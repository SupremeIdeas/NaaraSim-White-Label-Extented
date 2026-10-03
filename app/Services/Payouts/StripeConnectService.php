<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Connect onboarding (ROADMAP §Layer 0.2 — Stripe payout rail). Unlike
 * a bank account or PayPal email, a Stripe destination is a connected Express
 * account that must complete Stripe's own hosted onboarding (identity,
 * banking details, capability review) before it can receive a transfer — this
 * service owns creating that account, generating the onboarding link, and
 * syncing its status (via the account.updated webhook, and a synchronous
 * refresh when the user lands back from onboarding).
 *
 * `is_verified` is kept equal to `payouts_enabled` so every existing
 * money-path check (WithdrawalService, PayoutService::createRequest) already
 * refuses an account that hasn't finished onboarding — no changes needed
 * there for a third account type to be safe.
 */
class StripeConnectService
{
    public function available(): bool
    {
        return filled(config('services.stripe.secret_key'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.stripe.base_url'), '/');
    }

    private function client()
    {
        return Http::withToken((string) config('services.stripe.secret_key'))
            ->asForm()->timeout(8)->connectTimeout(3);
    }

    /**
     * The user's Stripe payout account, creating an Express account with
     * Stripe if they don't have one yet.
     */
    public function accountFor(User $user): PayoutAccount
    {
        $existing = PayoutAccount::query()
            ->where('user_id', $user->id)->where('type', 'stripe')->first();
        if ($existing !== null) {
            return $existing;
        }

        $country = strtoupper((string) $user->country_code);
        $hasCountry = (bool) preg_match('/^[A-Z]{2}$/', $country) && $country !== 'ZZ';

        // Once Stripe corridors are configured (Phase 3b) the corridor table decides
        // where Stripe may be used; until any exist, behaviour is unchanged.
        if (PayoutCorridor::query()->where('provider', 'stripe')->exists()
            && ! ($hasCountry && PayoutCorridor::query()->enabled()->where('provider', 'stripe')->where('country', $country)->exists())) {
            throw new PayoutException("Stripe payouts aren't available in your country yet.");
        }

        $response = $this->client()->post($this->base().'/accounts', array_filter([
            'type' => 'express',
            'email' => $user->email,
            // Stripe fixes the account's country at creation and it cannot be changed
            // later — never let it silently default to the platform's country.
            'country' => $hasCountry ? $country : null,
            'capabilities' => [
                'transfers' => ['requested' => 'true'],
            ],
        ], fn ($v) => $v !== null))->throw()->json();

        $accountId = (string) $response['id'];

        $isFirst = ! PayoutAccount::query()->where('user_id', $user->id)->exists();
        $account = PayoutAccount::create([
            'user_id' => $user->id,
            'type' => 'stripe',
            'country' => $user->country_code ?: 'ZZ',
            'currency' => 'USD',
            'bank_code' => 'stripe',
            'bank_name' => 'Stripe',
            'account_number' => $accountId,
            'account_name' => $accountId,
            'provider' => 'stripe',
            'provider_recipient_ref' => $accountId,
            'is_verified' => false,
            'is_default' => $isFirst,
        ]);

        Auditor::log('payout.account_added', 'PayoutAccount', $account->id, [
            'provider' => 'stripe', 'type' => 'stripe',
        ]);

        return $account;
    }

    /**
     * A fresh hosted onboarding link for the account (Account Links expire a
     * few minutes after issue — always generate a new one, never cache it).
     */
    public function onboardingUrl(PayoutAccount $account, string $refreshUrl, string $returnUrl): string
    {
        $response = $this->client()->post($this->base().'/account_links', [
            'account' => $account->account_number,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ])->throw()->json();

        return (string) $response['url'];
    }

    /** Pull the account's live capability status from Stripe and persist it. */
    public function refreshStatus(PayoutAccount $account): PayoutAccount
    {
        $response = $this->client()->get($this->base().'/accounts/'.$account->account_number)->throw()->json();

        return $this->applyStatus($account, $response);
    }

    /** Apply a status snapshot (from a refresh or an account.updated webhook). */
    public function applyStatus(PayoutAccount $account, array $stripeAccount): PayoutAccount
    {
        $payoutsEnabled = (bool) ($stripeAccount['payouts_enabled'] ?? false);

        $account->forceFill([
            'details_submitted' => (bool) ($stripeAccount['details_submitted'] ?? false),
            'charges_enabled' => (bool) ($stripeAccount['charges_enabled'] ?? false),
            'payouts_enabled' => $payoutsEnabled,
            'is_verified' => $payoutsEnabled,
            // A rejected Connect account is recorded so the guide/enrollment can stop offering Stripe.
            'provider_status' => str_starts_with((string) data_get($stripeAccount, 'requirements.disabled_reason', ''), 'rejected')
                ? 'rejected'
                : ($payoutsEnabled ? 'active' : $account->provider_status),
        ])->save();

        return $account;
    }

    /** Find the payout account a Connect account.updated event refers to. */
    public function findByAccountId(string $stripeAccountId): ?PayoutAccount
    {
        return PayoutAccount::query()
            ->where('provider', 'stripe')
            // account_number is encrypted, so match the mirrored ref or the blind index.
            ->where(fn ($q) => $q->where('provider_recipient_ref', $stripeAccountId)
                ->orWhere('lookup_hash', PayoutAccount::lookupHashFor($stripeAccountId)))
            ->first();
    }
}
