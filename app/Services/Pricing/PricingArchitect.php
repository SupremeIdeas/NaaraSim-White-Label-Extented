<?php

namespace App\Services\Pricing;

use App\Models\EsimPlan;
use App\Models\PricingProposal;
use App\Models\PricingProposalLine;
use App\Models\Setting;
use App\Models\User;
use App\Services\AI\AnthropicClient;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * The AI Pricing Architect — "Plan Price with Claude" (owner-requested layer).
 *
 * Claude analyses live provider costs and current retail, then PROPOSES optimal
 * retail prices plus safe promo caps. The unbreakable contract:
 *
 *   1. Claude only PROPOSES. It never writes a live price.
 *   2. MarginGuard is the law, not the model. Every proposed price is re-clamped
 *      to at least cost + minimum profit (and the Airalo minimum) BEFORE it is
 *      stored, shown, or applied — so a hallucinated or manipulated proposal can
 *      never push a sale below wholesale + profit. The model can only ever make
 *      the platform MORE profit than the floor, never less.
 *   3. The admin approves. Applying re-runs PricingEngine (guards again).
 *
 * The feature lights up only when the Anthropic key is active; without it the
 * admin keeps the manual margin controls and the always-on margin monitor below.
 */
class PricingArchitect
{
    public function __construct(
        private readonly AnthropicClient $ai,
        private readonly PricingEngine $engine,
    ) {
    }

    public function enabled(): bool
    {
        return $this->ai->enabled();
    }

    /** The absolute per-sale floor: cost + admin minimum profit (+ Airalo min). */
    public function floorFor(EsimPlan $plan): float
    {
        $cost = (float) $plan->cost_price_usd;
        $minProfit = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $floor = round($cost + $minProfit, 4);

        if ($plan->provider === 'airalo' && $plan->airalo_min_price !== null) {
            $floor = max($floor, (float) $plan->airalo_min_price);
        }

        return $floor;
    }

    /**
     * Admin-side snapshot of the current pricing landscape for every active plan:
     * cost, current retail, current profit and the guard floor. This is what
     * Claude reasons over. Cost is admin-only and never leaves this gate.
     *
     * @return array<int, array<string, mixed>>
     */
    public function snapshot(): array
    {
        return EsimPlan::where('is_active', true)->orderBy('name')->get()->map(function (EsimPlan $p) {
            $cost = (float) $p->cost_price_usd;
            $retail = (float) $p->final_retail_usd;

            return [
                'plan_id' => $p->id,
                'name' => $p->name,
                'type' => $p->type ?? 'data',
                'provider' => $p->provider,
                'countries' => $p->countries ?? [],
                'data_mb' => $p->data_mb,
                'validity_days' => $p->validity_days,
                'cost_usd' => round($cost, 4),
                'current_retail_usd' => round($retail, 4),
                'current_profit_usd' => round($retail - $cost, 4),
                'floor_usd' => $this->floorFor($p),
            ];
        })->all();
    }

    /**
     * The always-on margin monitor (no API call). Gives the admin a verdict on
     * how healthy each plan's margin is right now — flags any plan running thin
     * against its floor. Cheap enough to run on every page load.
     *
     * @return array{healthy: int, thin: int, at_floor: int, flags: array<int, array<string, mixed>>}
     */
    public function monitor(): array
    {
        $thinPct = (float) Setting::getValue('pricing.architect.thin_margin_pct', 15);
        $healthy = $thin = $atFloor = 0;
        $flags = [];

        foreach ($this->snapshot() as $row) {
            $cost = (float) $row['cost_usd'];
            $retail = (float) $row['current_retail_usd'];
            $floor = (float) $row['floor_usd'];
            $marginPct = $cost > 0 ? round(($retail - $cost) / $cost * 100, 1) : 100.0;

            if ($retail <= $floor + 0.0001) {
                $atFloor++;
                $flags[] = $row + ['verdict' => 'at_floor', 'margin_pct' => $marginPct];
            } elseif ($marginPct < $thinPct) {
                $thin++;
                $flags[] = $row + ['verdict' => 'thin', 'margin_pct' => $marginPct];
            } else {
                $healthy++;
            }
        }

        return ['healthy' => $healthy, 'thin' => $thin, 'at_floor' => $atFloor, 'flags' => $flags];
    }

