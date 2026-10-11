<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Livewire\Admin\PayoutRailGuide;
use App\Livewire\Admin\Payouts;
use App\Models\AuditLog;
use App\Models\PayoutAccount;
use App\Models\PayoutCorridor;
use App\Models\PayoutCountryRail;
use App\Models\PayoutGuideEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\Rail\PayoutRailRegistry;
use App\Services\Payouts\Rail\RailCopy;
use App\Services\Payouts\Rail\RailGuideReport;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Rail Guide G5: admin matrix editor, settings, audit/re-seed, preview, funnel report. */
class PayoutRailGuideAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
        config(['services.paystack.secret_key' => 'sk_p', 'services.paystack.base_url' => 'https://api.paystack.test', 'services.stripe.secret_key' => '']);
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->assignRole('admin'));
    }

    public function test_only_admins_reach_it_and_it_renders_inside_the_payouts_tab(): void
    {
        Livewire::actingAs(User::factory()->create())->test(PayoutRailGuide::class)->assertForbidden();
        Livewire::actingAs($this->admin())->test(PayoutRailGuide::class)->assertOk()->assertSee('Country x rail matrix');
        Livewire::actingAs($this->admin())->test(Payouts::class)->set('tab', 'guide')->assertSee('Guide funnel');
    }

    public function test_an_override_is_audited_flushes_the_cache_and_survives_a_reseed(): void
    {
        Http::fake(['api.paystack.test/country' => Http::response(['data' => []])]);
        $registry = app(PayoutRailRegistry::class);
        $this->assertSame('coming_soon', $registry->state('KE', 'paystack'));       // provider covers KE, no corridor yet

        $c = Livewire::actingAs($this->admin())->test(PayoutRailGuide::class)->set('country', 'KE')->call('setOverride', 'paystack', 'force_on');
        $this->assertSame('available', $registry->state('KE', 'paystack'), 'cache flushed, override applied');
        $this->assertTrue(AuditLog::where('action', 'payouts.guide_override')->exists());

        $c->call('reseed');
        $this->assertSame('force_on', PayoutCountryRail::where('country', 'KE')->where('rail', 'paystack')->value('admin_override'), 're-seed never overwrites an override');
        $this->assertSame('available', $registry->state('KE', 'paystack'));
    }

    public function test_overrides_reject_unknown_values(): void
    {
        Livewire::actingAs($this->admin())->test(PayoutRailGuide::class)->call('setOverride', 'paystack', 'nuke')->assertStatus(422);
    }

    public function test_settings_save_audit_and_drive_the_user_copy(): void
    {
        Livewire::actingAs($this->admin())->test(PayoutRailGuide::class)
            ->set('allowGlobal', true)->set('globalDays', 21)->set('guideVersion', 3)->set('etaText.paystack', 'Within the hour')
            ->call('saveSettings')->assertHasNoErrors();

        $this->assertTrue((bool) Setting::getValue('payouts.global_rail.allow_when_local_available'));
        $this->assertSame(21, RailCopy::globalDays());
        $this->assertSame(3, RailCopy::guideVersion());
        $this->assertSame('Within the hour', app(RailCopy::class)->eta('paystack')['text']);
        $this->assertTrue(AuditLog::where('action', 'payouts.guide_settings_updated')->exists());
    }

    public function test_settings_are_validated(): void
    {
        Livewire::actingAs($this->admin())->test(PayoutRailGuide::class)->set('globalDays', 0)->call('saveSettings')->assertHasErrors('globalDays');
    }

    public function test_a_per_country_eta_override_wins_over_the_defaults(): void
    {
        Livewire::actingAs($this->admin())->test(PayoutRailGuide::class)->set('country', 'KE')->set('etaMin', 1)->set('etaMax', 6)->call('saveEta', 'paystack');

        $this->assertSame('1 h – 6 h', app(RailCopy::class)->eta('paystack', 'KE')['text']);
        $this->assertNotSame('1 h – 6 h', app(RailCopy::class)->eta('paystack', 'NG')['text']);
    }

    public function test_run_audit_raises_the_drift_alert_and_reports_stale_rows(): void
    {
        Queue::fake();
        Http::fake(['api.paystack.test/country' => Http::response(['data' => [['iso_code' => 'NG']]])]);   // provider "lost" the other Paystack countries

        $c = Livewire::actingAs($this->admin())->test(PayoutRailGuide::class)->call('runAudit');

        $this->assertStringContainsString('disagreement', $c->get('auditResult'));
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'guide_registry_drift');
        $this->assertTrue(AuditLog::where('action', 'payouts.guide_audit_run')->exists());
    }

    public function test_preview_simulates_without_writing_anything(): void
    {
        PayoutCorridor::updateOrCreate(['country' => 'KE', 'currency' => 'KES', 'provider' => 'paystack', 'method' => 'bank'], ['enabled' => true, 'priority' => 10]);
        $admin = $this->admin();
        $events = PayoutGuideEvent::count();
        $users = User::count();

        $c = Livewire::actingAs($admin)->test(PayoutRailGuide::class)->set('previewCountry', 'KE')->call('runPreview');

        $this->assertSame('fast_available', $c->get('preview')['verdict']);
        $this->assertContains('paystack = recommended', $c->get('preview')['options']);
        $this->assertSame([$events, $users], [PayoutGuideEvent::count(), User::count()]);
    }

    public function test_the_funnel_report_reads_a_seeded_funnel(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        foreach ([$a, $b, $c] as $u) {
            PayoutGuideEvent::create(['user_id' => $u->id, 'event' => 'guide_viewed', 'country' => 'KE']);
            PayoutGuideEvent::create(['user_id' => $u->id, 'event' => 'rail_recommended', 'country' => 'KE', 'rail' => 'paystack']);
        }
        PayoutGuideEvent::create(['user_id' => $a->id, 'event' => 'rail_selected', 'country' => 'KE', 'rail' => 'paystack']);   // followed
        PayoutGuideEvent::create(['user_id' => $b->id, 'event' => 'rail_selected', 'country' => 'KE', 'rail' => 'flutterwave']); // did not
        PayoutGuideEvent::create(['user_id' => $c->id, 'event' => 'blocked_notify_requested', 'country' => 'RW']);
        PayoutAccount::create(['user_id' => $a->id, 'type' => 'bank', 'country' => 'KE', 'currency' => 'KES', 'bank_code' => '1', 'account_number' => '1',
            'account_name' => 'A', 'provider' => 'paystack', 'is_verified' => true, 'is_default' => true]);

        $r = app(RailGuideReport::class)->build();

        $this->assertSame(3, $r['viewers']);
        $this->assertSame(['paystack' => 1, 'flutterwave' => 1], $r['selections_by_rail']);
        $this->assertSame(33.3, $r['followed_pct'], '1 of 3 recommended users picked the recommended rail');
        $this->assertSame(33.3, $r['conversion_pct']);
        $this->assertSame(['RW' => 1], $r['notify_by_country']);
    }
}
