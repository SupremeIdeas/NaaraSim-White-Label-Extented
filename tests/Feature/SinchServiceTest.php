<?php

namespace Tests\Feature;

use App\Exceptions\SmsException;
use App\Services\SMS\Numbers\SinchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prompt 12 §3 — SinchService against the documented Numbers API v1
 * (numbers.api.sinch.com) + legacy XMS SMS API. NumberProviderInterface
 * only — VoiceProviderInterface deliberately not implemented (Sinch's
 * African voice coverage couldn't be independently confirmed in this
 * environment; see the class docblock / PROGRESS.md).
 */
class SinchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.sinch.client_id' => 'client123',
            'services.sinch.client_secret' => 'secret456',
            'services.sinch.project_id' => 'proj-789',
            'services.sinch.api_token' => 'bearer-token',
            'services.sinch.service_plan_id' => 'plan-abc',
        ]);
    }

    public function test_search_numbers_maps_the_real_response_shape(): void
    {
        Http::fake([
            '*/availableNumbers*' => Http::response([
                'availableNumbers' => [[
                    'phoneNumber' => '+2348011112222', 'regionCode' => 'NG', 'type' => 'MOBILE',
                    'capability' => ['SMS'], 'monthlyPrice' => ['currencyCode' => 'USD', 'amount' => '1.50'],
                ]],
            ]),
        ]);

        $numbers = app(SinchService::class)->searchNumbers('NG');

        $this->assertSame([
            ['number' => '+2348011112222', 'locality' => '', 'monthly_cost' => 1.50],
        ], $numbers);
    }

    public function test_buy_number_reports_the_real_capability_array_never_a_blanket_claim(): void
    {
        Http::fake(['*:rent*' => Http::response([
            'phoneNumber' => '+2348011112222', 'capability' => ['SMS'],
        ])]);

        $bought = app(SinchService::class)->buyNumber('NG', ['number' => '+2348011112222']);

        $this->assertSame(['sms' => true, 'voice' => false], $bought['capabilities']);
        $this->assertSame('+2348011112222', $bought['provider_ref']);
    }

    public function test_buy_number_reports_voice_true_only_when_the_response_confirms_it(): void
    {
        Http::fake(['*:rent*' => Http::response([
            'phoneNumber' => '15550001234', 'capability' => ['SMS', 'VOICE'],
        ])]);

        $bought = app(SinchService::class)->buyNumber('US', ['number' => '15550001234']);

        $this->assertSame(['sms' => true, 'voice' => true], $bought['capabilities']);
    }

    public function test_a_failed_buy_throws(): void
    {
        Http::fake(['*:rent*' => Http::response([], 422)]);

        $this->expectException(SmsException::class);
        app(SinchService::class)->buyNumber('NG', ['number' => '+2348011112222']);
    }

    public function test_send_sms_uses_the_sms_api_bearer_token_not_the_numbers_client_credentials(): void
    {
        Http::fake(['*/batches' => Http::response(['id' => 'batch-1', 'to' => ['+2348011112222']])]);

        $result = app(SinchService::class)->sendSms('+15550009999', '+2348011112222', 'hi');

        $this->assertSame('batch-1', $result['provider_ref']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer bearer-token'));
    }
}