    /**
     * Always-on market-competitiveness benchmark (no API call, owner request).
     * Estimates a typical market price band for each data plan from a TRANSPARENT,
     * admin-tunable model — a per-GB + per-day + base formula (NOT scraped
     * competitor data) — and rates our retail against it: competitive (inside the
     * band), keen (below it — great for users), or premium (above it — may cost
     * conversions). Gives the admin an at-a-glance verdict on whether prices are
     * sensible for the market, and enriches Claude's proposal when the key is on.
     *
     * @return array{rows: array<int, array<string, mixed>>, competitive: int, keen: int, premium: int, verdict: string}
     */
    public function marketBenchmark(): array
    {
        $perGb = (float) Setting::getValue('pricing.market.per_gb_usd', 2.50);
        $perDay = (float) Setting::getValue('pricing.market.per_day_usd', 0.08);
        $base = (float) Setting::getValue('pricing.market.base_usd', 0.99);
        $bandPct = (float) Setting::getValue('pricing.market.band_pct', 15);

        $rows = [];
        $competitive = $keen = $premium = 0;

        foreach ($this->snapshot() as $row) {
            if (($row['type'] ?? 'data') !== 'data') {
                continue; // the market model is for data plans
            }
            $gb = ($row['data_mb'] ?? 0) > 0 ? $row['data_mb'] / 1024 : 1;
            $days = max(1, (int) ($row['validity_days'] ?? 30));

            $mid = round($perGb * $gb + $perDay * $days + $base, 2);
            $low = round($mid * (1 - $bandPct / 100), 2);
            $high = round($mid * (1 + $bandPct / 100), 2);
            $retail = (float) $row['current_retail_usd'];

            $verdict = $retail < $low ? 'keen' : ($retail > $high ? 'premium' : 'competitive');
            ${$verdict}++;

            $rows[] = $row + [
                'market_low' => $low,
                'market_mid' => $mid,
                'market_high' => $high,
                'position' => $verdict, // keen | competitive | premium
                'delta_pct' => $mid > 0 ? round(($retail - $mid) / $mid * 100, 1) : 0.0,
            ];
        }

        $total = max(1, $competitive + $keen + $premium);
        $verdict = match (true) {
            $premium / $total > 0.4 => 'Several plans sit above the typical market band — consider trimming to lift conversion.',
            $keen / $total > 0.5 => 'Prices are keen (below market) — great for growth; keep an eye on the margin monitor.',
            default => 'Pricing looks competitive — most plans sit inside the typical market band.',
        };

        return compact('rows', 'competitive', 'keen', 'premium', 'verdict');
    }

    /**
     * Ask Claude to analyse the snapshot and propose optimal retail prices, then
     * build a PENDING proposal with every price re-clamped by the guard. Never
     * applies anything. Throws (RuntimeException) if the key is off or the call
     * fails — the caller surfaces a friendly message.
     */
    public function propose(?User $admin = null): PricingProposal
    {
        $snapshot = $this->snapshot();
        $currency = (string) Setting::getValue('pricing.currency_display', 'USD');

        $system = $this->systemPrompt();
        $payload = [
            'currency' => $currency,
            'minimum_profit_usd' => (float) Setting::getValue('pricing.minimum_profit_usd', 0.50),
            'plans' => $snapshot,
        ];
        $userMsg = "Here is the current NaaraSim pricing snapshot (admin-only data). "
            ."Propose the best RETAIL price for each plan and safe promo caps, as strict JSON.\n\n"
            .json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $result = $this->ai->completeJson($system, [
            ['role' => 'user', 'content' => $userMsg],
        ], maxTokens: 4096);

        return DB::transaction(function () use ($result, $snapshot, $admin) {
            $proposal = PricingProposal::create([
                'status' => 'pending',
                'model_used' => $this->ai->model(),
                'summary' => (string) ($result['summary'] ?? 'Pricing proposal generated.'),
                'meta' => [
                    'recommended_max_discount_pct' => $this->clampPct($result['recommended_max_discount_pct'] ?? null),
                    'recommended_credit_redeem_pct' => $this->clampPct($result['recommended_credit_redeem_pct'] ?? null),
                    'market_notes' => (string) ($result['market_notes'] ?? ''),
                ],
                'created_by' => $admin?->id,
            ]);

            // Index Claude's per-plan proposals by plan_id.
            $byPlan = [];
            foreach ((array) ($result['lines'] ?? []) as $line) {
                if (isset($line['plan_id'])) {
                    $byPlan[(int) $line['plan_id']] = $line;
                }
            }

            foreach ($snapshot as $row) {
                $planId = (int) $row['plan_id'];
                $plan = EsimPlan::find($planId);
                if (! $plan) {
                    continue;
                }

                $floor = (float) $row['floor_usd'];
                $cost = (float) $row['cost_usd'];
                $suggested = isset($byPlan[$planId]['proposed_retail_usd'])
                    ? (float) $byPlan[$planId]['proposed_retail_usd']
                    : (float) $row['current_retail_usd'];

                // THE LAW: never below the floor. The guard only corrects upward.
                $proposed = round(max($suggested, $floor), 4);
                $guardApplied = $proposed > round($suggested, 4);

                $profit = round($proposed - $cost, 4);
                $marginPct = $cost > 0 ? round($profit / $cost * 100, 2) : 0.0;

                PricingProposalLine::create([
                    'proposal_id' => $proposal->id,
                    'item_type' => 'esim',
                    'plan_id' => $planId,
                    'name' => $row['name'],
                    'cost_usd' => $cost,
                    'current_retail_usd' => $row['current_retail_usd'],
                    'proposed_retail_usd' => $proposed,
                    'floor_usd' => $floor,
                    'projected_profit_usd' => $profit,
                    'projected_margin_pct' => $marginPct,
                    'guard_applied' => $guardApplied,
                    'accepted' => true,
                    'rationale' => (string) ($byPlan[$planId]['rationale'] ?? 'Held at a competitive, profitable level.'),
                ]);
            }

            Auditor::log('pricing.architect_proposed', PricingProposal::class, $proposal->id, [
                'lines' => $proposal->lines()->count(),
                'model' => $proposal->model_used,
            ]);

            return $proposal->fresh('lines');
        });
    }

