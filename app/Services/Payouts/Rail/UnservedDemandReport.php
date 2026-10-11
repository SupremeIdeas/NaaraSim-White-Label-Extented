<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutCorridor;
use App\Models\User;
use App\Support\Money4;
use Illuminate\Support\Facades\Cache;

/**
 * Where users hold withdrawable money but we have NO enabled payout corridor at all —
 * i.e. which corridor to open next. NOT enrolled, NOT part of funding: clearly its own
 * report. Only users with a positive balance in an unserved country are touched.
 */
class UnservedDemandReport
{
    /** @return list<array{country: string, users: int, usd: string, notify_requests: int}> biggest first */
    public function get(bool $fresh = false): array
    {
        $fresh && Cache::forget('payout:unserved-demand');

        return Cache::remember('payout:unserved-demand', 3600, fn () => $this->build());
    }

    private function build(): array
    {
        $served = PayoutCorridor::query()->enabled()->pluck('country')->unique()->all();
        $candidates = User::query()->whereNotNull('country_code')->where('country_code', '!=', '')
            ->whereNotIn('country_code', $served ?: ['--'])->get(['id', 'country_code']);

        $resolver = app(WithdrawableBalanceResolver::class);
        $byCountry = [];
        foreach ($candidates->chunk(500) as $chunk) {
            foreach ($resolver->forUsers($chunk->pluck('id')) as $id => $b) {
                $units = Money4::units($b['total']);
                if ($units <= 0) {
                    continue;
                }
                $c = strtoupper($chunk->firstWhere('id', $id)->country_code);
                $byCountry[$c]['users'] = ($byCountry[$c]['users'] ?? 0) + 1;
                $byCountry[$c]['units'] = ($byCountry[$c]['units'] ?? 0) + $units;
            }
        }

        $notify = \Illuminate\Support\Facades\Schema::hasTable('payout_guide_events')
            ? \Illuminate\Support\Facades\DB::table('payout_guide_events')->where('event', 'blocked_notify_requested')->selectRaw('country, COUNT(DISTINCT user_id) as n')->groupBy('country')->pluck('n', 'country')->all()
            : [];

        $rows = [];
        foreach ($byCountry as $country => $d) {
            $rows[] = ['country' => $country, 'users' => $d['users'], 'usd' => Money4::str($d['units']), 'notify_requests' => (int) ($notify[$country] ?? 0), '_u' => $d['units']];
        }
        usort($rows, fn ($a, $b) => $b['_u'] <=> $a['_u']);

        return array_map(fn ($r) => array_diff_key($r, ['_u' => 1]), $rows);
    }
}
