<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use App\Jobs\AlertAdminJob;
use App\Models\EsimPlan;
use App\Models\OrderLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Routing\CandidateOrdering;
use App\Services\Routing\CircuitBreaker;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProviderRouter — profit-aware eSIM failover (blueprint Section 6).
 *
 * Tries providers in order (eSIM Go → Airalo → Quibity). The governing rule:
 * a fallback that destroys margin is worse than a failed order. So it SKIPS
 * any provider whose equivalent-plan cost would leave less than the minimum
 * profit versus what the user already paid, and if none can fulfil profitably
 * it refunds the wallet and alerts — never fulfils below cost + min profit.
 *
 * Assumes the user's wallet has already been debited (checkout debits first,
 * then calls this). Pass the ACTUAL amount charged as `$charged` — after any
 * coupon or NaaraCredit redemption it is less than list price, and the margin
 * guard, profit log and refund must all use the real amount, not `final_retail`.
 * On total failure it refunds exactly `$charged`.
 */
class ProviderRouter
{
    /**
     * The data-only failover lane (Naara Data). Zendit is a backup here too — it
     * also sells plain data eSIMs alongside its Full-eSIM offers.
     *
     * @var list<string>
     */
    protected array $chain = ['esimgo', 'airalo', 'quibity', 'zendit'];

    /**
     * The Full-eSIM failover lane (Naara Connect: calls + data). Four
     * interchangeable voice+data providers; a voice purchase fails over WITHIN
     * this lane only — never down to a data-only provider (blueprint lane rule).
     *
     * @var list<string>
     */
    protected array $voiceChain = ['zendit', 'oneglobal', 'montymobile', 'gigs'];

    public function __construct(
        private readonly WalletService $wallet,
        private readonly CircuitBreaker $breaker = new CircuitBreaker,
        private readonly CandidateOrdering $ordering = new CandidateOrdering,
    ) {}

    /**
     * $refundTo/$refundMeta (Prompt 11 §3, shared-plan purchases): when a
     * purchase is paid from someone ELSE's wallet (a shared plan), $user
     * stays the actual buyer for OrderLog attribution, but a failure must
     * refund the wallet that was actually charged, not the buyer's own —
     * both default to preserving the exact prior behaviour (refund $user)
     * for every existing caller.
     */
    public function orderPlan(string $naaraPlanId, User $user, string $currency = 'USD', ?float $charged = null, ?User $refundTo = null, array $refundMeta = []): EsimOrderResult
    {
        $plan = EsimPlan::findOrFail($naaraPlanId);
        // Default to list price only when the caller doesn't pass the real
        // charged amount (keeps older callers/tests working).
        $charged = $charged ?? (float) $plan->final_retail_usd;
        $refundTo ??= $user;

        try {
            return $this->fulfil($plan, $charged, $user);
        } catch (EsimProviderException $e) {
            // Never charge without delivering — refund the caller's wallet + alert.
            $this->wallet->refund($refundTo, $charged, $currency, [
                'description' => 'eSIM order failed — all providers unavailable or unprofitable',
                'reference' => "esim-refund:{$plan->id}:{$user->id}:".now()->timestamp,
                ...$refundMeta,
            ]);

            AlertAdminJob::dispatch(
                code: 'all_esim_providers_failed',
                message: "No eSIM provider could fulfil plan {$plan->id} for user {$user->id}; wallet refunded {$charged} {$currency}.",
                context: ['plan_id' => $plan->id, 'user_id' => $user->id, 'charged' => $charged, 'currency' => $currency],
            );

            throw new EsimProviderException('Order could not be fulfilled. Wallet refunded.', previous: $e);
        }
    }

