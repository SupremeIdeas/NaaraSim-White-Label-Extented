<?php

namespace App\Support\Maintenance;

use App\Services\Maintenance\ProposedFix;

/**
 * Enforces the non-negotiable rule that the maintenance loop NEVER touches
 * secrets (blueprint Section 29 & money-safety rule 10). A proposed fix is
 * refused if it changes a protected file (.env, keys, certificates) or if its
 * content introduces anything that looks like a secret value.
 */
class SecretGuard
{
    /** Files the loop may never modify. */
    private const PROTECTED_PATHS = [
        '#(^|/)\.env#',                 // .env, .env.example, .env.*
        '#\.(pem|key|crt|p12|pfx)$#',   // certificates / private keys
        '#(^|/)auth\.json$#',           // composer auth
        '#(^|/)storage/oauth-#',        // Passport keys
    ];

    /** Content patterns that indicate a secret is being written. */
    private const SECRET_CONTENT = [
        '#(API|SECRET|PRIVATE|ACCESS)_?KEY\s*[=:]\s*[\'"]?[A-Za-z0-9/_\-]{12,}#i',
        '#PASSWORD\s*[=:]\s*[\'"]?\S{6,}#i',
        '#sk_(live|test)_[A-Za-z0-9]{10,}#',   // Stripe-style secret keys
        '#-----BEGIN [A-Z ]*PRIVATE KEY-----#',
    ];

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 if the fix touches secrets
     */
    public function assertSafe(ProposedFix $fix): void
    {
        foreach ($fix->targetFiles() as $path) {
            foreach (self::PROTECTED_PATHS as $pattern) {
                abort_if(preg_match($pattern, $path) === 1, 422,
                    "This fix touches a protected file ({$path}) and was blocked — the maintenance loop never edits secrets.");
            }
        }

        $haystack = $fix->diff."\n".implode("\n", $fix->changes);
        foreach (self::SECRET_CONTENT as $pattern) {
            abort_if(preg_match($pattern, $haystack) === 1, 422,
                'This fix appears to write a secret value and was blocked — the maintenance loop never edits secrets.');
        }
    }

    public function isSafe(ProposedFix $fix): bool
    {
        try {
            $this->assertSafe($fix);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
