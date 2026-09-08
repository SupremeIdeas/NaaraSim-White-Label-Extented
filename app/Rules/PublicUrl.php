<?php

namespace App\Rules;

use App\Support\Security\SsrfGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule wrapping the SsrfGuard (blueprint Section 30): a URL that
 * resolves to a private/reserved address (or uses a non-http scheme) fails
 * validation. Use on any user-supplied URL the server might fetch.
 */
class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (! SsrfGuard::isSafe((string) $value)) {
            $fail('The :attribute must be a public http(s) URL (private or reserved hosts are not allowed).');
        }
    }
}
