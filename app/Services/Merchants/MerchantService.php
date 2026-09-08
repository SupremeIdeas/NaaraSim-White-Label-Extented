<?php

namespace App\Services\Merchants;

use App\Models\KycVerification;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Kyc\KycService;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\MerchantSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single owner of merchant lifecycle (ROADMAP §Layer 3.1). A KYB-verified
 * (KYC L3) user applies to become a merchant; an admin approves, which activates
 * the storefront and grants the additive `merchant` role. Suspend/reject flip the
 * status and role accordingly. The reseller margin is never set here — it's
 * admin-owned pricing config.
 */
class MerchantService
{
    public function __construct(private KycService $kyc, private WalletService $wallet)
    {
    }

    /**
     * The three unlock paths (ROADMAP §Layer 3) — a user qualifies by meeting
     * ANY one. Returned as structured criteria so the UI can show progress.
     *
     * @return array{eligible: bool, spend: array, enrollment: array, referrals: array}
     */
    public function eligibility(User $user): array
    {
        $spent = (float) ($user->wallet?->total_spent ?? 0);
        $referrals = $user->referralsMade()->count();
        $paidEnrollment = $user->merchant_enrollment_paid_at !== null;

        $spendMet = $spent >= MerchantSettings::minSpendUsd();
        $referralsMet = $referrals >= MerchantSettings::minReferrals();

        return [
            'eligible' => $spendMet || $paidEnrollment || $referralsMet,
            'spend' => ['met' => $spendMet, 'current' => round($spent, 2), 'required' => MerchantSettings::minSpendUsd()],
            'enrollment' => ['met' => $paidEnrollment, 'fee' => MerchantSettings::enrollmentFeeUsd()],
            'referrals' => ['met' => $referralsMet, 'current' => $referrals, 'required' => MerchantSettings::minReferrals()],
        ];
    }

    /**
     * Fast-route: pay the one-time enrollment fee from the wallet. It's a service
     * fee (not a product) — charged atomically and idempotently, never through
     * PricingEngine. Requires a funded wallet; a short balance surfaces cleanly so
     * the user tops up first.
     *
     * @throws MerchantException
     */
    public function payEnrollment(User $user): User
    {
        if (! MerchantSettings::enabled()) {
            throw new MerchantException('The merchant programme is not open right now.');
        }
        if ($user->merchant_enrollment_paid_at !== null) {
            return $user; // already paid — idempotent
        }

        $fee = MerchantSettings::enrollmentFeeUsd();
        try {
            $this->wallet->debit($user, $fee, 'USD', [
                'reference' => 'merchant_enrollment:'.$user->id,
                'description' => 'Merchant fast-route enrollment',
            ]);
        } catch (\App\Exceptions\InsufficientBalanceException $e) {
            throw new MerchantException('Top up your wallet with at least $'.number_format($fee, 2).' to use the fast route.');
        }

        $user->forceFill(['merchant_enrollment_paid_at' => now()])->save();
        Auditor::log('merchant.enrollment_paid', 'User', $user->id, ['fee' => $fee]);

        return $user;
    }

