<?php

namespace App\Console\Commands;

use App\Models\PayoutAccountingEntry;
use Illuminate\Console\Command;

class PayoutsAccountingExportCommand extends Command
{
    protected $signature = 'payouts:accounting-export {month : YYYY-MM} {--provider= : Limit to one provider} {--out= : File path (default: stdout)}';

    protected $description = 'Month-end CSV of the payout accounting ledger for the owner/accountant (admin-only data)';

    public function handle(): int
    {
        $from = \Illuminate\Support\Carbon::createFromFormat('Y-m', $this->argument('month'))->startOfMonth();
        $q = PayoutAccountingEntry::query()->whereBetween('occurred_at', [$from, $from->copy()->endOfMonth()])->orderBy('id');
        if ($p = $this->option('provider')) {
            $q->where('account_code', 'like', '%'.$p.'%');
        }

        $h = fopen($this->option('out') ?: 'php://output', 'w');
        fputcsv($h, ['occurred_at', 'entry_group', 'payout_request_id', 'account', 'direction', 'amount_usd', 'currency', 'amount_local', 'fx_rate', 'memo']);
        $q->each(fn ($e) => fputcsv($h, [$e->occurred_at->toIso8601String(), $e->entry_group, $e->payout_request_id, $e->account_code, $e->direction, $e->amount_usd, $e->currency, $e->amount_local, $e->fx_rate, $e->memo]));
        fclose($h);

        return self::SUCCESS;
    }
}
