<?php

namespace Tests\Feature;

use App\Services\SMS\PlivoService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prompt 12 §1 — PlivoService::buyNumber() used to hardcode
 * `'capabilities' => ['sms' => true, 'voice' => true]` on every number
 * regardless of country, even though Plivo's own published coverage has no
 * African inbound voice. This is the exact failure mode the whole updater/
 * provider-routing project has been trying to prevent: selling a capability
 * the provider can't actually deliver. The fix reads the REAL voice_enabled/
 * sms_enabled flags off Plivo's own AccountPhoneNumber object (a follow-up
 * GET right after the buy call, since Plivo's buy response itself carries no
 * capability data) — never a blanket claim.
 */
class PlivoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.plivo.auth_id' => 'MAtest', 'services.plivo.auth_token' => 'tok']);
    }

    public function test_buy_number_reports_the_real_voice_capability_when_false(): void
    {
        Http::fake([
            '*/PhoneNumber/*' => Http::response([
                'api_id' => 'abc', 'message' => 'created',
                'numbers' => [['number' => '2348000000000', 'status' => 'Success']],
                'status' => 'fulfilled',
            ], 201),
            '*/Number/*' => Http::response([
                'number' => '2348000000000', 'sms_enabled' => true, 'voice_enabled' => false,
            ], 200),
        ]);

        $bought = app(PlivoService::class)->buyNumber('NG', ['number' => '2348000000000']);

        $this->assertSame(['sms' => true, 'voice' => false], $bought['capabilities']);
    }

    public function test_buy_number_reports_real_voice_capability_when_true(): void
    {
        Http::fake([
            '*/PhoneNumber/*' => Http::response([
                'api_id' => 'abc', 'message' => 'created',
                'numbers' => [['number' => '17609915566', 'status' => 'Success']],
                'status' => 'fulfilled',
            ], 201),
            '*/Number/*' => Http::response([
                'number' => '17609915566', 'sms_enabled' => true, 'voice_enabled' => true,
            ], 200),
        ]);

        $bought = app(PlivoService::class)->buyNumber('US', ['number' => '17609915566']);

        $this->assertSame(['sms' => true, 'voice' => true], $bought['capabilities']);
    }

    public function test_a_failed_capability_lookup_defaults_to_no_voice_never_true(): void
    {
        Http::fake([
            '*/PhoneNumber/*' => Http::response([
                'api_id' => 'abc', 'message' => 'created',
                'numbers' => [['number' => '2348000000000', 'status' => 'Success']],
                'status' => 'fulfilled',
            ], 201),
            // An empty/unreadable response body — neither field can be
            // confirmed, so the safe defaults inside data_get() apply.
            '*/Number/*' => Http::response([], 500),
        ]);

        $bought = app(PlivoService::class)->buyNumber('NG', ['number' => '2348000000000']);

        // Never claim a capability we couldn't confirm — the safe default is
        // SMS-only, the opposite of the old hardcoded 'voice' => true bug.
        $this->assertSame(['sms' => true, 'voice' => false], $bought['capabilities']);
    }
}
