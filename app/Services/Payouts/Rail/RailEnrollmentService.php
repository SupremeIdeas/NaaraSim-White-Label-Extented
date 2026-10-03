<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutAccount;
use App\Models\PayoutRailEnrollment;
use App\Models\User;
use App\Support\Auditor;

/**
 * Tracks who chose a global payout rail (Funding Radar R1). The enrollment is for
 * tracking only — it never moves money and never changes `payout_accounts`.
 */
class RailEnrollmentService
{
    public function __construct(private StripeEligibility $stripe) {}

    /** @return list<string> */
    public static function globalProviders(): array
    {
        return (array) config('payouts.global_rail_providers', []);
    }

    /**
     * Global providers a user can actually be routed to right now: built into the core (manual_external) or an installed,
     * enabled updater-delivered rail. Payoneer/Grey/Stripe Global stay "coming soon" until their extension is installed.
     *
     * @return list<string>
     */
    public static function offeredGlobalProviders(): array
    {
        return array_values(array_filter(self::globalProviders(), fn ($p) => \App\Services\Payouts\Extensions\PayoutRailExtensions::offerable($p)));
    }

    public static function isGlobal(?string $provider): bool
    {
        return $provider !== null && in_array($provider, self::globalProviders(), true);
    }

    /** The user picked a global rail (idempotent: re-selecting never resets progress). */
    public function select(User $user, string $provider, string $country, ?string $currency = null, bool $enforce = false): PayoutRailEnrollment
    {
        if (! self::isGlobal($provider)) {
            throw new \InvalidArgumentException("{$provider} is not a global-rail provider.");
        }
        // User-initiated selections go through the guide's server-side rule (offered for the
        // country + acknowledged). Internal callers (backfill, sync) do not.
        $enforce && app(RailGuideService::class)->assertMayEnroll($user, $provider, strtoupper($country));
        $country = strtoupper($country);
        $eligibility = $this->stripe->check($country, $user);

        $enrollment = PayoutRailEnrollment::firstOrNew(['user_id' => $user->id, 'provider' => $provider]);
        $isNew = ! $enrollment->exists;

        $enrollment->fill([
            'country' => $country,
            'currency' => $currency ? strtoupper($currency) : $enrollment->currency,
            'stripe_connect_eligible' => $eligibility['eligible'],
            // Eligible but chose global anyway = a plain user choice.
            'ineligible_reason' => $eligibility['eligible'] ? StripeEligibility::USER_CHOICE : $eligibility['reason'],
        ]);
        if ($isNew) {
            $enrollment->fill(['status' => PayoutRailEnrollment::SELECTED, 'selected_at' => now(), 'last_status_at' => now()]);
        }
        $enrollment->save();

        $isNew && Auditor::log('payout.rail_selected', 'PayoutRailEnrollment', $enrollment->id, ['provider' => $provider, 'country' => $country]);

        return $enrollment;
    }

    /**
     * Keep the enrollment consistent with the money-path account. Called whenever a
     * global-rail payout account is saved (and from provider webhook handlers when
     * those rails ship). Never downgrades a paused/declined decision made by a human.
     */
    public function sync(PayoutAccount $account): ?PayoutRailEnrollment
    {
        if (! self::isGlobal($account->provider)) {
            return null;
        }
        $enrollment = PayoutRailEnrollment::firstOrNew(['user_id' => $account->user_id, 'provider' => $account->provider]);
        if (! $enrollment->exists) {
            $elig = $this->stripe->check((string) $account->country, $account->user);
            $enrollment->fill([
                'country' => strtoupper((string) $account->country), 'currency' => $account->currency, 'selected_at' => now(),
                'stripe_connect_eligible' => $elig['eligible'],
                'ineligible_reason' => $elig['eligible'] ? StripeEligibility::USER_CHOICE : $elig['reason'],
            ]);
        }

        $target = match (true) {
            $account->provider_status === 'declined' => PayoutRailEnrollment::DECLINED,
            $account->is_verified => PayoutRailEnrollment::ACTIVE,
            default => PayoutRailEnrollment::ONBOARDING,
        };
        if ($enrollment->status === PayoutRailEnrollment::PAUSED && $target !== PayoutRailEnrollment::DECLINED) {
            $target = PayoutRailEnrollment::PAUSED; // an admin pause survives provider status chatter
        }

        $enrollment->fill([
            'payout_account_id' => $account->id, 'status' => $target, 'last_status_at' => now(),
            'activated_at' => $target === PayoutRailEnrollment::ACTIVE ? ($enrollment->activated_at ?? now()) : $enrollment->activated_at,
        ])->save();

        return $enrollment;
    }

    public function pause(PayoutRailEnrollment $e, User $admin, string $note): void
    {
        $e->forceFill(['status' => PayoutRailEnrollment::PAUSED, 'last_status_at' => now()])->save();
        Auditor::log('payout.rail_paused', 'PayoutRailEnrollment', $e->id, ['by' => $admin->id, 'note' => $note]);
    }

    public function resume(PayoutRailEnrollment $e, User $admin, string $note): void
    {
        $status = $e->account?->is_verified ? PayoutRailEnrollment::ACTIVE : ($e->account ? PayoutRailEnrollment::ONBOARDING : PayoutRailEnrollment::SELECTED);
        $e->forceFill(['status' => $status, 'last_status_at' => now()])->save();
        Auditor::log('payout.rail_resumed', 'PayoutRailEnrollment', $e->id, ['by' => $admin->id, 'note' => $note]);
    }

    /** Enrollments for existing users whose payout account is already on a global rail. Idempotent. */
    public function backfill(): int
    {
        $n = 0;
        PayoutAccount::query()->whereIn('provider', self::globalProviders())->orderBy('id')->each(function (PayoutAccount $a) use (&$n) {
            $this->sync($a) && $n++;
        });

        return $n;
    }
}
