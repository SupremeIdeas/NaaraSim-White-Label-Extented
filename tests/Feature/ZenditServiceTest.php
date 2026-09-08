<?php

namespace Tests\Feature;

use App\Exceptions\EsimProviderException;
use App\Services\eSIM\ZenditService;
use App\Support\Niche\LpaActivation;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Zendit backs the Naara Connect line (Full eSIMs: calls + data). These assert
 * the wire contract: Bearer auth, the sandbox host, paged offers, an idempotent
 * purchase that reads back the activation confirmation, and divisor-scaled
 * balance/cost — the wholesale cost stays private.
 */
class ZenditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.zendit.base_url' => 'https://test-api.zendit.io/v1',
            'services.zendit.api_key' => 'sand_test-key',
            'services.zendit.sandbox' => true,
        ]);
    }

    public function test_get_catalogue_pages_offers_with_bearer_auth(): void
    {
        Http::fake([
            'test-api.zendit.io/v1/esim/offers*' => Http::response([
                'list' => [['offerId' => 'a'], ['offerId' => 'b']],
                'total' => 2, 'limit' => 200, 'offset' => 0,
            ]),
        ]);

        $out = app(ZenditService::class)->getCatalogue();

        $this->assertCount(2, $out['list']);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://test-api.zendit.io/v1/esim/offers')
            && $r->hasHeader('Authorization', 'Bearer sand_test-key'));
    }

    public function test_order_bundle_posts_an_idempotent_purchase_and_reads_back_confirmation(): void
    {
        Http::fake([
            'test-api.zendit.io/v1/esim/purchases' => Http::response(['status' => 'ACCEPTED', 'transactionId' => 'x']),
            'test-api.zendit.io/v1/esim/purchases/*' => Http::response([
                'status' => 'DONE',
                'confirmation' => [
                    'iccid' => '8944000000000000001',
                    'activationCode' => 'LPA:1$smdp.zendit.io$MATCH-123',
                    'smdpAddress' => 'smdp.zendit.io',
                ],
            ]),
        ]);

        $out = app(ZenditService::class)->orderBundle('ng-voice-5gb');

        // Confirmation is flattened to the top level for checkout/LpaActivation.
        $this->assertSame('8944000000000000001', $out['iccid']);
        $this->assertSame('LPA:1$smdp.zendit.io$MATCH-123', $out['activationCode']);
        $this->assertSame(LpaActivation::fromPayload($out), $out['activationCode']);

        Http::assertSent(function ($r) {
            return $r->url() === 'https://test-api.zendit.io/v1/esim/purchases'
                && $r->method() === 'POST'
                && $r['offerId'] === 'ng-voice-5gb'
                && is_string($r['transactionId']) && str_starts_with($r['transactionId'], 'naara-');
        });
    }

    public function test_get_balance_scales_by_currency_divisor(): void
    {
        Http::fake([
            'test-api.zendit.io/v1/balance' => Http::response([
                'availableBalance' => 12345, 'currency' => 'USD', 'currencyDivisor' => 100,
            ]),
        ]);

        $this->assertSame(123.45, app(ZenditService::class)->getBalance());
    }

    public function test_a_failed_http_call_throws_a_typed_provider_exception(): void
    {
        Http::fake(['test-api.zendit.io/v1/balance' => Http::response(['message' => 'server error'], 500)]);

        $this->expectException(EsimProviderException::class);

        app(ZenditService::class)->getBalance();
    }
}
