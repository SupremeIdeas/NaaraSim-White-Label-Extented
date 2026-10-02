<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Livewire\Admin\GlobalPayoutRail;
use App\Models\AuditLog;
use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\PayoutExposureSnapshot;
use App\Models\PayoutFloatBalance;
use App\Models\PayoutRailEnrollment;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\CorridorRouter;
use App\Services\Payouts\Rail\FundingRadar;
use App\Services\Payouts\Rail\RadarAlerts;
use App\Services\Payouts\Rail\UnservedDemandReport;
use App\Services\Referrals\ReferralEarningsService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Funding Radar R4 (admin UI) and R5 (alerts, unserved demand). */
class GlobalPayoutRailAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true);
        Carbon::setTestNow('2026-10-05 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->assignRole('admin'));
    }

    private function enrolled(float $usd, string $country = 'KE', string $name = 'Person', string $provider = 'payoneer'): User
    {
        $u = User::factory()->create(['name' => $name, 'country_code' => $country, 'created_at' => now()->subYear()]);
        PayoutAccount::create(['user_id' => $u->id, 'type' => 'bank', 'country' => $country, 'currency' => 'USD', 'bank_code' => 'x',
            'account_number' => '1234567890123', 'account_name' => 'T', 'provider' => $provider, 'is_verified' => true, 'is_default' => true]);
        app(ReferralEarningsService::class)->accrue($u, User::factory()->create(), 'esim', $usd, 'seed:'.uniqid());

        return $u;
    }

    // ---- access ----

    public function test_only_admin_roles_reach_the_page(): void
    {
        Livewire::actingAs(User::factory()->create())->test(GlobalPayoutRail::class)->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.global-payout-rail'))->assertStatus(404);
        Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class)->assertOk();
    }

    public function test_every_tab_renders(): void
    {
        $this->enrolled(100);
        $c = Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class);
        foreach (['overview', 'users', 'live', 'planner', 'unserved'] as $tab) {
            $c->set('tab', $tab)->assertOk();
        }
    }

    // ---- overview ----

    public function test_overview_shows_exact_and_estimate_labels_and_reads_the_cache(): void
    {
        $u = $this->enrolled(100);
        $radar = app(FundingRadar::class);
        $radar->refreshCache('payoneer');

        $c = Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class)->assertSee('Exact')->assertSee('Estimate')->assertSee('$100.00');

        // The money tables change, the cache does not: the page keeps rendering the cached figure.
        PayoutRailEnrollment::query()->delete();
        $c->call('$refresh')->assertSee('$100.00');

        $radar->refreshCache('payoneer');
        $c->call('$refresh')->assertSee('$0.00');
    }

    public function test_a_stall_banner_appears_when_float_is_below_what_is_owed(): void
    {
        $this->enrolled(100);
        Setting::setValue(PayoutSettings::MODE, 'auto');
        app(\App\Services\Payouts\FloatService::class)->track('payoneer', 'USD', 10);
        app(FundingRadar::class)->refreshCache('payoneer');

        Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class)->assertSee('Payouts will stall');
    }

    // ---- users ----

    public function test_users_default_sort_is_balance_descending_and_the_footer_equals_the_visible_rows(): void
    {
        $this->enrolled(30, 'KE', 'Small');
        $this->enrolled(300, 'KE', 'Large');
        $this->enrolled(60, 'GH', 'Medium');

        $c = Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class)->set('tab', 'users');
        $c->assertSeeInOrder(['Large', 'Medium', 'Small'])->assertSee('Filtered total · 3 users');
        $this->assertSame(390.0, (float) $c->viewData('totals')['total']);

        $c->set('country', 'ke');
        $this->assertSame(330.0, (float) $c->viewData('totals')['total'], 'footer = total of the FILTERED set');
        $this->assertSame(array_sum(array_map(fn ($r) => (float) $r['total'], $c->viewData('rows'))), (float) $c->viewData('totals')['total']);

        $c->set('country', '')->call('sortBy', 'name')->assertSeeInOrder(['Small', 'Medium', 'Large'])
            ->set('minBalance', 50)->assertDontSee('Small');
    }

    public function test_filters_for_swept_next_run_and_kyc_blocked(): void
    {
        Setting::setValue(PayoutSettings::MODE, 'auto');
        Setting::setValue(PayoutSettings::FREE_COUNT, 1);
        $u = $this->enrolled(100, 'KE', 'Zorbatron');
        // One payout already taken: the free allowance is spent, so KYC-L2 is now required.
        PayoutRequest::create(['user_id' => $u->id, 'amount' => 5, 'usd_amount' => 5, 'currency' => 'USD', 'source_bucket' => 'referral_earnings', 'status' => 'paid', 'provider' => 'payoneer', 'reference' => 'k:1']);
        $c = Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class)->set('tab', 'users');

        $c->set('kycBlockedOnly', true);
        $this->assertCount(1, $c->viewData('rows'), json_encode($c->viewData('rows')[0] ?? null));
        $c->set('kycBlockedOnly', false)->set('sweepOnly', true)->assertDontSee('Zorbatron');
    }

    public function test_csv_export_is_audited_and_never_contains_a_full_account_number(): void
    {
        $this->enrolled(100);
        Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class)->call('export')->assertFileDownloaded();

        $this->assertTrue(AuditLog::where('action', 'payout.rail_export')->exists());

        $component = new GlobalPayoutRail;
        $csv = (function () { return $this->rows(); })->call($component);
        $this->assertStringNotContainsString('1234567890123', json_encode($csv));
        $this->assertStringContainsString('0123', json_encode($csv), 'masked: only the last four');
    }

    public function test_an_admin_can_pause_and_resume_an_enrollment(): void
    {
        $this->enrolled(100);
        $e = PayoutRailEnrollment::first();
        $c = Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class)->set('tab', 'users');

        $c->call('pause', $e->id);
        $this->assertSame('paused', $e->fresh()->status);
        $c->call('resume', $e->id);
        $this->assertSame('active', $e->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'payout.rail_paused')->exists());
    }

    // ---- planner ----

    public function test_recording_a_top_up_needs_a_note_updates_float_and_re_runs_the_radar(): void
    {
        $this->enrolled(100);
        Setting::setValue(PayoutSettings::MODE, 'auto');
        $c = Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class)->set('tab', 'planner')
            ->set('topupCurrency', 'USD')->set('topupAmount', 100)->set('topupNote', '')->call('recordTopUp')->assertHasErrors('topupNote');

        $c->set('topupNote', 'wire ref 77')->call('recordTopUp')->assertHasNoErrors();

        $this->assertEquals(100, PayoutFloatBalance::where('provider', 'payoneer')->first()->balance);
        $this->assertSame('100.0000', Cache::get('payout:radar:payoneer')['float_available']);
        $this->assertTrue(AuditLog::where('action', 'payout.float_topup')->exists());
    }

    public function test_what_if_scales_the_exposure(): void
    {
        $this->enrolled(200);
        $c = Livewire::actingAs($this->admin())->test(GlobalPayoutRail::class)->set('tab', 'planner')->set('whatIfPct', 25);
        $this->assertSame('50.0000', $c->viewData('planner')['what_if']);
    }

    // ---- alerts ----

    public function test_float_short_alerts_once_per_hour(): void
    {
        $this->enrolled(100);
        Setting::setValue(PayoutSettings::MODE, 'auto');
        app(\App\Services\Payouts\FloatService::class)->track('payoneer', 'USD', 10);
        app(FundingRadar::class)->refreshCache('payoneer');
        PayoutExposureSnapshot::create(['captured_at' => now(), 'provider' => 'payoneer']);
        Queue::fake();

        $first = app(RadarAlerts::class)->run();
        $second = app(RadarAlerts::class)->run();

        $this->assertContains('rail_float_short', $first);
        $this->assertNotContains('rail_float_short', $second, 'throttled for an hour');
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'rail_float_short');
    }

    public function test_concentration_alert_fires_when_one_user_dominates(): void
    {
        $this->enrolled(900, 'KE', 'Whale');
        $this->enrolled(100, 'KE', 'Minnow');
        PayoutExposureSnapshot::create(['captured_at' => now(), 'provider' => 'payoneer']);
        Queue::fake();

        $this->assertContains('rail_concentration', app(RadarAlerts::class)->run());
    }

    public function test_velocity_spike_and_stale_snapshot_alerts(): void
    {
        $u = $this->enrolled(100);
        foreach (range(1, 6) as $i) {
            PayoutRequest::create(['user_id' => $u->id, 'amount' => 1, 'usd_amount' => 1, 'currency' => 'USD', 'source_bucket' => 'referral_earnings', 'status' => 'pending', 'provider' => 'payoneer', 'reference' => "v:{$i}"]);
        }
        PayoutExposureSnapshot::create(['captured_at' => now()->subHour(), 'provider' => 'payoneer']);   // stale
        Queue::fake();

        $raised = app(RadarAlerts::class)->run();

        $this->assertContains('rail_velocity_spike', $raised);
        $this->assertContains('rail_stale_snapshot', $raised);
    }

    public function test_a_high_failure_rate_alerts_and_takes_the_provider_out_of_routing(): void
    {
        config(['services.paystack.secret_key' => 'sk_test']);
        $u = $this->enrolled(10, 'NG', 'N', 'paystack');
        foreach (range(1, 12) as $i) {
            PayoutRequest::create(['user_id' => $u->id, 'amount' => 1, 'usd_amount' => 1, 'currency' => 'NGN', 'source_bucket' => 'referral_earnings',
                'status' => $i <= 4 ? 'failed' : 'paid', 'provider' => 'paystack', 'reference' => "f:{$i}"]);
        }
        PayoutRailEnrollment::create(['user_id' => $u->id, 'provider' => 'paystack', 'country' => 'NG', 'status' => 'active']);
        PayoutExposureSnapshot::create(['captured_at' => now(), 'provider' => 'paystack']);
        $this->assertNotNull(app(CorridorRouter::class)->pick('NG', 'NGN'));
        Queue::fake();

        $this->assertContains('rail_failure_rate', app(RadarAlerts::class)->run());

        $this->assertTrue(RadarAlerts::isUnhealthy('paystack'));
        $options = app(CorridorRouter::class)->optionsFor('NG', 'NGN')->pluck('provider')->all();
        $this->assertNotContains('paystack', $options, 'an unhealthy rail is not offered');
    }

    public function test_a_quiet_rail_raises_nothing(): void
    {
        $this->enrolled(100);
        app(FundingRadar::class)->refreshCache('payoneer');
        PayoutExposureSnapshot::create(['captured_at' => now(), 'provider' => 'payoneer']);
        Queue::fake();

        $this->assertSame([], array_values(array_diff(app(RadarAlerts::class)->run(), ['rail_concentration'])));
    }

    // ---- unserved demand ----

    public function test_unserved_demand_lists_only_countries_without_an_enabled_corridor(): void
    {
        $this->enrolled(80, 'RW', 'Rwanda user', 'paystack');    // seeded RW corridor is DISABLED -> unserved
        $this->enrolled(40, 'RW', 'Another', 'paystack');
        $this->enrolled(500, 'NG', 'Nigeria user', 'paystack'); // NG corridors are enabled -> served
        $this->enrolled(0, 'ZA', 'Nothing', 'paystack');         // no balance -> ignored

        $rows = app(UnservedDemandReport::class)->get(fresh: true);

        $this->assertSame([['country' => 'RW', 'users' => 2, 'usd' => '120.0000', 'notify_requests' => 0]], $rows);
    }
}
