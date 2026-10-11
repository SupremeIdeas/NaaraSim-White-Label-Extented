<?php

namespace App\Services\Payouts\Hardening;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;

/**
 * Freezes WHERE a payout is going at the moment the request is created (Addendum D-3.1).
 *
 * The snapshot (encrypted on the request) holds the full destination; the digest is a keyed
 * hash over the fields that decide where money lands (provider, method, country, currency,
 * bank code, account number). Before any provider call the engine recomputes the digest from
 * the live account: if the user edited or deleted the account in between, the payout is NOT
 * sent — it goes to manual review, because that pattern is what an account takeover looks like.
 */
class DestinationSnapshot
{
    /** @return array<string, mixed> */
    public function capture(PayoutAccount $account): array
    {
        return [
            'provider' => $account->provider,
            'method' => $account->method,
            'type' => $account->type,
            'country' => $account->country,
            'currency' => $account->currency,
            'bank_code' => $account->bank_code,
            'account_name' => $account->account_name,
            'identifier_masked' => self::mask((string) $account->account_number),
            'recipient_ref' => $account->provider_recipient_ref,
            'fingerprint' => $account->lookup_hash,
            'payout_account_id' => $account->id,
            'digest' => $this->digestFor($account),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /** Write the snapshot + digest onto a freshly created request (same transaction as the hold). */
    public function stamp(PayoutRequest $request, PayoutAccount $account): void
    {
        // Read the row as the database holds it: a just-created in-memory model lacks column
        // defaults (method, …), which would make the digest differ from the one send() computes.
        $snap = $this->capture($account->exists ? ($account->newQuery()->find($account->id) ?? $account) : $account);
        $request->forceFill([
            'destination_snapshot' => encrypt(json_encode($snap)),
            'destination_digest' => $snap['digest'],
        ])->save();
    }

    /** @return array<string, mixed>|null */
    public function read(PayoutRequest $request): ?array
    {
        if (blank($request->destination_snapshot)) {
            return null;
        }
        try {
            return json_decode(decrypt($request->destination_snapshot), true) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Has the destination changed since the request was made?
     * Returns null when it has not (or the request pre-dates snapshots — legacy rows are left alone),
     * otherwise a short machine reason.
     */
    public function drift(PayoutRequest $request, ?PayoutAccount $live): ?string
    {
        if (blank($request->destination_digest)) {
            return null;
        }
        if ($live === null) {
            return 'account_deleted_after_request';
        }

        return hash_equals($request->destination_digest, $this->digestFor($live)) ? null : 'account_changed_after_request';
    }

    public function digestFor(PayoutAccount $account): string
    {
        $parts = [
            strtolower((string) $account->provider), strtolower((string) $account->method), strtoupper((string) $account->country),
            strtoupper((string) $account->currency), strtolower((string) $account->bank_code),
            strtolower(preg_replace('/\s+/', '', (string) $account->account_number)),
        ];

        return hash_hmac('sha256', implode('|', $parts), (string) config('app.key'));
    }

    public static function mask(string $identifier): string
    {
        $len = mb_strlen($identifier);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', max(0, $len - 4)).mb_substr($identifier, -4);
    }
}