    /**
     * Fulfil a plan at the first profitable provider in the failover chain and
     * return the result — WITHOUT touching any wallet. Throws
     * EsimProviderException if no provider can deliver at cost + minimum profit.
     *
     * The CALLER owns the money: the storefront (orderPlan) refunds the user's
     * wallet on failure; the Developer API refunds its prepaid API wallet. This
     * shared loop never assumes whose money paid, so the margin guard, provider
     * fallback, and profit log stay in ONE place. `$forLog` only attributes the
     * OrderLog row.
     */
    public function fulfil(EsimPlan $plan, float $charged, User $forLog): EsimOrderResult
    {
        $minProfit = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $errors = [];

        // BUILD-15 §4: order the existing lane by the live registry (open circuits
        // excluded, fastest/most-reliable first) — reorder only; the live purchase
        // call below is unchanged. Falls back to the static chain if unavailable.
        $liveAttempts = 0;
        $openSkips = 0;
        foreach ($this->ordering->order($this->chainFor($plan), 'esim') as $provider) {
            $result = $this->attemptProvider($plan, $provider, $charged, $forLog, $minProfit, $errors, false, $liveAttempts, $openSkips);
            if ($result !== null) {
                return $result;
            }
        }

        // BUILD-19 §5 — total-outage last resort: if nothing got a live call and
        // the only thing stopping it was open circuits, try the least-recently-
        // failed provider anyway rather than hard-failing the customer.
        if ($liveAttempts === 0 && $openSkips > 0) {
            $lastResort = $this->breaker->lastResortAmong($this->chainFor($plan));
            if ($lastResort !== null) {
                AlertAdminJob::dispatch(
                    code: 'total-outage-esim',
                    message: "Total outage: all eSIM providers unavailable — attempting {$lastResort} as a last resort for plan {$plan->id}.",
                    context: ['plan' => $plan->id, 'last_resort' => $lastResort],
                    severity: 'critical',
                );
                $result = $this->attemptProvider($plan, $lastResort, $charged, $forLog, $minProfit, $errors, true, $liveAttempts, $openSkips);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        // Existing all-failed handling (predates NCI) — unchanged.
        throw new EsimProviderException('Order could not be fulfilled — no provider available or profitable.');
    }

    /**
     * One provider attempt: the plan/margin guards, the circuit pre-check (unless
     * $bypassCircuit for the §5 last resort), the live purchase, the OrderLog, and
     * the outcome record. Returns the success result or null to try the next.
     */
    private function attemptProvider(EsimPlan $plan, string $provider, float $charged, User $forLog, float $minProfit, array &$errors, bool $bypassCircuit, int &$liveAttempts, int &$openSkips): ?EsimOrderResult
    {
        $pp = $this->findEquivalentPlan($plan, $provider);
        if ($pp === null) {
            return null; // provider has no equivalent plan
        }

        $cost = (float) $pp->cost_price_usd;
        if ($charged < $cost + $minProfit) {
            Log::warning("ProviderRouter: skipping {$provider} for plan {$plan->id} — cost {$cost} too close to charged {$charged}.");
            $errors[$provider] = 'skipped: unprofitable';

            return null;
        }

        // BUILD-15 §1: skip a provider whose circuit is open (business skips above
        // are NOT circuit failures). Bypassed only for the §5 last-resort attempt.
        if (! $bypassCircuit && ! $this->breaker->allows($provider)) {
            $errors[$provider] = 'circuit_open';
            $openSkips++;

            return null;
        }

        $liveAttempts++;
        try {
            $result = app("esim.{$provider}")->orderBundle($pp->provider_plan_id);

            OrderLog::create([
                'user_id' => $forLog->id,
                'naarasim_plan_id' => $plan->id,
                'provider' => $provider,
                'provider_cost' => $cost,
                'charged_to_user' => $charged,
                'profit' => round($charged - $cost, 4),
                'profit_pct' => $cost > 0 ? round(($charged - $cost) / $cost * 100, 3) : null,
                'result' => 'success',
            ]);

            $this->breaker->record($provider, 'esim', 'success', null, 'PLAN-'.$plan->id);

            return EsimOrderResult::success($provider, $result, $cost, $charged, $pp->provider_plan_id);
        } catch (Throwable $e) {
            $this->breaker->record($provider, 'esim', 'failure', $this->errorCode($e), 'PLAN-'.$plan->id);
            $errors[$provider] = $e->getMessage();

            return null;
        }
    }

    /** A compact error class/code for the outcome log (NCI raw material, BUILD-16). */
    private function errorCode(Throwable $e): string
    {
        return $e->getCode() ? (string) $e->getCode() : class_basename($e);
    }

    /**
     * The failover lane for a plan. Voice eSIMs (Naara Connect) are NOT
     * interchangeable with data-only providers — a data-only fallback would
     * deliver the wrong product for a voice purchase (blueprint lane rule). So a
     * voice plan fails over within the Full-eSIM lane (Zendit / 1GLOBAL / Monty
     * Mobile / Gigs), while a data plan uses the data failover chain.
     *
     * @return list<string>
     */
    protected function chainFor(EsimPlan $plan): array
    {
        return $plan->has_voice ? $this->voiceChain : $this->chain;
    }

    /**
     * Find the cheapest active plan from $provider that covers at least the
     * same countries, data, and validity as $plan — so a fallback never
     * downgrades what the user paid for. Cheapest cost first. Voice capability
     * must match exactly: a data plan never matches a voice bundle (which would
     * overpay) and a voice plan never matches a data-only bundle (wrong product).
     */
    public function findEquivalentPlan(EsimPlan $plan, string $provider): ?EsimPlan
    {
        $candidates = EsimPlan::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->where('has_voice', (bool) $plan->has_voice)
            ->orderBy('cost_price_usd')
            ->get();

        foreach ($candidates as $candidate) {
            if (! $this->dataCovers($plan->data_mb, $candidate->data_mb)) {
                continue;
            }
            if (! $this->validityCovers($plan->validity_days, $candidate->validity_days)) {
                continue;
            }
            if (! $this->countriesCover($plan->countries, $candidate->countries)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /** null data_mb means unlimited. Unlimited is only covered by unlimited. */
    private function dataCovers(?int $need, ?int $have): bool
    {
        if ($need === null) {
            return $have === null;
        }

        return $have === null || $have >= $need;
    }

    private function validityCovers(?int $need, ?int $have): bool
    {
        if ($need === null) {
            return true;
        }

        return $have === null || $have >= $need;
    }

    /**
     * @param  array<int, string>|null  $need
     * @param  array<int, string>|null  $have
     */
    private function countriesCover(?array $need, ?array $have): bool
    {
        $need = $need ?? [];
        if ($need === []) {
            return true;
        }

        return empty(array_diff($need, $have ?? []));
    }
}
