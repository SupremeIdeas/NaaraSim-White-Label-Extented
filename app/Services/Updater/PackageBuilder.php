<?php

namespace App\Services\Updater;

use App\Support\UpdateManifest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Builds and signs a `.naaraupdate` package (blueprint Batch 1 §1.5–§1.6).
 *
 * Kept as a standalone service rather than command-only logic so it is
 * unit-testable and so Batch 4's "build and publish" admin flow can call it
 * directly without shelling out to Artisan. The signing PRIVATE key is passed
 * in at build time (from `--key=` or a CI secret) and never stored — it is the
 * one secret that must never live inside a deployed app.
 *
 * Package layout produced (a deliberate, security-first reading of §1.2):
 *   manifest.json      — the signed source of truth
 *   signature.sig      — base64 Ed25519 signature over SHA-256(manifest.json)
 *   payload/<repo path> — every shipped content file, at its repo-relative path
 *   deletions.json     — paths to remove (optional)
 * Migration files are shipped as ordinary payload files under
 * `payload/database/migrations/…`, so they carry a per-file checksum in the
 * manifest exactly like any other file (the §1.3 example listed migrations by
 * name only; checksumming them too closes a tamper gap). Their bare filenames
 * are ALSO listed in the manifest's `migrations` array so Batch 2 can scope
 * `migrate --path` to precisely this package's migrations.
 */
class PackageBuilder
{
    /**
     * @param  array<string,mixed>  $params  See build() body for the accepted keys.
     * @return string  Absolute path to the written .naaraupdate file.
     */
    public function build(array $params): string
    {
        $privateKey = $this->decodePrivateKey($params['private_key'] ?? null);

        /** @var array<int,array{path:string,source?:string,content?:string,action?:string}> $inputFiles */
        $inputFiles = $params['files'] ?? [];
        $deletions = array_values($params['deletions'] ?? []);

        [$files, $payload, $migrations] = $this->collectFiles($inputFiles);

        if ($files === [] && $deletions === []) {
            throw new \InvalidArgumentException('Refusing to build an empty package — no files and no deletions.');
        }

        $outputDir = $params['output_dir'] ?? config('updater.package_storage_path');
        File::ensureDirectoryExists($outputDir);

        $version = $params['version'] ?? $this->nextVersion($outputDir);
        if (! UpdateManifest::isValidVersion($version)) {
            throw new \InvalidArgumentException("Version '{$version}' is not in YYYY.MM.DD-N form.");
        }

        $manifest = new UpdateManifest(
            packageId: (string) Str::uuid(),
            product: $params['product'] ?? config('updater.product_identifier'),
            version: $version,
            minCompatibleVersion: $params['min_compatible_version'] ?? $version,
            packageType: $params['package_type'] ?? $this->inferType($files, $migrations),
            builtAt: now()->toIso8601String(),
            builtBy: $params['built_by'] ?? null,
            sourceBranch: $params['source_branch'] ?? null,
            changelog: $params['changelog'] ?? '',
            files: $files,
            migrations: $migrations,
            deletions: $deletions,
            requiresComposerInstall: (bool) ($params['requires_composer_install'] ?? false),
            requiresNpmBuild: (bool) ($params['requires_npm_build'] ?? false),
            tierRequirement: $params['tier_requirement'] ?? null,
        );

        $manifestBytes = $this->encodeManifest($manifest);
        $signature = base64_encode($this->sign($manifestBytes, $privateKey));

        $path = rtrim($outputDir, '/')."/update-{$version}.naaraupdate";
        $this->writeZip($path, $manifestBytes, $signature, $payload, $deletions);

        return $path;
    }

    /** Generate a fresh Ed25519 keypair as base64 strings for `update:keygen`. */
    public static function generateKeypair(): array
    {
        $keypair = sodium_crypto_sign_keypair();

        return [
            'public' => base64_encode(sodium_crypto_sign_publickey($keypair)),
            'private' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
        ];
    }

