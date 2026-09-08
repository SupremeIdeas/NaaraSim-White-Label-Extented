<?php

namespace App\Jobs;

use App\Models\EsimOrder;
use App\Models\EsimUsageSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Connectivity Analytics blueprint Part A §2.3 — the snapshot capture pipeline.
 * Resolves the order's own provider adapter (never ProviderRouter, which picks
 * a provider for a NEW purchase — this reads back the one that already
 * fulfilled this order) and writes one esim_usage_snapshots row.
 *
 * Provider response shapes are NOT uniform: EsimAccessService and UbigiService
 * already normalize their own getUsage() to ['remaining_mb' => .., 'used_mb' =>
 * ..]; every other adapter (eSIM Go, Airalo, Zendit, 1GLOBAL, Monty Mobile,
 * Gigs, Quibity) returns the provider's raw, undocumented-here JSON. This job
 * only trusts the two confirmed-normalized keys with full confidence; anything
 * else goes through a best-effort common-alias fallback that MUST be verified
 * against each provider's live sandbox response before this is relied on in
 * production (same "first draft to verify" caveat the blueprint itself puts on
 * GatewayCurrencyMatrix) — never invented, only left null when unrecognized.
 */
class CaptureEsimUsageSnapshotJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // never blind-retry against a live provider call

    public function __construct(public int $esimOrderId) {}

    public function handle(): void
    {
        $order = EsimOrder::find($this->esimOrderId);
        if ($order === null || $order->status !== 'active' || blank($order->iccid)) {
            return;
        }

        try {
            $raw = app("esim.{$order->provider}")->getUsage($order->iccid, (string) ($order->bundle_name ?? ''));
        } catch (Throwable $e) {
            // Providers hiccup — a failed snapshot must never crash a queue worker.
            Log::warning("CaptureEsimUsageSnapshotJob: getUsage failed for order {$order->id} ({$order->provider}): {$e->getMessage()}");

            return;
        }

        [$total, $remaining, $used, $status] = $this->normalize($raw);

        EsimUsageSnapshot::create([
            'esim_order_id' => $order->id,
            'user_id' => $order->user_id,
            'data_total_mb' => $total,
            'data_remaining_mb' => $remaining,
            'data_used_mb' => $used,
            'bundle_status' => $status,
            'captured_at' => now(),
        ]);

        // Opportunistically keep the single-value display column correct too,
        // so anything still reading data_remaining_mb directly stays accurate
        // without a second polling path.
        if ($remaining !== null) {
            $order->forceFill(['data_remaining_mb' => $remaining])->save();
        }
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: ?int, 3: ?string} [total_mb, remaining_mb, used_mb, status]
     */
    private function normalize(array $raw): array
    {
        // Confirmed-normalized shape (EsimAccessService, UbigiService).
        if (array_key_exists('remaining_mb', $raw) || array_key_exists('used_mb', $raw)) {
            $remaining = isset($raw['remaining_mb']) ? (int) $raw['remaining_mb'] : null;
            $used = isset($raw['used_mb']) ? (int) $raw['used_mb'] : null;
            $total = $remaining !== null && $used !== null ? $remaining + $used : null;

            return [$total, $remaining, $used, $this->status($raw)];
        }

        // Best-effort common-alias fallback for every other provider's raw
        // payload — verify against a live sandbox response before trusting.
        $remaining = $this->firstNumeric($raw, ['remainingMb', 'remaining', 'dataRemaining', 'data_remaining', 'remainingData', 'balance']);
        $total = $this->firstNumeric($raw, ['totalMb', 'total', 'dataTotal', 'data_total', 'totalData', 'planData', 'initial', 'initialQuantity']);
        $used = $this->firstNumeric($raw, ['usedMb', 'used', 'dataUsed', 'data_used', 'consumedData']);

        if ($used === null && $total !== null && $remaining !== null) {
            $used = max(0, $total - $remaining);
        }
        if ($total === null && $used !== null && $remaining !== null) {
            $total = $used + $remaining;
        }

        return [$total, $remaining, $used, $this->status($raw)];
    }

    private function firstNumeric(array $raw, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($raw[$key]) && is_numeric($raw[$key])) {
                return (int) round((float) $raw[$key]);
            }
        }

        return null;
    }

    private function status(array $raw): ?string
    {
        foreach (['bundleState', 'status', 'state', 'esimStatus'] as $key) {
            if (isset($raw[$key]) && is_scalar($raw[$key])) {
                return (string) $raw[$key];
            }
        }

        return null;
    }
}
