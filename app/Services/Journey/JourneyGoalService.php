<?php

namespace App\Services\Journey;

use App\Models\CreditLedger;
use App\Models\EsimOrder;
use App\Models\JourneyGoal;
use App\Models\JourneyGoalClaim;
use App\Models\MerchantClient;
use App\Models\MerchantInvoice;
use App\Models\Referral;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\JourneyGoalUnlockedNotification;
use App\Services\Credits\CreditService;
use App\Support\CreditSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The "My Journey" goals engine (loyalty module expansion). Every metric here
 * is computed live from real, existing tables — never invented, never a
 * cached/estimated figure. A goal is only ever paid out ONCE per (user, goal,
 * period): journey_goal_claims' unique index is the hard guard, on top of
 * CreditService::earn()'s own reference-based idempotency.
 *
 * Adding a metric later is a single new `case` in metricValue() plus a label
 * in METRICS — nothing else in this class or the admin UI needs to change.
 */
class JourneyGoalService
{
    public const METRICS = [
        'esim_purchases' => 'eSIM purchases',
        'number_purchases' => 'Number purchases',
        'countries_reached' => 'Countries reached (eSIM)',
        'total_spend_usd' => 'Total spend (USD)',
        'referrals_made' => 'Referrals made',
        'credits_earned_lifetime' => 'NaaraCredits earned',
        'checkin_streak_days' => 'Daily check-in streak',
        'merchant_clients_connected' => 'Merchant: clients connected',
        'merchant_invoices_paid' => 'Merchant: invoices paid',
    ];

    public function __construct(private CreditService $credits) {}

