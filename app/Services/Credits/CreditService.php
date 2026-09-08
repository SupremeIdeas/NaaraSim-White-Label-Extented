<?php

namespace App\Services\Credits;

use App\Models\CreditLedger;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\Pricing\DiscountMarginGuard;
use App\Support\CreditSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NaaraCredits ledger service (loyalty module). The single owner of credit
 * balance changes, with the same discipline as the money WalletService:
 *   - every change runs under a per-user lock + DB transaction,
 *   - writes a credit_ledger row with balance_after in the same transaction,
 *   - is idempotent by reference (an earn/spend never double-applies),
 * so a replayed ad postback, a double-tapped check-in, or a retried job all
 * move credits exactly once.
 *
 * Credits are NOT money — they never touch the money wallet columns.
 */
class CreditService
{
    private const SCALE = 2;

    public function balance(User $user): float
    {
        return (float) ($user->wallet?->naara_credits ?? 0);
    }

    /**
     * The withdrawable subset of the balance (ROADMAP §Layer 1) — only credits
     * from first-referral rewards. Cash-out is capped at this, never the whole
     * balance.
     */
    public function withdrawableBalance(User $user): float
    {
        return (float) ($user->wallet?->withdrawable_credits ?? 0);
    }

    /**
     * Grant credits. `reference` makes it idempotent per earning event
     * (e.g. "checkin:{userId}:{date}", "ad:{externalTxnId}"). Set $withdrawable
     * only for eligible referral rewards — it also grows the withdrawable bucket.
     */
    public function earn(User $user, float $credits, string $source, ?string $reference = null, ?string $description = null, bool $withdrawable = false): CreditLedger
    {
        return $this->apply($user, 'earn', $credits, $source, $reference, $description, $withdrawable);
    }

    /** Spend credits (e.g. redeemed at checkout). Throws if the balance is short. */
    public function spend(User $user, float $credits, string $source, ?string $reference = null, ?string $description = null): CreditLedger
    {
        return $this->apply($user, 'spend', $credits, $source, $reference, $description);
    }

    /**
     * Spend specifically from the withdrawable bucket (the cash-out HOLD). Throws
     * if the withdrawable balance can't cover it — you can never cash out more
     * than you earned from referrals.
     */
    public function spendWithdrawable(User $user, float $credits, string $source, ?string $reference = null, ?string $description = null): CreditLedger
    {
        return $this->apply($user, 'spend', $credits, $source, $reference, $description, fromWithdrawable: true);
    }

    /**
     * Grant a withdrawable first-referral reward, once per referred person. The
     * reference guarantees a given referral is only ever rewarded once.
     */
    public function rewardReferral(User $referrer, int $referredId, float $credits, ?string $description = null): CreditLedger
    {
        return $this->earn(
            $referrer, $credits, 'referral',
            "referral:{$referrer->id}:{$referredId}",
            $description ?? 'Referral reward',
            withdrawable: true,
        );
    }

