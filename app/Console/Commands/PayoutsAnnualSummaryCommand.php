<?php

namespace App\Console\Commands;

use App\Services\Payouts\Hardening\PayeeTax;
use Illuminate\Console\Command;

class PayoutsAnnualSummaryCommand extends Command
{
    protected $signature = 'payouts:annual-summary {year} {--out= : File path (default: stdout)}';

    protected $description = 'Per-payee yearly payout totals as CSV for the accountant (reporting hook only, not tax advice)';

    public function handle(PayeeTax $tax): int
    {
        $h = fopen($this->option('out') ?: 'php://output', 'w');
        fputcsv($h, ['user_id', 'country', 'provider', 'payouts', 'total_usd', 'tax_country', 'form_type', 'form_status', 'provider_collected']);
        foreach ($tax->annualSummary((int) $this->argument('year')) as $r) {
            fputcsv($h, [$r['user_id'], $r['country'], $r['provider'], $r['payouts'], $r['total_usd'], $r['tax_country'], $r['form_type'], $r['form_status'], $r['provider_collected'] ? 'yes' : 'no']);
        }
        fclose($h);

        return self::SUCCESS;
    }
}
