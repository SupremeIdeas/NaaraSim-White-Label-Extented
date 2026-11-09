<?php

namespace App\Services\Topup;

use App\Jobs\AlertAdminJob;
use App\Models\Setting;
use App\Models\TopupOrder;
use App\Models\TopupProvider;
use App\Models\TopupProviderLog;
use App\Models\TopupRoute;
use App\Services\Pricing\CurrencyService;
use App\Services\Topup\DTO\PurchaseResult;
use App\Support\TopupSettings;
use Throwable;

/** Operations: seeding, float monitoring, daily reconciliation, margin report, log retention. Thin commands and the admin panel call these. */
class TopupOps
{
    /** Supplier rows + a sensible default route table (idempotent; never overwrites what the admin edited). */
    public function seed(): void
    {
        foreach (TopupProviders::META as $code => $m) {
            TopupProvider::firstOrCreate(['code' => $code], ['name' => $m['name'], 'currency' => $m['currency'], 'enabled' => true]);
        }
        app(TopupSnapshot::class)->import();   // an empty catalogue starts from the shipped snapshot of every operator's offers
        if (TopupRoute::count() === 0) {
            $rows = [
                ['NG', '*', 'vtpass', 1], ['NG', '*', 'zendit', 2], ['NG', '*', 'reloadly', 3], ['NG', '*', 'ding', 4], ['NG', '*', 'bitrefill', 5], ['NG', '*', 'paga', 6],
                ['*', '*', 'zendit', 1], ['*', '*', 'reloadly', 2], ['*', '*', 'ding', 3], ['*', '*', 'bitrefill', 4],
            ];
            foreach ($rows as [$c, $t, $p, $prio]) {
                TopupRoute::create(['country_iso' => $c, 'product_type' => $t, 'provider_code' => $p, 'priority' => $prio, 'enabled' => true]);
            }
        }
    }

    /** Poll every configured supplier's float; alert once when it falls below the minimum, clear when topped up. @return array<string, array> */
    public function pollFloat(): array
    {
        $out = [];
        foreach (TopupProviders::all() as $code => $p) {
            $row = TopupProvider::firstOrCreate(['code' => $code], ['name' => $p->name(), 'enabled' => true]);
            if (! $p->configured()) {
                continue;
            }
            try {
                $b = $p->balance();
                $row->update(['last_balance' => $b['amount'], 'currency' => $b['currency'], 'last_balance_at' => now(), 'last_error' => null]);
                $low = (float) $row->min_float > 0 && $b['amount'] < (float) $row->min_float;
                if ($low && ! $row->low_float_alerted && $row->enabled) {
                    AlertAdminJob::dispatch(code: 'topup_low_float_'.$code, message: "{$row->name} float is {$b['currency']} ".number_format($b['amount'], 2).' (minimum '.number_format((float) $row->min_float, 2).'). It is skipped in routing until topped up.', context: ['provider' => $code]);
                    $row->update(['low_float_alerted' => true]);
                } elseif (! $low && $row->low_float_alerted) {
                    $row->update(['low_float_alerted' => false]);
                }
                $out[$code] = ['ok' => true] + $b;
            } catch (Throwable $e) {
                $row->update(['last_error' => substr($e->getMessage(), 0, 250)]);
                $out[$code] = ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        return $out;
    }

    /**
     * Compare our last 48h of orders with the supplier by reference. Flags: delivered at the supplier but not on ours (money we owe the
     * supplier for nothing), delivered on ours but failed/missing at the supplier (customer shorted).
     *
     * @return array{checked: int, mismatches: list<array>}
     */
    public function reconcile(int $hours = 48): array
    {
        $mismatch = [];
        $checked = 0;
        $orders = TopupOrder::where('created_at', '>=', now()->subHours($hours))->whereNotIn('status', [TopupOrder::CREATED, TopupOrder::PAYMENT_FAILED])->limit(1000)->get();
        foreach ($orders as $o) {
            try {
                $r = TopupProviders::get($o->provider_code)->requery($o->reference, $o->provider_txn_id);
            } catch (Throwable) {
                continue;
            }
            $checked++;
            $ours = $o->status;
            $bad = ($r->status === PurchaseResult::DELIVERED && in_array($ours, [TopupOrder::FAILED, TopupOrder::REFUNDED], true))
                || (in_array($r->status, [PurchaseResult::FAILED, PurchaseResult::REVERSED], true) && $ours === TopupOrder::DELIVERED);
            if ($bad) {
                $mismatch[] = ['order' => $o->uuid, 'ours' => $ours, 'supplier' => $r->status, 'provider' => $o->provider_code];
            }
        }
        foreach ($mismatch as $m) {
            AlertAdminJob::dispatch(code: 'topup_reconcile_mismatch', message: "Top-up {$m['order']}: we say {$m['ours']}, {$m['provider']} says {$m['supplier']}.", context: $m);
        }
        Setting::setValue('topup.last_reconcile', ['at' => now()->toIso8601String(), 'checked' => $checked, 'mismatches' => $mismatch], 'topup');

        return ['checked' => $checked, 'mismatches' => $mismatch];
    }

    /**
     * Revenue minus supplier cost per day (USD). Cost is USD-equivalent at quote time (cost_usd). Refunded/failed orders are excluded.
     *
     * @return array{revenue: float, cost: float, margin: float, orders: int, by_provider: array<string, array>}
     */
    public function margin(int $days = 30): array
    {
        $rows = TopupOrder::where('status', TopupOrder::DELIVERED)->where('delivered_at', '>=', now()->subDays($days))->get(['provider_code', 'price_usd', 'cost_usd']);
        $by = [];
        foreach ($rows as $o) {
            $b = &$by[$o->provider_code];
            $b['orders'] = ($b['orders'] ?? 0) + 1;
            $b['revenue'] = ($b['revenue'] ?? 0) + (float) $o->price_usd;
            $b['cost'] = ($b['cost'] ?? 0) + (float) $o->cost_usd;
            unset($b);
        }
        $rev = (float) $rows->sum('price_usd');
        $cost = (float) $rows->sum('cost_usd');

        return ['revenue' => round($rev, 2), 'cost' => round($cost, 2), 'margin' => round($rev - $cost, 2), 'orders' => $rows->count(), 'by_provider' => $by];
    }

    /** Provider call logs hold phone numbers: keep them only as long as the admin says. */
    public function pruneLogs(): int
    {
        return TopupProviderLog::where('created_at', '<', now()->subDays(max(7, TopupSettings::int('log_retention_days'))))->delete();
    }
}
