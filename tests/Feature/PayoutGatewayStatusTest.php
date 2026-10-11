<?php

namespace Tests\Feature;

use App\Services\Payouts\Health\GatewayStatus;
use App\Services\Payouts\Health\GatewayStatusResolver;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\Rail\RadarAlerts;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Engineering v3 Phase 2 (generic part): payout gateways report WHY they are not usable, and members only ever see calm reasons. */
class PayoutGatewayStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
        config(['payouts.env' => 'sandbox']);
        app(PayoutService::class);
    }

    private function resolver(): GatewayStatusResolver
    {
        return app(GatewayStatusResolver::class);
    }

    public function test_the_provider_list_comes_from_the_registry_and_includes_planned_rails(): void
    {
        $all = $this->resolver()->providers();
        foreach (['paystack', 'flutterwave', 'stripe', 'paypal', 'cryptomus'] as $p) {
            $this->assertContains($p, $all);
        }
        $this->assertContains('payoneer', $all, 'a planned rail that is not installed still shows (as not installed)');
    }

    public function test_each_cause_has_its_own_reason_and_fix(): void
    {
        config(['services.paystack.secret_key' => '']);
        $s = $this->resolver()->statusOf('paystack');
        $this->assertSame([GatewayStatus::R_NO_KEY, false], [$s->reason, $s->usable()]);

        config(['services.paystack.secret_key' => 'sk_live_abc']);
        $this->assertSame(GatewayStatus::R_ENV_MISMATCH, $this->resolver()->statusOf('paystack')->reason);

        config(['services.paystack.secret_key' => 'sk_test_abc']);
        $this->assertTrue($this->resolver()->statusOf('paystack')->usable());

        Cache::put(RadarAlerts::UNHEALTHY_KEY.'paystack', true, 600);
        $s = $this->resolver()->statusOf('paystack');
        $this->assertSame([GatewayStatus::R_BREAKER, 'temporarily_unavailable'], [$s->reason, $s->userReason()]);
    }

    public function test_members_only_ever_see_calm_reasons_and_the_automation_checklist_names_the_real_cause(): void
    {
        config(['services.paystack.secret_key' => '']);
        $this->assertSame('not_open_yet', $this->resolver()->statusOf('paystack')->userReason());
        $this->assertNotSame('payout_guide.reason_not_open_yet', __('payout_guide.reason_not_open_yet'));

        config(['services.paystack.secret_key' => 'sk_live_abc', 'services.flutterwave.secret_key' => '', 'services.stripe.secret_key' => '', 'services.paypal.client_id' => '', 'services.cryptomus.merchant_id' => '']);
        $row = collect(app(\App\Services\Payouts\Hardening\AutomationStatus::class)->report()['checks'])->firstWhere('label', 'At least one payout provider is ready');
        $this->assertNotNull($row);
        $this->assertStringContainsString('LIVE key', (string) $row['fix']);
    }
}