    /**
     * Apply an approved proposal: write each accepted line's proposed retail as
     * the plan's manual price, then recompute through PricingEngine so the guards
     * fire ONE more time. Atomic + audited. A price can never land below floor.
     */
    public function apply(PricingProposal $proposal, User $admin): int
    {
        if (! $proposal->isPending()) {
            return 0;
        }

        return DB::transaction(function () use ($proposal, $admin) {
            $applied = 0;

            foreach ($proposal->lines()->where('accepted', true)->get() as $line) {
                $plan = $line->plan_id ? EsimPlan::find($line->plan_id) : null;
                if (! $plan) {
                    continue;
                }

                // Set the manual retail, then let the engine re-clamp (guards run
                // again inside recompute()/calculateRetail()).
                $plan->manual_retail_usd = max((float) $line->proposed_retail_usd, $this->floorFor($plan));
                $plan->save();
                $this->engine->recompute($plan);
                $applied++;
            }

            $proposal->update([
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            // Apply the recommended promo caps too (each already margin-clamped
            // at redemption time by CouponEngine / CreditService, so this is a
            // guidance ceiling, never a floor breach).
            $meta = $proposal->meta ?? [];
            if (! empty($meta['recommended_credit_redeem_pct'])) {
                Setting::setValue('credits.max_redeem_pct', (int) $meta['recommended_credit_redeem_pct'], 'credits');
            }
            if (! empty($meta['recommended_max_discount_pct'])) {
                Setting::setValue('pricing.architect.max_discount_pct', (int) $meta['recommended_max_discount_pct'], 'pricing');
            }

            Auditor::log('pricing.architect_approved', PricingProposal::class, $proposal->id, [
                'applied' => $applied,
                'approved_by' => $admin->id,
            ]);

            return $applied;
        });
    }

    public function reject(PricingProposal $proposal, User $admin): void
    {
        if ($proposal->isPending()) {
            $proposal->update(['status' => 'rejected', 'approved_by' => $admin->id, 'approved_at' => now()]);
            Auditor::log('pricing.architect_rejected', PricingProposal::class, $proposal->id, []);
        }
    }

    private function clampPct(mixed $value): ?int
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return max(0, min(90, (int) round((float) $value)));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are the pricing strategist for NaaraSim, a Pan-African travel-connectivity
        platform selling eSIM data plans and virtual/verification phone numbers across
        190+ countries. You are given ADMIN-ONLY data: each plan's wholesale cost, its
        current retail price, and the guard floor (cost + minimum profit).

        Your job: propose the RETAIL price that maximises the owner's profit while
        staying competitive with data-only rivals (Airalo, Holafly, Nomad) — NaaraSim
        uniquely sells BOTH data and numbers, so it can price with confidence.

        NON-NEGOTIABLE RULES:
        - NEVER propose a retail price below a plan's "floor_usd". If in doubt, price
          above the floor. A price at or below wholesale is unacceptable.
        - Keep prices realistic and round to sensible price points (e.g. 4.99, 9.50).
        - Prefer small, defensible margins on cheap plans and healthier margins on
          premium/regional/global plans.
        - Also recommend a safe platform-wide maximum promotional discount percentage
          and a maximum NaaraCredits redemption percentage that still leave every sale
          above its floor.

        Respond with STRICT JSON only, no prose outside the JSON, in exactly this shape:
        {
          "summary": "one short paragraph verdict for the admin",
          "market_notes": "brief competitive context",
          "recommended_max_discount_pct": <integer 0-90>,
          "recommended_credit_redeem_pct": <integer 0-90>,
          "lines": [
            { "plan_id": <int>, "proposed_retail_usd": <number>, "rationale": "short reason" }
          ]
        }
        PROMPT;
    }
}
