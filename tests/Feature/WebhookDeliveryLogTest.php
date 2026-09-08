<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Laravel readiness Domain 13/14 — the inbound webhook delivery log. Observability
 * only: it records every webhooks/* hit and never changes the handler's outcome.
 */
class WebhookDeliveryLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_webhook_hit_is_recorded_with_provider_and_status(): void
    {
        // The getatext webhook rejects an unsigned/empty body — but the delivery
        // is still logged (that's the point: we can see it was attempted).
        $this->post('/webhooks/getatext', []);

        $row = DB::table('webhook_deliveries')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('getatext', $row->provider);
        $this->assertSame('webhooks/getatext', $row->path);
        $this->assertIsNumeric($row->status_code);
    }

    public function test_non_webhook_routes_are_not_logged(): void
    {
        $this->get('/')->assertOk();
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }
}
