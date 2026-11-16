<?php

namespace App\PayoutRails\Grey;

use App\Models\PayoutCorridor;
use App\Models\PayoutFloatBalance;
use App\Models\PayoutWebhookEvent;
use App\Support\JobHeartbeats;

/**
 * The Grey integration scoreboard: ten checks from "is the key saved" to "is the automation actually running". Each is pass / warn / fail with a plain reason
 * and, when it is not a pass, what to do. Read-only: the live checks only read balances and a rate quote. `score` counts passes out of ten.
 */
class GreyReadiness
{
    public function __construct(private readonly GreyClient $client)
    {
    }

    /** @return array{score: int, total: int, checks: list<array{label: string, status: string, detail: string}>} */
    public function run(bool $live = true): array
    {
        $c = [];
        $add = function (string $label, string $status, string $detail) use (&$c) {
            $c[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
        };

        $configured = $this->client->configured();
        $add('API secret key saved', $configured ? 'pass' : 'fail', $configured ? 'The key is saved.' : 'Save the Grey secret key (Admin > Connections > Grey).');

        $consistent = $configured && $this->client->environmentConsistent();
        $add('Host matches the payout mode', $consistent ? 'pass' : 'fail', $consistent ? ($this->client->sandboxHost() ? 'Sandbox host with sandbox mode.' : 'Production host with live mode.') : 'The Grey host and the payout mode disagree, so nothing will be sent.');

        $balances = null;
        if ($live && $configured) {
            try {
                $r = $this->client->balances();
                $balances = $r->successful() ? (array) data_get($r->json(), 'data.balances', []) : null;
                $add('Grey accepts the key', $balances !== null ? 'pass' : 'fail', $balances !== null ? 'Wallet balances were read.' : 'Grey answered HTTP '.$r->status().'. Check the key and the host.');
            } catch (\Throwable) {
                $add('Grey accepts the key', 'fail', 'Grey could not be reached from this server.');
            }
        } else {
            $add('Grey accepts the key', 'warn', $live ? 'Skipped: no key.' : 'Skipped (offline check).');
        }

        if ($live && $configured) {
            try {
                $n = count($this->client->payoutMethods(fresh: true));
                $add('Payout methods readable', $n > 0 ? 'pass' : 'fail', $n > 0 ? "{$n} payout methods listed by Grey." : 'Grey returned no payout methods.');
            } catch (\Throwable) {
                $add('Payout methods readable', 'fail', 'Grey could not be reached.');
            }
            try {
                $q = $this->client->quoteWithdrawal('USD', 'NGN', 10);
                $ok = $q->successful() && (float) data_get($q->json(), 'data.destination_amount', 0) > 0;
                $add('Live rate quote works', $ok ? 'pass' : 'warn', $ok ? 'A USD to NGN quote came back (used to size cross-currency payouts).' : 'Grey did not quote USD to NGN (HTTP '.$q->status().').');
            } catch (\Throwable) {
                $add('Live rate quote works', 'warn', 'Grey could not be reached.');
            }
        } else {
            $add('Payout methods readable', 'warn', 'Skipped.');
            $add('Live rate quote works', 'warn', 'Skipped.');
        }

        $secret = filled(config('services.grey.webhook_secret'));
        $add('Webhook secret saved', $secret ? 'pass' : 'fail', $secret ? 'Deliveries are verified with it.' : 'Press "Register the webhook with Grey for me" on the Grey guide.');

        $seen = PayoutWebhookEvent::query()->where('provider', 'grey')->where('received_at', '>=', now()->subDays(30))->exists();
        $add('A Grey webhook has arrived', $seen ? 'pass' : 'warn', $seen ? 'Grey has delivered a webhook in the last 30 days.' : 'None yet. It appears after the first payout finishes. Until then the 2-minute reconciler settles payouts.');

        $corr = PayoutCorridor::query()->enabled()->where('provider', 'grey')->count();
        $add('Corridors enabled', $corr > 0 ? 'pass' : 'fail', $corr > 0 ? "{$corr} Grey corridors are switched on." : 'No Grey corridor is on. The hourly sync adds them once the key works.');

        $funded = $balances === null ? null : collect($balances)->contains(fn ($b) => (float) ($b['available_balance'] ?? 0) > 0);
        $add('Grey wallet funded', $funded === true ? 'pass' : 'warn', $funded === null ? 'Not read.' : ($funded ? 'At least one wallet has money.' : 'Every wallet is at zero. Payouts wait until you fund one.'));

        $stale = [];
        foreach (['payouts:reconcile-unknown' => 600, 'payouts:float-sync' => 5400, 'payouts:sync-corridors' => 7800] as $cmd => $max) {
            $last = JobHeartbeats::lastSuccessAt($cmd);
            if ($last === null || $last->diffInSeconds(now()) > $max) {
                $stale[] = $cmd;
            }
        }
        $float = PayoutFloatBalance::query()->where('provider', 'grey')->max('last_synced_at');
        $add('Automation is running', $stale === [] ? 'pass' : 'warn', $stale === [] ? 'The reconciler, float sync and corridor sync all ran on time.' : 'These scheduled jobs have not run recently: '.implode(', ', $stale).'. Check the cron (Admin > System health).'.($float ? '' : ' No Grey float has been read yet.'));

        return ['score' => count(array_filter($c, fn ($r) => $r['status'] === 'pass')), 'total' => count($c), 'checks' => $c];
    }
}
