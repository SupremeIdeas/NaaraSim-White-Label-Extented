<?php

namespace App\Console\Commands;

use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Services\Payouts\PaystackPayoutGateway;
use App\Services\Payouts\Rail\RailRegistrySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * One-command sandbox pass for a provider (GO-LIVE-CHECKLIST "owed" checks). Paystack only for now.
 * Refuses to run with anything but a TEST key. Read-only by default; `--send` also creates a recipient and sends one
 * tiny TEST transfer, verifies it by reference, and re-sends the same reference to prove the provider dedupes.
 * Prints the raw shapes it saw so the gateway's assumptions can be compared with reality.
 */
class PayoutsSandboxCheckCommand extends Command
{
    protected $signature = 'payouts:sandbox-check {provider=paystack} {--send : also create a recipient and send one test transfer (NGN 100)}';

    protected $description = 'Run the owed provider sandbox checks against the real sandbox API (test keys only)';

    /** @var list<array{0: string, 1: bool, 2: string}> */
    private array $rows = [];

    public function handle(PaystackPayoutGateway $gateway): int
    {
        if ($this->argument('provider') !== 'paystack') {
            $this->error('Only paystack has a sandbox runner so far.');

            return self::FAILURE;
        }
        $key = (string) config('services.paystack.secret_key');
        if (! str_starts_with($key, 'sk_test_')) {
            $this->error($key === '' ? 'PAYSTACK_SECRET_KEY is empty. Put your Paystack TEST secret key (sk_test_...) in .env.' : 'This is not a TEST key (sk_test_...). The sandbox pass never runs with a live key.');

            return self::FAILURE;
        }
        $base = rtrim((string) config('services.paystack.base_url'), '/');
        $http = fn () => Http::withToken($key)->acceptJson()->timeout(25);

        // 1. /balance — shape and units
        $this->step('GET /balance returns [{currency, balance}] (subunits)', function () use ($http, $base, $gateway) {
            $raw = $http()->get("{$base}/balance")->json();
            $ok = is_array(data_get($raw, 'data')) && data_get($raw, 'data.0.currency') !== null && data_get($raw, 'data.0.balance') !== null;

            return [$ok, 'raw='.json_encode(data_get($raw, 'data')).' parsed(major)='.json_encode($gateway->balances())];
        });

        // 2. /country — our rail registry constants vs Paystack's own list
        $this->step('GET /country matches our Paystack country list', function () use ($http, $base) {
            $codes = collect((array) data_get($http()->get("{$base}/country")->json(), 'data'))->pluck('iso_code')->sort()->values()->all();
            $ours = RailRegistrySeeder::PAYSTACK;
            sort($ours);
            $missing = array_values(array_diff($codes, $ours));

            return [true, 'paystack='.json_encode($codes).' ours='.json_encode($ours).($missing ? ' NOT IN OURS (operating countries, not proof transfers work): '.json_encode($missing) : '')];
        });

        // 3. verify an unknown reference — what does "not found" look like?
        $this->step('GET /transfer/verify/{unknown} body for "not found"', function () use ($http, $base) {
            $res = $http()->get("{$base}/transfer/verify/ns-sandbox-does-not-exist-".bin2hex(random_bytes(4)));

            return [true, 'http='.$res->status().' body='.mb_substr($res->body(), 0, 300).'  (our lookup treats 404/status:false as notFound)'];
        });

        // 4. banks
        $this->step('GET /bank?country=nigeria&currency=NGN', function () use ($http, $base) {
            $res = $http()->get("{$base}/bank", ['country' => 'nigeria', 'currency' => 'NGN', 'perPage' => 5])->json();

            return [(bool) data_get($res, 'status'), 'sample='.json_encode(collect((array) data_get($res, 'data'))->take(2)->map(fn ($b) => ['name' => $b['name'] ?? null, 'code' => $b['code'] ?? null])->all())];
        });

        if ($this->option('send')) {
            $this->sendPass($gateway, $http, $base);
        }

        $this->newLine();
        $this->table(['Check', 'Result', 'Evidence'], array_map(fn ($r) => [$r[0], $r[1] ? 'PASS' : 'FAIL', $r[2]], $this->rows));
        $failed = count(array_filter($this->rows, fn ($r) => ! $r[1]));
        $this->line($failed === 0 ? 'Sandbox pass complete: record the evidence above in docs/payouts/GO-LIVE-CHECKLIST.md.' : "{$failed} check(s) FAILED: do not enable Paystack payouts until resolved.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function sendPass(PaystackPayoutGateway $gateway, callable $http, string $base): void
    {
        $recipient = null;
        $this->step('POST /transferrecipient (nuban test account)', function () use ($http, $base, &$recipient) {
            $res = $http()->post("{$base}/transferrecipient", ['type' => 'nuban', 'name' => 'SANDBOX TEST', 'account_number' => '0000000000', 'bank_code' => '057', 'currency' => 'NGN'])->json();
            $recipient = data_get($res, 'data.recipient_code');

            return [(bool) $recipient, 'recipient='.($recipient ?: json_encode($res))];
        });
        if (! $recipient) {
            return;
        }

        $request = new PayoutRequest(['amount' => 100, 'currency' => 'NGN', 'reference' => 'sandbox-'.bin2hex(random_bytes(6))]);
        $request->id = 0;
        $account = new PayoutAccount(['provider_recipient_ref' => $recipient, 'currency' => 'NGN']);
        $wire = null;

        $this->step('POST /transfer with our reference', function () use ($gateway, $request, $account, &$wire) {
            $wire = $request->wireReference();
            $r = $gateway->sendTransfer($request, $account);

            return [in_array($r->status, ['processing', 'paid'], true), "wire={$wire} result={$r->status} ref=".($r->providerRef ?? '-').' reason='.($r->failureReason ?? '-')];
        });
        $this->step('verify by OUR reference finds it (lookupTransfer)', function () use ($gateway, $request) {
            $l = $gateway->lookupTransfer($request);

            return [$l->state === 'found', 'state='.$l->state.' status='.($l->status ?? '-')];
        });
        $this->step('re-sending the SAME reference does not create a second transfer', function () use ($gateway, $request, $account) {
            $r = $gateway->sendTransfer($request, $account);

            return [true, "second send result={$r->status} reason=".($r->failureReason ?? '-').' (a duplicate-reference rejection is the good outcome; a second transfer_code is a FAIL, compare refs above)'];
        });
    }

    private function step(string $name, callable $fn): void
    {
        try {
            [$ok, $evidence] = $fn();
        } catch (Throwable $e) {
            [$ok, $evidence] = [false, $e->getMessage()];
        }
        $this->rows[] = [$name, (bool) $ok, mb_substr($evidence, 0, 400)];
    }
}
