<?php

namespace App\Services\Payouts;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutRequest;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Payouts sitting in `processing` past the rail's SLA. Where the provider offers a
 * verified status lookup we ask it: a confirmed outcome is applied through the normal
 * confirm()/fail() paths. Anything we cannot verify is ALERTED — never reversed.
 */
class PayoutStuckWatchdog
{
    /** Default SLA before a processing payout is "stuck", in minutes. Slow rails (global) get far longer. */
    private const DEFAULT_SLA_MIN = ['paystack' => 60, 'flutterwave' => 60, 'stripe' => 24 * 60, 'paypal' => 24 * 60, 'cryptomus' => 60];

    public function __construct(private PayoutService $payouts) {}

    /** @return array{checked: int, resolved: int, alerted: int} */
    public function run(int $limit = 100): array
    {
        $s = ['checked' => 0, 'resolved' => 0, 'alerted' => 0];

        PayoutRequest::query()->where('status', PayoutRequest::PROCESSING)->orderBy('updated_at')->limit($limit)->get()
            ->each(function (PayoutRequest $r) use (&$s) {
                $sla = self::DEFAULT_SLA_MIN[$r->provider] ?? 24 * 60;
                if ($r->updated_at->gt(now()->subMinutes($sla))) {
                    return;
                }
                $s['checked']++;

                $gateway = $this->payouts->gatewayFor($r->provider);
                if ($gateway instanceof SupportsStatusLookup) {
                    try {
                        $res = $gateway->lookupTransfer($r);
                        if ($res->state === LookupResult::FOUND && $res->status === 'paid') {
                            $this->payouts->confirm($r, $res->providerRef);
                            $s['resolved']++;

                            return;
                        }
                        if ($res->state === LookupResult::FOUND && $res->status === 'failed') {
                            $this->payouts->fail($r, 'Provider reported: '.($res->failureReason ?? 'failed'));
                            $s['resolved']++;

                            return;
                        }
                    } catch (Throwable) {
                        // fall through to the alert — a failed lookup proves nothing
                    }
                }

                if (Cache::add("payout-stuck:{$r->id}", 1, now()->addHours(6))) {
                    AlertAdminJob::dispatch(
                        code: 'payout_stuck',
                        message: "Payout #{$r->id} has been processing for over {$sla} minutes on {$r->provider}. Check it with the provider — do not refund until verified.",
                        context: ['payout_id' => $r->id, 'provider' => $r->provider],
                    );
                    $s['alerted']++;
                }
            });

        return $s;
    }
}
