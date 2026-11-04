<?php

namespace App\Support\Activation;

/**
 * Proves an entitlement answer really came from the platform that licensed this install. The platform signs it (Ed25519) with a private key that never
 * leaves it; this install holds only the public key. A copy of the code pointed at a fake "platform" cannot produce a valid signature, and an answer
 * meant for another licence (or an expired one) is refused.
 */
final class EntitlementVerifier
{
    public static function publicKey(): ?string
    {
        $b64 = config('updater.licence_public_key');
        if (! is_string($b64) || trim($b64) === '') {
            return null;
        }
        $raw = base64_decode(trim($b64), true);

        return $raw !== false && strlen($raw) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $raw : null;
    }

    /** With a public key set, only signed answers are believed. Without one this install cannot tell a real answer from a forged one. */
    public static function configured(): bool
    {
        return self::publicKey() !== null;
    }

    /**
     * @param  array{payload?: mixed, sig?: mixed}  $signed
     * @return array<string, mixed>|null  the verified payload, or null when the signature, licence reference or expiry does not hold
     */
    public static function verify(array $signed, ?string $expectedRef = null, bool $allowExpired = false): ?array
    {
        $key = self::publicKey();
        if ($key === null || ! is_string($signed['payload'] ?? null) || ! is_string($signed['sig'] ?? null)) {
            return null;
        }
        $payload = base64_decode($signed['payload'], true);
        $sig = base64_decode($signed['sig'], true);
        if ($payload === false || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }
        try {
            if (! sodium_crypto_sign_verify_detached($sig, hash('sha256', $payload, true), $key)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }
        $p = json_decode($payload, true);
        if (! is_array($p) || ($p['v'] ?? null) !== 1 || ! is_array($p['locks'] ?? null)) {
            return null;
        }
        if (! $allowExpired && (int) ($p['exp'] ?? 0) < time()) {
            return null;
        }
        if ($expectedRef !== null && $expectedRef !== '' && ! hash_equals((string) $expectedRef, (string) ($p['ref'] ?? ''))) {
            return null;
        }

        return $p;
    }
}
