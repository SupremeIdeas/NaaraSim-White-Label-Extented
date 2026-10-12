<?php

namespace Tests\Feature;

use App\Jobs\AssignTelnyxMessagingProfileJob;
use App\Services\SMS\Numbers\TelnyxService;
use App\Support\ProviderKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Provider blueprint 3.1 / 3.2: a bought Telnyx number is put on the Messaging Profile, and the webhook token and profile id are configurable. */
class TelnyxMessagingProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telnyx.api_key' => 'k', 'services.telnyx.messaging_profile_id' => 'prof-123']);
    }

    public function test_buying_a_number_queues_the_profile_assignment(): void
    {
        Bus::fake();
        Http::fake(['api.telnyx.com/v2/number_orders' => Http::response(['data' => ['id' => 'ord1', 'phone_numbers' => [['id' => 'pn1', 'phone_number' => '+12025550123']]]])]);
        $r = (new TelnyxService)->buyNumber('US', ['number' => '+12025550123']);
        $this->assertSame('+12025550123', $r['number']);
        Bus::assertDispatched(AssignTelnyxMessagingProfileJob::class, fn ($j) => $j->e164 === '+12025550123');
    }

    public function test_no_profile_configured_queues_nothing(): void
    {
        config(['services.telnyx.messaging_profile_id' => null]);
        Bus::fake();
        Http::fake(['api.telnyx.com/v2/number_orders' => Http::response(['data' => ['id' => 'ord1', 'phone_numbers' => [['id' => 'pn1', 'phone_number' => '+12025550123']]]])]);
        (new TelnyxService)->buyNumber('US', ['number' => '+12025550123']);
        Bus::assertNotDispatched(AssignTelnyxMessagingProfileJob::class);
    }

    public function test_assignment_looks_up_the_number_then_patches_the_profile(): void
    {
        Http::fake([
            'api.telnyx.com/v2/phone_numbers?*' => Http::response(['data' => [['id' => 'pn1']]]),
            'api.telnyx.com/v2/phone_numbers/pn1/messaging' => Http::response(['data' => ['id' => 'pn1']]),
        ]);
        $this->assertTrue((new TelnyxService)->assignMessagingProfile('+12025550123'));
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/phone_numbers/pn1/messaging') && $r['messaging_profile_id'] === 'prof-123');
    }

    public function test_a_pending_order_is_not_assigned_yet_and_the_job_retries(): void
    {
        Http::fake(['api.telnyx.com/v2/phone_numbers?*' => Http::response(['data' => []])]);
        $this->assertFalse((new TelnyxService)->assignMessagingProfile('+12025550123'));
        $this->expectException(\RuntimeException::class);
        (new AssignTelnyxMessagingProfileJob('+12025550123'))->handle(new TelnyxService);
    }

    public function test_the_secrets_screen_offers_both_fields(): void
    {
        $keys = json_encode(ProviderKeys::schema());
        $this->assertStringContainsString('TELNYX_MESSAGING_PROFILE_ID', $keys);
        $this->assertStringContainsString('TELNYX_WEBHOOK_TOKEN', $keys);
    }
}
