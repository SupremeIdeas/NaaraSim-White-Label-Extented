<?php

namespace Tests\Feature;

use App\PayoutRails\Grey\GreyWebhookRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Grey webhook registration: Grey is told the address and a generated secret, and the secret is saved here only after Grey accepted it. */
class GreyWebhookRegistrarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.grey.api_secret' => 'gbsk_test', 'services.grey.webhook_secret' => null, 'services.grey.base_url' => 'https://businessapi-sandbox.grey.co', 'payouts.env' => 'sandbox', 'app.url' => 'https://example.com']);
    }

    public function test_it_registers_the_payout_webhook_and_saves_the_secret_only_after_grey_accepts(): void
    {
        Http::fake(['*/v1/webhooks' => Http::response(['status' => 'success', 'data' => ['id' => 'w1', 'active' => true]])]);
        $r = app(GreyWebhookRegistrar::class)->register();
        $this->assertTrue($r['ok'], $r['message']);

        $secret = null;
        Http::assertSent(function (Request $req) use (&$secret) {
            $b = $req->data();
            $secret = $b['secret'] ?? null;

            return str_ends_with($req->url(), '/v1/webhooks') && str_contains($b['url'], '/webhooks/payouts/grey')
                && strlen((string) $secret) >= 8 && $b['event_types'] === ['transaction.success', 'transaction.failed'];
        });
        $this->assertSame($secret, config('services.grey.webhook_secret'), 'the secret Grey was given is the one we verify with');
    }

    public function test_a_refusal_saves_nothing_and_a_second_run_needs_force(): void
    {
        Http::fake(['*/v1/webhooks' => Http::response(['error' => 'bad url'], 400)]);
        $r = app(GreyWebhookRegistrar::class)->register();
        $this->assertFalse($r['ok']);
        $this->assertEmpty(config('services.grey.webhook_secret'));

        config(['services.grey.webhook_secret' => 'already-saved-secret']);
        $again = app(GreyWebhookRegistrar::class)->register();
        $this->assertFalse($again['ok']);
        $this->assertStringContainsString('already', $again['message']);
    }
}
