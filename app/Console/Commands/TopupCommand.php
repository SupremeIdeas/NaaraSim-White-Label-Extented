<?php

namespace App\Console\Commands;

use App\Services\Topup\TopupCatalogue;
use App\Services\Topup\TopupOps;
use Illuminate\Console\Command;

class TopupCommand extends Command
{
    protected $signature = 'topup:run {task : seed|sync|float|reconcile|prune|margin|rates|live|ip|autopilot|autoreload|snapshot} {arg? : country for the rates and live tasks, e.g. NG}';

    protected $description = 'Top-up operations: seed suppliers + routes, sync the catalogue, poll float, reconcile, prune logs, margin report';

    public function handle(TopupOps $ops, TopupCatalogue $catalogue): int
    {
        switch ($this->argument('task')) {
            case 'seed':
                $ops->seed();
                $this->info('Suppliers and default routes are in place.');
                break;
            case 'sync':
                \App\Models\Setting::setValue('topup.last_sync_at', now()->toIso8601String());
                $this->table(['Supplier', 'Products'], collect($catalogue->syncAll())->map(fn ($n, $c) => [$c, $n])->values()->all());
                break;
            case 'float':
                foreach ($ops->pollFloat() as $c => $r) {
                    $this->line($c.': '.(($r['ok'] ?? false) ? $r['currency'].' '.number_format($r['amount'], 2) : 'ERROR '.($r['error'] ?? '')));
                }
                break;
            case 'reconcile':
                $r = $ops->reconcile();
                $this->info("Checked {$r['checked']}, mismatches ".count($r['mismatches']));
                break;
            case 'prune':
                $this->info('Pruned '.$ops->pruneLogs().' log rows');
                break;
            case 'autoreload':
                $this->info('Auto top-ups attempted: '.app(\App\Services\Topup\TopupAutoReloadService::class)->runDue());
                break;
            case 'snapshot':
                $this->info('Snapshot of '.app(\App\Services\Topup\TopupSnapshot::class)->export().' products written');
                break;
            case 'rates':
                $r = app(\App\Services\Topup\TopupRateCheck::class)->airtime((string) ($this->argument('arg') ?: 'NG'));
                $this->line("Mid-market ".$r['currency']." per USD: ".($r['mid'] ?? 'unavailable').'   markup '.$r['settings']['markup_pct'].'%  FX buffer '.$r['settings']['fx_buffer_pct'].'%  min profit $'.$r['settings']['min_profit_usd']);
                $this->table(['Network', 'Supplier', 'Face', 'Cost $', 'Our price $', 'Par $', 'Markup on cost %', 'Supplier vs par %', 'Our price vs par %'], collect($r['rows'])->map(fn ($x) => [$x['operator'], $x['supplier'], $x['face'], $x['cost_usd'], $x['price_usd'], $x['par_usd'], $x['markup_on_cost_pct'], $x['supplier_vs_par_pct'], $x['price_vs_par_pct']])->all());
                break;
            case 'autopilot':
                \App\Jobs\Topup\TopupAutopilotJob::dispatch();
                $this->info('Top-up autopilot queued.');
                break;
            case 'ip':
                $ip = \App\Support\ServerEgressIp::get(true);
                $this->line($ip ? "Outbound IP of this server (whitelist this with every supplier that asks): $ip" : 'Could not detect the outbound IP; run "curl https://ifconfig.me" on the server.');
                break;
            case 'live':
                $r = app(\App\Services\Topup\TopupLiveRates::class)->run((string) ($this->argument('arg') ?: 'NG'), 'airtime', $this->argument('arg') === 'NG' || ! $this->argument('arg') ? [500, 1000, 2000, 5000] : [5, 10, 20, 50], force: true);
                $this->line('Mid-market '.$r['currency'].' per USD: '.($r['mid'] ?? 'unavailable').'   markup '.$r['settings']['markup_pct'].'%  FX buffer '.$r['settings']['fx_buffer_pct'].'%  min profit $'.$r['settings']['min_profit_usd']);
                $this->table(['Supplier', 'State', 'ms', 'Listed', 'Avg supplier vs par %', 'Avg our margin %', 'Avg our price vs par %', 'Cheapest on'], collect($r['providers'])->map(fn ($p) => [$p['name'], $p['state'].($p['error'] ? ' ('.$p['error'].')' : ''), $p['ms'], $p['listed'], $p['avg_supplier_vs_par'], $p['avg_margin_pct'], $p['avg_price_vs_par'], $p['cheapest_count'] ?? 0])->all());
                $this->table(['Network', 'Supplier', 'Face', 'Supplier sells us $', 'We charge $', 'Margin $', 'Margin %', 'Par $', 'Supplier vs par %', 'Cheapest'], collect($r['rows'])->map(fn ($x) => [$x['operator'], $x['provider'], $x['face'], $x['cost_usd'], $x['price_usd'], $x['margin_usd'], $x['margin_pct'], $x['par_usd'], $x['supplier_vs_par_pct'], $x['cheapest'] ? 'yes' : ''])->all());
                foreach ($r['alerts'] as $a) {
                    $this->line(strtoupper($a['level']).': '.$a['text']);
                }
                break;
            case 'margin':
                $this->line(json_encode($ops->margin(), JSON_PRETTY_PRINT));
                break;
            default:
                $this->error('Unknown task');

                return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