    /**
     * Read each input file's bytes, compute its checksum, and split the result
     * into the manifest `files` array, an in-memory payload map, and the
     * derived list of migration filenames.
     *
     * @param  array<int,array<string,mixed>>  $inputFiles
     * @return array{0:array<int,array<string,mixed>>,1:array<string,string>,2:array<int,string>}
     */
    private function collectFiles(array $inputFiles): array
    {
        $files = [];
        $payload = [];
        $migrations = [];

        foreach ($inputFiles as $entry) {
            $path = $entry['path'] ?? null;
            if (! is_string($path) || $path === '') {
                throw new \InvalidArgumentException('Every file entry needs a repo-relative path.');
            }

            $action = $entry['action'] ?? 'add';
            if ($action === 'delete') {
                // Deletes belong in deletions.json, not the content payload.
                continue;
            }

            if (array_key_exists('content', $entry)) {
                $content = (string) $entry['content'];
            } elseif (! empty($entry['source'])) {
                if (! is_file($entry['source'])) {
                    throw new \InvalidArgumentException("Source file does not exist: {$entry['source']}");
                }
                $content = (string) file_get_contents($entry['source']);
            } else {
                throw new \InvalidArgumentException("File '{$path}' has neither content nor a readable source.");
            }

            $payload[$path] = $content;
            $files[] = [
                'path' => $path,
                'action' => $action,
                'sha256' => hash('sha256', $content),
                'size_bytes' => strlen($content),
            ];

            if (preg_match('#^database/migrations/(.+\.php)$#', $path, $m) === 1) {
                $migrations[] = $m[1];
            }
        }

        sort($migrations);

        return [$files, $payload, $migrations];
    }

    /**
     * @param  array<int,array<string,mixed>>  $files
     * @param  array<int,string>  $migrations
     */
    private function inferType(array $files, array $migrations): string
    {
        if ($files === []) {
            return 'migrations';
        }

        return $migrations === [] ? 'code' : 'code_and_migrations';
    }

    /** Canonical, stable JSON bytes for the manifest — the exact bytes we sign. */
    private function encodeManifest(UpdateManifest $manifest): string
    {
        return (string) json_encode(
            $manifest->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    private function sign(string $manifestBytes, string $privateKey): string
    {
        return sodium_crypto_sign_detached(hash('sha256', $manifestBytes, true), $privateKey);
    }

    private function decodePrivateKey(?string $privateKeyB64): string
    {
        if (empty($privateKeyB64)) {
            throw new \InvalidArgumentException('A private signing key is required to build a package.');
        }

        $key = base64_decode(trim($privateKeyB64), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \InvalidArgumentException('The private signing key is malformed (expected a base64 Ed25519 secret key).');
        }

        return $key;
    }

    /**
     * Next daily sequence: scan the output dir for today's packages and add one,
     * so the Nth package built today is `YYYY.MM.DD-N`.
     */
    private function nextVersion(string $outputDir): string
    {
        $today = now()->format('Y.m.d');
        $n = 1;

        if (is_dir($outputDir)) {
            foreach (glob(rtrim($outputDir, '/')."/update-{$today}-*.naaraupdate") ?: [] as $existing) {
                if (preg_match('/-'.preg_quote($today, '/').'-(\d+)\.naaraupdate$/', $existing, $m) === 1) {
                    $n = max($n, ((int) $m[1]) + 1);
                }
            }
        }

        return "{$today}-{$n}";
    }

    /**
     * @param  array<string,string>  $payload  repo-relative path => content
     * @param  array<int,string>  $deletions
     */
    private function writeZip(string $path, string $manifestBytes, string $signatureB64, array $payload, array $deletions): void
    {
        if (is_file($path)) {
            @unlink($path);
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create package archive at {$path}.");
        }

        $zip->addFromString('manifest.json', $manifestBytes);
        $zip->addFromString('signature.sig', $signatureB64);

        foreach ($payload as $relPath => $content) {
            $zip->addFromString(PackageVerifier::PAYLOAD_PREFIX.$relPath, $content);
        }

        if ($deletions !== []) {
            $zip->addFromString('deletions.json', (string) json_encode(
                array_values($deletions),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ));
        }

        $zip->close();
    }
}
