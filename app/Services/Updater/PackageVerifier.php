<?php

namespace App\Services\Updater;

use App\Support\UpdateManifest;
use ZipArchive;

/**
 * Verifies a `.naaraupdate` package (blueprint Batch 1 §1.4). This is the single
 * trust gate for the whole updater system: the apply engine (Batch 2), the
 * distribution download flow (Batch 5) and the `update:verify` command all call
 * THIS class — one verification implementation, called from everywhere that
 * consumes a package, mirroring the "one source of truth" discipline used
 * elsewhere in the codebase.
 *
 * What "verified" means, concretely:
 *   1. The package carries an Ed25519 signature over the SHA-256 of its
 *      manifest.json, and that signature checks out against the PUBLIC key this
 *      instance ships (config/updater.php). The private key never touches a
 *      deployed app, so a server compromise cannot forge an update.
 *   2. Because the manifest embeds a SHA-256 for every payload file, signing the
 *      manifest transitively covers every byte — tampering with any file changes
 *      its checksum, which changes the manifest, which breaks the signature.
 *   3. Every payload file physically present is re-hashed and matched against
 *      the manifest before this returns success.
 *
 * Nothing here writes to the live application or extracts untrusted paths to
 * disk — payload bytes are read straight out of the zip by their expected name
 * and hashed in memory, so verification itself cannot be abused for path
 * traversal. (Batch 2's apply step, which does write to disk, carries its own
 * extraction guard.)
 */
class PackageVerifier
{
    /** In-package prefix under which every checksummed content file lives. */
    public const PAYLOAD_PREFIX = 'payload/';

    public function verify(string $packagePath): VerificationResult
    {
        if (! is_file($packagePath) || ! is_readable($packagePath)) {
            return VerificationResult::fail("Package not found or unreadable: {$packagePath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($packagePath) !== true) {
            return VerificationResult::fail('Could not open the package — it is not a valid .naaraupdate archive.');
        }

        try {
            // A tampered or traversal-laden entry name is a hard reject before
            // we trust anything else in the archive.
            if ($badEntry = $this->firstUnsafeEntry($zip)) {
                return VerificationResult::fail("Refusing package: unsafe archive entry '{$badEntry}'.");
            }

            $manifestBytes = $zip->getFromName('manifest.json');
            if ($manifestBytes === false) {
                return VerificationResult::fail('Package has no manifest.json.');
            }

            $signatureB64 = $zip->getFromName('signature.sig');
            if ($signatureB64 === false) {
                return VerificationResult::fail('Package is unsigned (no signature.sig).');
            }

            // --- Signature check (before decoding or trusting the manifest) ---
            $signatureCheck = $this->checkSignature($manifestBytes, trim($signatureB64));
            if ($signatureCheck !== null) {
                return VerificationResult::fail($signatureCheck);
            }

            // --- Decode + structural validation ---
            $decoded = json_decode($manifestBytes, true);
            if (! is_array($decoded)) {
                return VerificationResult::fail('manifest.json is not valid JSON.');
            }

            try {
                $manifest = UpdateManifest::fromArray($decoded);
            } catch (\InvalidArgumentException $e) {
                return VerificationResult::fail($e->getMessage());
            }

            if (! UpdateManifest::isValidVersion($manifest->version)) {
                return VerificationResult::fail("Invalid version string '{$manifest->version}' (expected YYYY.MM.DD-N).");
            }
            if (! UpdateManifest::isValidVersion($manifest->minCompatibleVersion)) {
                return VerificationResult::fail("Invalid min_compatible_version '{$manifest->minCompatibleVersion}' (expected YYYY.MM.DD-N).");
            }

            // --- Per-file checksum check ---
            if ($fileError = $this->checkPayloadFiles($zip, $manifest)) {
                return VerificationResult::fail($fileError);
            }

            return VerificationResult::ok($manifest);
        } finally {
            $zip->close();
        }
    }

    /**
     * Verify the detached Ed25519 signature over the SHA-256 of the manifest
     * bytes. Returns null on success or a human-readable reason on failure.
     * Every failure mode a real instance can hit — no key configured, a
     * malformed key, an outdated key that simply doesn't match — resolves to a
     * clear message here rather than a crash.
     */
    private function checkSignature(string $manifestBytes, string $signatureB64): ?string
    {
        $publicKeyB64 = config('updater.public_key');
        if (empty($publicKeyB64)) {
            return 'No update public key is configured (set NAARA_UPDATE_PUBLIC_KEY).';
        }

        $publicKey = base64_decode($publicKeyB64, true);
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return 'The configured update public key is malformed.';
        }

        $signature = base64_decode($signatureB64, true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return 'The package signature is malformed.';
        }

        $message = hash('sha256', $manifestBytes, true);

        try {
            $valid = sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
        } catch (\SodiumException $e) {
            return 'Signature verification could not be performed: '.$e->getMessage();
        }

        return $valid ? null : 'Signature verification failed — this package was not signed by a trusted key, or it has been tampered with.';
    }

    /**
     * Re-hash every file the manifest claims and match it against the recorded
     * checksum and size. Reading by expected name (rather than iterating archive
     * contents) means an attacker can't smuggle in an extra unsigned file and
     * have it pass — only what the signed manifest lists is trusted.
     */
    private function checkPayloadFiles(ZipArchive $zip, UpdateManifest $manifest): ?string
    {
        foreach ($manifest->files as $entry) {
            $path = $entry['path'] ?? null;
            $action = $entry['action'] ?? null;

            if (! is_string($path) || $path === '') {
                return 'manifest.json has a file entry with no path.';
            }

            // Deletes carry no content to verify (handled via deletions).
            if ($action === 'delete') {
                continue;
            }

            $bytes = $zip->getFromName(self::PAYLOAD_PREFIX.$path);
            if ($bytes === false) {
                return "Manifest lists '{$path}' but its payload is missing from the package.";
            }

            $expectedSha = $entry['sha256'] ?? '';
            $actualSha = hash('sha256', $bytes);
            if (! is_string($expectedSha) || ! hash_equals($expectedSha, $actualSha)) {
                return "Checksum mismatch for '{$path}' — the package is corrupt or has been tampered with.";
            }

            if (isset($entry['size_bytes']) && (int) $entry['size_bytes'] !== strlen($bytes)) {
                return "Size mismatch for '{$path}' — the package is corrupt or has been tampered with.";
            }
        }

        return null;
    }

    /**
     * Scan every archive entry for a name that would escape its intended
     * location (absolute path, `..` traversal, or a backslash that some
     * extractors normalise into a separator). Defence in depth: verification
     * itself never extracts, but rejecting these here means a hostile package
     * never reaches Batch 2's apply step in the first place.
     */
    private function firstUnsafeEntry(ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            $normalised = str_replace('\\', '/', $name);

            if (str_starts_with($normalised, '/')
                || preg_match('#(^|/)\.\.(/|$)#', $normalised) === 1
                || preg_match('#^[A-Za-z]:#', $name) === 1) {
                return $name;
            }
        }

        return null;
    }
}
