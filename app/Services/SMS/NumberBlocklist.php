<?php

namespace App\Services\SMS;

use App\Models\BlockedNumber;

/**
 * Recycled-number pre-check (Prompt 11): the guard that stops a permanent
 * number we pulled for abuse/complaint from being re-provisioned to someone
 * else. Matching is on digits only, so a number blocked as "+1 555-000-1234"
 * still matches a provider that returns "+15550001234".
 *
 * This is deliberately scoped to OUR OWN released-number history — an honest,
 * deliverable guarantee. A cross-provider "this number was never anyone
 * else's" guarantee is not offerable and is intentionally NOT attempted.
 */
class NumberBlocklist
{
    /** Digits-only match key for a number, however it's formatted. */
    public function normalize(string $number): string
    {
        return (string) preg_replace('/\D/', '', $number);
    }

    /** Block a number from re-sale (idempotent on the normalized key). */
    public function block(
        string $number,
        ?string $reason = null,
        ?string $provider = null,
        string $source = 'admin',
        ?int $blockedBy = null,
    ): BlockedNumber {
        return BlockedNumber::updateOrCreate(
            ['msisdn' => $this->normalize($number)],
            [
                'phone_number' => $number,
                'provider' => $provider,
                'reason' => $reason,
                'source' => $source,
                'blocked_by' => $blockedBy,
            ],
        );
    }

    public function unblock(string $number): void
    {
        BlockedNumber::where('msisdn', $this->normalize($number))->delete();
    }

    public function isBlocked(string $number): bool
    {
        return BlockedNumber::where('msisdn', $this->normalize($number))->exists();
    }

    /**
     * The normalized keys, among the given numbers, that are blocked — one
     * query for a whole search result set rather than a query per candidate.
     *
     * @param  list<string>  $numbers
     * @return array<string, true>  normalized msisdn => true
     */
    public function blockedAmong(array $numbers): array
    {
        $keys = array_values(array_unique(array_map([$this, 'normalize'], $numbers)));
        if ($keys === []) {
            return [];
        }

        return BlockedNumber::whereIn('msisdn', $keys)
            ->pluck('msisdn')
            ->mapWithKeys(fn ($m) => [$m => true])
            ->all();
    }
}