    /** Goals visible to this user at all (audience-scoped, active, within any campaign window). */
    public function goalsFor(User $user): Collection
    {
        $isMerchant = $user->merchantAccount !== null && $user->merchantAccount->isActive();
        $isV2 = $isMerchant && $user->merchantAccount->isV2();

        return JourneyGoal::where('is_active', true)
            ->where(function (Builder $q) use ($isMerchant, $isV2) {
                $q->where('audience', JourneyGoal::AUDIENCE_ALL);
                if ($isMerchant) {
                    $q->orWhere('audience', JourneyGoal::AUDIENCE_MERCHANT);
                }
                if ($isV2) {
                    $q->orWhere('audience', JourneyGoal::AUDIENCE_MERCHANT_V2);
                }
            })
            ->where(function (Builder $q) {
                // A campaign goal is only "visible" inside its own window.
                $q->where('period_type', '!=', JourneyGoal::PERIOD_CAMPAIGN)
                    ->orWhere(function (Builder $c) {
                        $c->where('period_type', JourneyGoal::PERIOD_CAMPAIGN)
                            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
                    });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{current: float, target: float, period_key: string, claimed: bool, claimed_at: ?Carbon}
     */
    public function progress(User $user, JourneyGoal $goal): array
    {
        [$start, $end, $periodKey] = $this->periodWindow($goal);
        $claim = JourneyGoalClaim::where('journey_goal_id', $goal->id)
            ->where('user_id', $user->id)->where('period_key', $periodKey)->first();

        return [
            'current' => $claim ? (float) $claim->achieved_value : $this->metricValue($goal->metric, $user, $start, $end),
            'target' => (float) $goal->target,
            'period_key' => $periodKey,
            'claimed' => $claim !== null,
            'claimed_at' => $claim?->created_at,
        ];
    }

    /**
     * Evaluate every visible goal for this user and grant NaaraCredits for any
     * newly-reached one. Safe to call as often as needed (page load, or right
     * after an action that could have just completed a goal) — already-claimed
     * periods are cheap no-ops.
     *
     * @return JourneyGoalClaim[] newly granted claims (for a "goal unlocked!" toast)
     */
    public function evaluate(User $user): array
    {
        if (! CreditSettings::enabled()) {
            return [];
        }

        $granted = [];
        foreach ($this->goalsFor($user) as $goal) {
            [$start, $end, $periodKey] = $this->periodWindow($goal);
            $alreadyClaimed = JourneyGoalClaim::where('journey_goal_id', $goal->id)
                ->where('user_id', $user->id)->where('period_key', $periodKey)->exists();
            if ($alreadyClaimed) {
                continue;
            }

            $current = $this->metricValue($goal->metric, $user, $start, $end);
            if ($current < (float) $goal->target) {
                continue;
            }

            $claim = $this->claim($user, $goal, $periodKey, $current);
            if ($claim !== null) {
                $granted[] = $claim;
            }
        }

        return $granted;
    }

    private function claim(User $user, JourneyGoal $goal, string $periodKey, float $achievedValue): ?JourneyGoalClaim
    {
        $reference = "journey_goal:{$goal->id}:{$user->id}:{$periodKey}";

        // The unique index on (goal, user, period) is the real guard — a
        // duplicate insert here just means another process already claimed it.
        try {
            $claim = JourneyGoalClaim::create([
                'journey_goal_id' => $goal->id,
                'user_id' => $user->id,
                'period_key' => $periodKey,
                'achieved_value' => $achievedValue,
                'credits_granted' => $goal->reward_credits,
                'reference' => $reference,
            ]);
        } catch (QueryException) {
            return null;
        }

        $this->credits->earn(
            $user, (float) $goal->reward_credits, 'journey_goal', $reference,
            "Journey goal reached: {$goal->title}",
        );

        try {
            $user->notify(new JourneyGoalUnlockedNotification($goal->title, (float) $goal->reward_credits));
        } catch (\Throwable) {
            // Best-effort — a bell notification must never break the grant.
        }

        return $claim;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon, 2: string} */
    private function periodWindow(JourneyGoal $goal): array
    {
        return match ($goal->period_type) {
            JourneyGoal::PERIOD_MONTHLY => [now()->startOfMonth(), now()->endOfMonth(), now()->format('Y-m')],
            JourneyGoal::PERIOD_QUARTERLY => [now()->startOfQuarter(), now()->endOfQuarter(), now()->format('Y').'-Q'.now()->quarter],
            JourneyGoal::PERIOD_YEARLY => [now()->startOfYear(), now()->endOfYear(), now()->format('Y')],
            JourneyGoal::PERIOD_CAMPAIGN => [$goal->starts_at, $goal->ends_at, 'campaign'],
            default => [null, null, 'lifetime'],
        };
    }

    private function metricValue(string $metric, User $user, ?Carbon $start, ?Carbon $end): float
    {
        return match ($metric) {
            'esim_purchases' => (float) $this->period(EsimOrder::where('user_id', $user->id), $start, $end)->count(),
            'number_purchases' => (float) $this->period(SmsOrder::where('user_id', $user->id), $start, $end)->count(),
            'countries_reached' => (float) $this->countriesReached($user, $start, $end),
            'total_spend_usd' => $this->totalSpend($user, $start, $end),
            'referrals_made' => (float) $this->period(Referral::where('referrer_id', $user->id), $start, $end)->count(),
            'credits_earned_lifetime' => (float) $this->period(
                CreditLedger::where('user_id', $user->id)->where('type', 'earn'), $start, $end
            )->sum('amount'),
            'checkin_streak_days' => (float) $this->checkinStreak($user),
            'merchant_clients_connected' => $this->merchantValue(
                $user, fn ($merchantId) => $this->period(MerchantClient::where('merchant_id', $merchantId), $start, $end)->count()
            ),
            'merchant_invoices_paid' => $this->merchantValue(
                $user, fn ($merchantId) => MerchantInvoice::where('merchant_id', $merchantId)
                    ->where('status', MerchantInvoice::PAID)
                    ->when($start, fn ($q) => $q->whereBetween('paid_at', [$start, $end]))
                    ->count()
            ),
            default => 0.0,
        };
    }

    private function period(Builder $query, ?Carbon $start, ?Carbon $end, string $column = 'created_at'): Builder
    {
        return $start ? $query->whereBetween($column, [$start, $end]) : $query;
    }

    private function countriesReached(User $user, ?Carbon $start, ?Carbon $end): int
    {
        return $this->period(EsimOrder::where('user_id', $user->id), $start, $end)
            ->with('plan:id,countries')->get()
            ->map(fn (EsimOrder $o) => $o->plan?->countries[0] ?? null)
            ->filter()->unique()->count();
    }

    private function totalSpend(User $user, ?Carbon $start, ?Carbon $end): float
    {
        $base = WalletTransaction::where('user_id', $user->id)->where('currency', 'USD');
        $debit = (float) $this->period((clone $base)->where('type', 'debit'), $start, $end)->sum('amount');
        $refund = (float) $this->period((clone $base)->where('type', 'refund'), $start, $end)->sum('amount');

        return max(0.0, round($debit - $refund, 2));
    }

    private function merchantValue(User $user, \Closure $fn): float
    {
        $merchant = $user->merchantAccount;

        return $merchant !== null ? (float) $fn($merchant->id) : 0.0;
    }

    /** Consecutive-day streak ending today or yesterday, from real check-in ledger rows. */
    public function checkinStreak(User $user): int
    {
        $dates = CreditLedger::where('user_id', $user->id)->where('source', 'checkin')
            ->pluck('created_at')
            ->map(fn ($d) => $d->copy()->startOfDay())
            ->unique(fn ($d) => $d->toDateString())
            ->sortByDesc(fn ($d) => $d->toDateString())
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $expected = Carbon::today();
        if ($dates->first()->lt($expected->copy()->subDay())) {
            return 0;
        }
        if ($dates->first()->equalTo($expected->copy()->subDay())) {
            $expected = $expected->subDay();
        }

        $streak = 0;
        foreach ($dates as $date) {
            if ($date->equalTo($expected)) {
                $streak++;
                $expected = $expected->copy()->subDay();
            } elseif ($date->lt($expected)) {
                break;
            }
        }

        return $streak;
    }
}