    private function apply(User $user, string $type, float $credits, string $source, ?string $reference, ?string $description, bool $withdrawable = false, bool $fromWithdrawable = false): CreditLedger
    {
        $credits = round($credits, self::SCALE);
        if ($credits <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return Cache::lock("credits:{$user->id}", 10)->block(5, function () use ($user, $type, $credits, $source, $reference, $description, $withdrawable, $fromWithdrawable) {
            return DB::transaction(function () use ($user, $type, $credits, $source, $reference, $description, $withdrawable, $fromWithdrawable) {
                if ($reference !== null) {
                    $existing = CreditLedger::where('user_id', $user->id)->where('reference', $reference)->first();
                    if ($existing !== null) {
                        return $existing; // idempotent replay
                    }
                }

                /** @var UserWallet $wallet */
                $wallet = UserWallet::where('user_id', $user->id)->lockForUpdate()->first()
                    ?? UserWallet::create(['user_id' => $user->id]);
                $wallet = UserWallet::whereKey($wallet->getKey())->lockForUpdate()->first();

                $before = round((float) $wallet->naara_credits, self::SCALE);
                $after = round($type === 'spend' ? $before - $credits : $before + $credits, self::SCALE);
                if ($after < 0) {
                    throw new InsufficientCreditsException($user->id, $credits, $before);
                }

                // Keep the withdrawable bucket coherent:
                //  - earning a referral reward grows it,
                //  - a withdrawal HOLD shrinks it (guarded ≤ withdrawable),
                //  - any other spend eats non-withdrawable first, so withdrawable
                //    is simply clamped to the new balance.
                $wBefore = round((float) $wallet->withdrawable_credits, self::SCALE);
                if ($type === 'earn' && $withdrawable) {
                    $wallet->withdrawable_credits = round($wBefore + $credits, self::SCALE);
                } elseif ($type === 'spend' && $fromWithdrawable) {
                    if ($credits > $wBefore) {
                        throw new InsufficientCreditsException($user->id, $credits, $wBefore);
                    }
                    $wallet->withdrawable_credits = round($wBefore - $credits, self::SCALE);
                } elseif ($type === 'spend') {
                    $wallet->withdrawable_credits = min($wBefore, $after);
                }

                $wallet->naara_credits = $after;
                $wallet->save();

                return CreditLedger::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'source' => $source,
                    'withdrawable' => $type === 'earn' ? $withdrawable : false,
                    'amount' => $credits,
                    'balance_after' => $after,
                    'reference' => $reference ?? (string) Str::uuid(),
                    'description' => $description,
                ]);
            });
        });
    }

    // ---- earn helpers -------------------------------------------------------

    /** Daily check-in. Returns the credits granted, or 0 if still on cooldown. */
    public function checkIn(User $user): float
    {
        if (! CreditSettings::enabled()) {
            return 0.0;
        }
        $amount = (float) CreditSettings::get('checkin_daily', 5);
        if ($amount <= 0) {
            return 0.0;
        }

        $wallet = $user->wallet ?? UserWallet::create(['user_id' => $user->id]);
        $cooldown = (int) CreditSettings::get('checkin_cooldown_hours', 24);
        if ($wallet->last_checkin_at && $wallet->last_checkin_at->diffInHours(now()) < $cooldown) {
            return 0.0; // still on cooldown
        }

        $this->earn($user, $amount, 'checkin', 'checkin:'.$user->id.':'.now()->format('Y-m-d-H'), 'Daily check-in reward');
        $wallet->forceFill(['last_checkin_at' => now()])->save();

        return $amount;
    }

    /** Whether the user may check in right now. */
    public function canCheckIn(User $user): bool
    {
        if (! CreditSettings::enabled() || (float) CreditSettings::get('checkin_daily', 5) <= 0) {
            return false;
        }
        $last = $user->wallet?->last_checkin_at;
        $cooldown = (int) CreditSettings::get('checkin_cooldown_hours', 24);

        return $last === null || $last->diffInHours(now()) >= $cooldown;
    }

    /**
     * How much of a purchase the user's credits may cover — MARGIN-CAPPED so the
     * platform always wins. The redeemable USD is the smallest of:
     *   - what keeps the CHARGED money at or above cost + minimum profit
     *     (so the sale never dips below wholesale + profit),
     *   - the admin's max-redeem-% of the retail, and
     *   - the USD value of the user's credit balance.
     *
     * @param  string  $product  'esim' or 'sms' — selects the absolute min-profit
     *                           floor (CouponEngine::price() uses the same split;
     *                           the number lane's retail is cents-level, so the
     *                           eSIM $0.50 floor would zero out every quote).
     * @return array{usd: float, credits: float}
     */
    public function quoteRedemption(User $user, float $retail, float $cost, ?float $adminMargin = null, ?float $merchantMargin = null, string $product = 'esim'): array
    {
        if (! CreditSettings::enabled()) {
            return ['usd' => 0.0, 'credits' => 0.0];
        }

        // Margin-safe floor (discount-floor blueprint §3) — the SAME floor
        // CouponEngine uses, so coupon + credits combined can never cross it.
        $minProfit = $product === 'esim'
            ? (float) Setting::getValue('pricing.minimum_profit_usd', 0.50)
            : (float) Setting::getValue('pricing.sms_min_profit', 0.01);
        $floor = app(DiscountMarginGuard::class)->floor(
            $cost,
            $adminMargin ?? ($retail - $cost),
            $merchantMargin,
            $minProfit,
        );
        $maxByFloor = max(0.0, round($retail - $floor, 4));
        $maxByPct = round($retail * (int) CreditSettings::get('max_redeem_pct', 50) / 100, 4);
        $maxByBalance = CreditSettings::creditsToUsd($this->balance($user));

        $usd = round(max(0.0, min($maxByFloor, $maxByPct, $maxByBalance)), 2);
        // Never spend more credits than the USD represents at the current rate.
        $credits = min($this->balance($user), CreditSettings::usdToCredits($usd));

        return ['usd' => $usd, 'credits' => round($credits, 2)];
    }

    /** One-time bonuses (signup, first purchase) — idempotent by their reference. */
    public function grantOnce(User $user, float $credits, string $source, string $description): void
    {
        if (! CreditSettings::enabled() || $credits <= 0) {
            return;
        }
        try {
            $this->earn($user, $credits, $source, "{$source}:{$user->id}", $description);
        } catch (\Throwable) {
            // best-effort — a loyalty bonus must never break the flow that triggered it
        }
    }
}
