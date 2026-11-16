<?php

namespace Tests\Feature;

use App\Jobs\GreyInflowJob;
use App\Models\PayoutWebhookEvent;
use App\PayoutRails\Grey\GreyReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Grey autopilot: a deposit releases waiting payouts at once, every inflow is its own event, and the readiness score tells the truth. */
class GreyAutopilotTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_grey';

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\Payouts\PayoutService::class);
        Cache::flush();
        config(['services.grey.api_secret' => 'gbsk_test', 'services.grey.webhook_secret' => self::SECRET, 'services.grey.base_url' => 'https://businessapi-sandbox.grey.co', 'payouts.env' => 'sandbox']);
    }

    private function inflow(string $ref, string $delivery)
    {
        $body = json_encode(['event_type' => 'transaction.received', 'transaction_reference' => $ref, 'amount' => 100, 'status' => 'completed']);

        return $this->call('POST', '/webhooks/payouts/grey', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_EVENT' => 'transaction.received', 'HTTP_X_WEBHOOK_DELIVERY_ID' => $delivery,
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);
    }

    public function test_each_deposit_event_is_recorded_separately_and_wakes_the_float_release(): void
    {
        Bus::fake();
        $this->inflow('GREY111', 'd-1')->assertOk();
        $this->inflow('GREY222', 'd-2')->assertOk();
        $this->inflow('GREY222', 'd-2')->assertOk()->assertJson(['duplicate' => true]);   // a retried delivery is a no-op

        $this->assertSame(2, PayoutWebhookEvent::where('provider', 'grey')->count(), 'two different deposits, two rows');
        Bus::assertDispatched(GreyInflowJob::class);
    }

    public function test_the_readiness_score_reads_grey_and_reports_what_is_missing(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            '*/v1/balances' => Http::response(['status' => 'success', 'data' => ['balances' => [['currency' => 'USD', 'currency_type' => 'fiat', 'available_balance' => 0]]]]),
            '*/v1/payout-methods' => Http::response(['status' => 'success', 'data' => ['payout_methods' => [['name' => 'bank_account', 'currencies' => ['USD'], 'countries' => ['US'], 'required_fields' => ['account_number']]]]]),
            '*/v1/currency/rate' => Http::response(['status' => 'success', 'data' => ['destination_amount' => 14000]]),
        ]);
        $r = app(GreyReadiness::class)->run();
        $by = collect($r['checks'])->keyBy('label');

        $this->assertSame(10, $r['total']);
        $this->assertSame('pass', $by['Grey accepts the key']['status']);
        $this->assertSame('pass', $by['Live rate quote works']['status']);
        $this->assertSame('warn', $by['Grey wallet funded']['status'], 'a zero balance is a warning, not a pass');
        $this->assertSame('fail', $by['Corridors enabled']['status']);
        $this->assertLessThan(10, $r['score']);
    }

    public function test_a_rejected_key_fails_the_live_check_and_the_offline_mode_makes_no_call(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['error' => 'invalid API key'], 401)]);
        $r = app(GreyReadiness::class)->run();
        $this->assertSame('fail', collect($r['checks'])->firstWhere('label', 'Grey accepts the key')['status']);

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake();
        app(GreyReadiness::class)->run(false);
        Http::assertNothingSent();
    }
}
