<?php

namespace Tests\Feature;

use App\Exceptions\SmsException;
use App\Services\SMS\Numbers\VonageService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prompt 12 §2 — VonageService against the documented legacy REST API
 * (rest.nexmo.com). NumberProviderInterface only; VoiceProviderInterface is
 * deliberately not implemented (see the class docblock / PROGRESS.md).
 */
class VonageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.vonage.api_key' => 'key123', 'services.vonage.api_secret' => 'secret456']);
    }

    public function test_search_numbers_maps_the_real_response_shape(): void
    {
        Http::fake([
            '*/number/search*' => Http::response([
                'count' => 1,
                'numbers' => [[
                    'country' => 'NG', 'msisdn' => '2348011112222', 'type' => 'mobile-lvn',
                    'features' => ['SMS', 'VOICE'], 'cost' => '1.25',
                ]],
            ]),
        ]);

        $numbers = app(VonageService::class)->searchNumbers('NG');

        $this->assertSame([
            ['number' => '2348011112222', 'locality' => '', 'monthly_cost' => 1.25],
        ], $numbers);
    }

    public function test_buy_number_reports_real_capabilities_from_a_fresh_lookup(): void
    {
        Http::fakeSequence()
            ->push(['error-code' => '200', 'error-code-label' => 'success']) // /number/buy
            ->push(['numbers' => [['msisdn' => '2348011112222', 'features' => ['SMS']]]]); // capability re-check

        $bought = app(VonageService::class)->buyNumber('NG', ['number' => '2348011112222']);

        $this->assertSame(['sms' => true, 'voice' => false], $bought['capabilities']);
        $this->assertSame('2348011112222', $bought['number']);
    }

    public function test_a_failed_buy_throws_rather_than_silently_succeeding(): void
    {
        Http::fake(['*/number/buy*' => Http::response(['error-code' => '401', 'error-code-label' => 'authentication failed'])]);

        $this->expectException(SmsException::class);
        app(VonageService::class)->buyNumber('NG', ['number' => '2348011112222']);
    }

    public function test_send_sms_reports_the_real_message_id(): void
    {
        Http::fake(['*/sms/json*' => Http::response([
            'messages' => [['status' => '0', 'message-id' => 'ABC123']],
        ])]);

        $result = app(VonageService::class)->sendSms('447700900000', '2348011112222', 'hi');

        $this->assertSame('ABC123', $result['provider_ref']);
        $this->assertSame('sent', $result['status']);
    }

    public function test_send_sms_throws_on_a_non_zero_status(): void
    {
        Http::fake(['*/sms/json*' => Http::response([
            'messages' => [['status' => '2', 'error-text' => 'Missing params']],
        ])]);

        $this->expectException(SmsException::class);
        app(VonageService::class)->sendSms('447700900000', '2348011112222', 'hi');
    }
}