    /**
     * Apply to become a merchant. Requires the programme on AND that the user has
     * unlocked eligibility (spend / paid enrollment / referrals). Returns an
     * existing application if one is in flight.
     *
     * BUILD-4 §1: KYB (L3) is deliberately NO LONGER a gate here — identity
     * verification is deferred to payout time (a merchant verifies when they first
     * cash out, reusing the existing KYC-L2 payout-account gate, plus optional
     * business KYB above an admin threshold). Do not re-add a KYB check at the
     * application step: the whole point of this flow is to let a user set up a
     * storefront and start earning first, verifying only when money leaves.
     *
     * @param  array{business_name: string, brand_color?: string|null}  $data
     *
     * @throws MerchantException
     */
    public function apply(User $user, array $data): Merchant
    {
        if (! MerchantSettings::enabled()) {
            throw new MerchantException('The merchant programme is not open right now.');
        }
        if (! $this->eligibility($user)['eligible']) {
            throw new MerchantException('Unlock membership first: spend the minimum, pay the one-time enrollment, or reach the referral target.');
        }

        $existing = Merchant::query()->where('owner_user_id', $user->id)
            ->whereIn('status', [Merchant::PENDING, Merchant::ACTIVE])->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $data) {
            $merchant = Merchant::create([
                'owner_user_id' => $user->id,
                'business_name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'brand_color' => $data['brand_color'] ?? null,
                'status' => Merchant::PENDING,
            ]);

            Auditor::log('merchant.applied', 'Merchant', $merchant->id, ['user_id' => $user->id]);

            return $merchant;
        });
    }

    /**
     * Admin one-click promotion (BUILD-4 §4.2). Turns any user into an ACTIVE
     * merchant at the given tier with no application or fee — reusing the same
     * Merchant model + role grant as the normal path (never a second status
     * system). Idempotent-ish: an existing merchant is activated and its tier
     * raised. Fires a celebratory notification the UI can react to, and audits
     * who/when/why.
     *
     * @param  'v1'|'v2'  $tier
     */
    public function promote(User $user, string $tier, User $admin, ?string $reason = null): Merchant
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);
        $tier = $tier === Merchant::TIER_V2 ? Merchant::TIER_V2 : 'v1';

        return DB::transaction(function () use ($user, $tier, $admin, $reason) {
            $merchant = Merchant::query()->where('owner_user_id', $user->id)->latest('id')->first();

            if ($merchant === null) {
                $merchant = Merchant::create([
                    'owner_user_id' => $user->id,
                    'business_name' => $user->name ?: 'My storefront',
                    'slug' => $this->uniqueSlug($user->name ?: 'merchant'),
                    'status' => Merchant::ACTIVE,
                    'tier' => $tier,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                    'reason' => $reason,
                ]);
            } else {
                $merchant->forceFill([
                    'status' => Merchant::ACTIVE,
                    'tier' => $tier === Merchant::TIER_V2 ? Merchant::TIER_V2 : $merchant->tier,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                    'reason' => $reason,
                ])->save();
            }

            $user->assignRole('merchant');
            if ($tier === Merchant::TIER_V2) {
                $merchant->forceFill(['upgraded_at' => $merchant->upgraded_at ?? now()])->save();
            }

            Auditor::log('merchant.promoted', 'Merchant', $merchant->id, [
                'by' => $admin->id, 'user_id' => $user->id, 'tier' => $tier, 'reason' => $reason,
            ]);
            // BUILD-7 §4: pay the inviter's one-time merchant-referral bonus, if any.
            app(MerchantReferralService::class)->rewardReferrerIfEligible($user);

            return $merchant;
        });
    }

    /** Admin approves an application — activates it and grants the merchant role. */
    public function approve(Merchant $merchant, User $admin): Merchant
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);

        if ($merchant->status === Merchant::PENDING || $merchant->status === Merchant::SUSPENDED) {
            DB::transaction(function () use ($merchant, $admin) {
                $merchant->forceFill([
                    'status' => Merchant::ACTIVE,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                    'reason' => null,
                ])->save();
                $merchant->owner->assignRole('merchant');
            });
            Auditor::log('merchant.approved', 'Merchant', $merchant->id, ['by' => $admin->id]);
            // BUILD-7 §4: pay the inviter's one-time merchant-referral bonus, if any.
            app(MerchantReferralService::class)->rewardReferrerIfEligible($merchant->owner);
        }

        return $merchant;
    }

    /** Admin suspends an active merchant — storefront off, role removed. */
    public function suspend(Merchant $merchant, User $admin, string $reason = 'Suspended'): Merchant
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);

        DB::transaction(function () use ($merchant, $admin, $reason) {
            $merchant->forceFill([
                'status' => Merchant::SUSPENDED,
                'reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
            $merchant->owner->removeRole('merchant');
        });
        Auditor::log('merchant.suspended', 'Merchant', $merchant->id, ['by' => $admin->id, 'reason' => $reason]);

        return $merchant;
    }

    /** Admin rejects a pending application. */
    public function reject(Merchant $merchant, User $admin, string $reason = 'Not approved'): Merchant
    {
        abort_unless($admin->hasAnyRole(['super_admin', 'admin']), 403);

        if ($merchant->status === Merchant::PENDING) {
            $merchant->forceFill([
                'status' => Merchant::REJECTED,
                'reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
            Auditor::log('merchant.rejected', 'Merchant', $merchant->id, ['by' => $admin->id, 'reason' => $reason]);
        }

        return $merchant;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'merchant';
        $slug = $base;
        $i = 1;
        while (Merchant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
