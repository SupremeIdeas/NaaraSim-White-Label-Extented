<?php

namespace App\Services\Payouts;

use App\Models\PayoutAccount;
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

        $response = $this->client()->post($this->base().'/accounts', [
            'type' => 'express',
            'email' => $user->email,
            'capabilities' => [
                'transfers' => ['requested' => 'true'],
            ],
        ])->throw()->json();

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
        ])->save();

        return $account;
    }

    /** Find the payout account a Connect account.updated event refers to. */
    public function findByAccountId(string $stripeAccountId): ?PayoutAccount
    {
        return PayoutAccount::query()
            ->where('provider', 'stripe')->where('account_number', $stripeAccountId)->first();
    }
}
