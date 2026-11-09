<?php

namespace Tests\Feature;

use App\Livewire\Topup as TopupPage;
use App\Livewire\TopupBeneficiaries;
use App\Models\Setting;
use App\Models\TopupAutoReload;
use App\Models\TopupBeneficiary;
use App\Models\TopupNumberOperator;
use App\Models\TopupOrder;
use App\Models\TopupProduct;
use App\Models\TopupProvider;
use App\Models\TopupRoute;
use App\Models\User;
use App\Services\Topup\NumberResolver;
use App\Services\Topup\TopupAutoReloadService;
use App\Services\Topup\TopupOps;
use App\Services\Topup\TopupSnapshot;
use App\Services\Wallet\WalletService;
use App\Support\TopupHeroContent;
use App\Support\TopupLogos;
use App\Support\TopupSettings;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Support\FakeTopupProvider;
use Tests\TestCase;

/** Number resolution, beneficiaries, auto top-ups (daily/weekly/monthly), the offers snapshot, logos for the Nigerian networks, hero content. */
class TopupExtrasTest extends TestCase
{
    use RefreshDatabase;

    private FakeTopupProvider $zendit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        config(['queue.default' => 'sync', 'services.zendit.api_key' => 'k']);
        $this->zendit = new FakeTopupProvider('zendit');
        $this->app->instance('topup.provider.zendit', $this->zendit);
        TopupRoute::query()->delete();
        TopupRoute::create(['country_iso' => '*', 'product_type' => '*', 'provider_code' => 'zendit', 'priority' => 1]);
        TopupProvider::create(['code' => 'zendit', 'name' => 'Zendit', 'enabled' => true]);
        Setting::setValue('features.naara_topup.enabled', true);
        TopupSettings::set('daily_cap_usd', 1000);
        TopupSettings::set('new_account_daily_cap_usd', 1000);
        Cache::flush();
    }

    private function ng(): void
    {
        foreach (['MTN', 'Glo', 'Airtel', 'Nine Mobile'] as $op) {
            TopupProduct::create(['provider_code' => 'zendit', 'provider_sku' => 'a-'.$op, 'country_iso' => 'NG', 'operator_name' => $op, 'type' => 'airtime', 'name' => 'Airtime', 'currency' => 'NGN',
                'is_fixed' => false, 'min_amount' => 50, 'max_amount' => 50000, 'cost_ratio' => 0.0007, 'cost_currency' => 'USD', 'active' => true]);
            TopupProduct::create(['provider_code' => 'zendit', 'provider_sku' => 'd-'.$op, 'country_iso' => 'NG', 'operator_name' => $op, 'type' => 'data', 'name' => '1GB (7 days)', 'currency' => 'NGN',
                'is_fixed' => true, 'fixed_amount' => 1000, 'cost_native' => 0.7, 'cost_currency' => 'USD', 'validity_text' => '7 days', 'active' => true]);
        }
    }

    private function user(float $usd = 100): User
    {
        $u = User::factory()->create(['is_active' => true, 'created_at' => now()->subMonths(2)]);
        app(WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'seed:'.$u->id]);

        return $u;
    }

    // ── Number resolution ───────────────────────────────────────────────────
    public function test_nigerian_numbers_resolve_to_their_network_in_every_common_format(): void
    {
        $this->ng();
        $r = app(NumberResolver::class);
        foreach ([['08031234567', 'MTN'], ['+2348031234567', 'MTN'], ['0803 123 4567', 'MTN'], ['2348031234567', 'MTN'], ['07038881234', 'MTN'], ['08051234567', 'Glo'], ['0705 123 4567', 'Glo'],
            ['08021234567', 'Airtel'], ['09021234567', 'Airtel'], ['08091234567', 'Nine Mobile'], ['09091234567', 'Nine Mobile']] as [$n, $op]) {
            $x = $r->resolve($n, 'NG');
            $this->assertTrue($x['valid'], $n);
            $this->assertSame('NG', $x['country']);
            $this->assertSame($op, $x['operator'], "$n should be $op");
            $this->assertSame('+234', substr($x['e164'], 0, 4));
        }
    }

    public function test_country_is_read_from_the_number_itself(): void
    {
        $this->ng();
        $x = app(NumberResolver::class)->resolve('+233244123456', 'NG');   // typed with + : the hint is ignored
        $this->assertSame('GH', $x['country']);
        $this->assertSame('+233244123456', $x['e164']);
    }

    public function test_a_number_that_is_not_valid_is_refused_with_a_clear_message(): void
    {
        $x = app(NumberResolver::class)->resolve('0803', 'NG');
        $this->assertFalse($x['valid']);
        $y = app(NumberResolver::class)->resolve('080312345', 'NG');
        $this->assertFalse($y['valid']);
        $this->assertNotNull($y['error']);
    }

    public function test_a_delivered_topup_teaches_the_resolver_that_a_number_has_moved_network(): void
    {
        $this->ng();
        $r = app(NumberResolver::class);
        $this->assertSame('MTN', $r->resolve('08031234567', 'NG')['operator']);
        $r->learn('+2348031234567', 'NG', 'Airtel');                       // the number was ported to Airtel
        $x = $r->resolve('08031234567', 'NG');
        $this->assertSame('Airtel', $x['operator']);
        $this->assertSame('confirmed', $x['source']);
        $this->assertTrue($x['sure']);
    }

    public function test_a_saved_beneficiary_network_beats_the_prefix(): void
    {
        $this->ng();
        $u = $this->user();
        TopupBeneficiary::create(['user_id' => $u->id, 'name' => 'Tunde', 'phone_e164' => '+2348031234567', 'country_iso' => 'NG', 'operator_name' => 'Glo']);
        $this->assertSame('Glo', app(NumberResolver::class)->resolve('08031234567', 'NG', $u->id)['operator']);
        $this->assertSame('MTN', app(NumberResolver::class)->resolve('08031234567', 'NG')['operator']);
    }

    public function test_carrier_names_map_to_supplier_operator_names(): void
    {
        $r = app(NumberResolver::class);
        $this->assertSame('Nine Mobile', $r->match('9mobile', ['MTN', 'Nine Mobile'], 'NG'));
        $this->assertSame('Nine Mobile', $r->match('Etisalat', ['MTN', 'Nine Mobile'], 'NG'));
        $this->assertSame('Telecel (formerly Vodafone)', $r->match('Vodafone', ['MTN', 'Telecel (formerly Vodafone)'], 'GH'));
        $this->assertNull($r->match('Nonexistent', ['MTN'], 'NG'));
    }

    public function test_the_page_detects_the_network_and_lists_both_services(): void
    {
        $this->ng();
        $u = $this->user();
        Livewire::actingAs($u)->test(TopupPage::class)->set('country', 'NG')->set('phone', '0803 123 4567')
            ->assertSet('operator', 'MTN')->assertSet('e164', '+2348031234567')->assertSee('Airtime')->assertSee('Data');
        Livewire::actingAs($u)->test(TopupPage::class)->set('country', 'NG')->set('phone', '+233244123456')->assertSet('country', 'GH');
    }

    // ── Beneficiaries + auto top-up ─────────────────────────────────────────
    public function test_schedule_maths_daily_weekly_monthly(): void
    {
        $mk = fn ($f, $o = []) => new TopupAutoReload($o + ['frequency' => $f, 'run_hour' => 9, 'timezone' => 'UTC']);
        $from = \Illuminate\Support\Carbon::parse('2027-03-10 10:00:00', 'UTC');                 // a Wednesday, after 09:00
        $this->assertSame('2027-03-11 09:00', $mk('daily')->computeNext($from)->format('Y-m-d H:i'));
        $this->assertSame('2027-03-15 09:00', $mk('weekly', ['run_weekday' => 1])->computeNext($from)->format('Y-m-d H:i'));   // next Monday
        $this->assertSame('2027-03-17 09:00', $mk('weekly', ['run_weekday' => 3])->computeNext($from)->format('Y-m-d H:i'));   // Wednesday already passed today
        $this->assertSame('2027-04-05 09:00', $mk('monthly', ['run_monthday' => 5])->computeNext($from)->format('Y-m-d H:i'));
        $this->assertSame('2027-03-20 09:00', $mk('monthly', ['run_monthday' => 20])->computeNext($from)->format('Y-m-d H:i'));
        $this->assertSame('2027-03-10 08:00', $mk('daily', ['run_hour' => 9, 'timezone' => 'Africa/Lagos'])->computeNext(\Illuminate\Support\Carbon::parse('2027-03-09 12:00', 'UTC'))->format('Y-m-d H:i'), 'a 09:00 Lagos run is 08:00 UTC');
    }

    private function plan(User $u, array $o = []): TopupAutoReload
    {
        $b = TopupBeneficiary::create(['user_id' => $u->id, 'name' => 'Mum', 'phone_e164' => '+2348031234567', 'country_iso' => 'NG', 'operator_name' => 'MTN']);

        return TopupAutoReload::create($o + ['user_id' => $u->id, 'beneficiary_id' => $b->id, 'country_iso' => 'NG', 'operator_name' => 'MTN', 'product_type' => 'airtime', 'send_amount' => 1000,
            'send_currency' => 'NGN', 'frequency' => 'daily', 'run_hour' => 9, 'timezone' => 'UTC', 'next_run_at' => now()->subMinute(), 'active' => true]);
    }

    public function test_a_due_auto_topup_sends_once_charges_once_and_reschedules(): void
    {
        $this->ng();
        $u = $this->user(50);
        $a = $this->plan($u);
        $this->assertSame(1, app(TopupAutoReloadService::class)->runDue());
        $this->assertSame(1, TopupOrder::count());
        $this->assertSame(TopupOrder::DELIVERED, TopupOrder::first()->status);
        $a->refresh();
        $this->assertTrue($a->next_run_at->isFuture());
        $this->assertSame(1, $a->runs);
        $this->assertSame(0, app(TopupAutoReloadService::class)->runDue(), 'nothing is due again until its next slot');
        $this->assertSame(1, TopupOrder::count());
    }

    public function test_the_same_slot_can_never_charge_twice(): void
    {
        $this->ng();
        $u = $this->user(50);
        $a = $this->plan($u);
        $slotKey = 'auto-'.$a->id.'-'.$a->next_run_at->timestamp;
        app(TopupAutoReloadService::class)->runOne($a->id);
        $this->assertSame(1, TopupOrder::where('idempotency_key', $slotKey)->count());
        // even if the schedule were rewound to the same slot, the idempotency key returns the same order
        $a->refresh();
        $a->forceFill(['next_run_at' => \Illuminate\Support\Carbon::createFromTimestamp((int) explode('-', $slotKey)[2])])->save();
        app(TopupAutoReloadService::class)->runOne($a->id);
        $this->assertSame(1, TopupOrder::count());
    }

    public function test_a_short_wallet_skips_the_run_and_three_misses_pause_the_plan(): void
    {
        $this->ng();
        $u = $this->user(0.01);
        $a = $this->plan($u);
        for ($i = 1; $i <= 3; $i++) {
            $a->forceFill(['next_run_at' => now()->subMinutes($i)])->save();
            app(TopupAutoReloadService::class)->runOne($a->id);
        }
        $a->refresh();
        $this->assertFalse($a->active);
        $this->assertNotNull($a->paused_reason);
        $this->assertSame(0, TopupOrder::where('status', TopupOrder::DELIVERED)->count());
        $this->assertEqualsWithDelta(0.01, (float) app(WalletService::class)->spendableUsd($u->fresh()), 0.001, 'nothing was charged');
    }

    public function test_a_data_plan_is_matched_to_the_same_bundle_by_name(): void
    {
        $this->ng();
        TopupProduct::create(['provider_code' => 'zendit', 'provider_sku' => 'd2', 'country_iso' => 'NG', 'operator_name' => 'MTN', 'type' => 'data', 'name' => '2GB (30 days)', 'currency' => 'NGN', 'is_fixed' => true,
            'fixed_amount' => 1000, 'cost_native' => 0.7, 'cost_currency' => 'USD', 'active' => true]);
        $u = $this->user(50);
        $this->plan($u, ['product_type' => 'data', 'send_amount' => 1000, 'product_name' => '2GB (30 days)']);
        app(TopupAutoReloadService::class)->runDue();
        $this->assertSame('d2', TopupOrder::first()->provider_sku, 'two bundles share a price: the saved name picks the right one');
    }

    public function test_auto_topups_obey_the_admin_switch_and_the_per_user_limit(): void
    {
        $this->ng();
        $u = $this->user(50);
        $this->plan($u);
        TopupSettings::set('autoreload_enabled', false);
        $this->assertSame(0, app(TopupAutoReloadService::class)->runDue());
        TopupSettings::set('autoreload_enabled', true);
        TopupSettings::set('max_autoreloads_per_user', 1);
        $b = TopupBeneficiary::first();
        Livewire::actingAs($u)->test(TopupBeneficiaries::class)->set('planFor', $b->id)->set('planProduct', TopupProduct::where('type', 'airtime')->value('id'))->set('planAmount', '1000')->call('createPlan')
            ->assertSet('problem', 'You already have the maximum number of active auto top-ups (1).');
    }

    public function test_paying_can_save_the_person_and_start_an_auto_topup(): void
    {
        $this->ng();
        $u = $this->user(50);
        Livewire::actingAs($u)->test(TopupPage::class)->set('country', 'NG')->set('phone', '08031234567')->call('pickOperator', 'MTN')
            ->call('pickProduct', TopupProduct::where('operator_name', 'MTN')->where('type', 'airtime')->value('id'))->set('amount', '1000')->call('makeQuote')
            ->set('saveBeneficiary', true)->set('beneficiaryName', 'Mum')->set('autoOn', true)->set('freq', 'weekly')->set('weekday', 2)->set('hour', 7)->call('pay');
        $this->assertSame(1, TopupOrder::where('status', TopupOrder::DELIVERED)->count());
        $b = TopupBeneficiary::first();
        $this->assertSame('Mum', $b->name);
        $this->assertSame('+2348031234567', $b->phone_e164);
        $a = TopupAutoReload::first();
        $this->assertSame('weekly', $a->frequency);
        $this->assertSame(2, (int) $a->run_weekday);
        $this->assertTrue($a->next_run_at->isFuture());
    }

    public function test_one_person_cannot_see_or_touch_anothers_beneficiaries(): void
    {
        $this->ng();
        $a = $this->user();
        $b = $this->user();
        $ben = TopupBeneficiary::create(['user_id' => $a->id, 'name' => 'A', 'phone_e164' => '+2348031234567', 'country_iso' => 'NG']);
        Livewire::actingAs($b)->test(TopupBeneficiaries::class)->call('remove', $ben->id);
        $this->assertTrue(TopupBeneficiary::whereKey($ben->id)->exists());
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($b)->test(TopupBeneficiaries::class)->call('startEdit', $ben->id);
    }

    // ── Snapshot, logos, hero ───────────────────────────────────────────────
    public function test_the_shipped_snapshot_seeds_an_empty_catalogue_and_never_overwrites_live_data(): void
    {
        $this->assertFileExists(TopupSnapshot::path());
        $n = app(TopupSnapshot::class)->import();
        $this->assertGreaterThan(10000, $n);
        $this->assertGreaterThan(100, TopupProduct::distinct('country_iso')->count('country_iso'));
        $this->assertTrue(TopupProduct::where('country_iso', 'NG')->where('operator_name', 'MTN')->where('type', 'airtime')->exists());
        $this->assertTrue(TopupProduct::where('country_iso', 'NG')->where('operator_name', 'MTN')->where('type', 'data')->exists());
        $this->assertSame(0, app(TopupSnapshot::class)->import(), 'a populated catalogue is left alone');
    }

    public function test_seed_command_path_loads_the_snapshot(): void
    {
        app(TopupOps::class)->seed();
        $this->assertGreaterThan(10000, TopupProduct::count());
    }

    public function test_nigerian_network_logos_are_seeded_and_etisalat_means_9mobile_in_nigeria(): void
    {
        foreach (['MTN', 'Glo', 'Airtel', 'Nine Mobile', '9mobile', 'Safaricom', 'Vodacom'] as $n) {
            $this->assertNotNull(TopupLogos::url($n), "$n has no logo");
        }
        $this->assertSame(TopupLogos::url('9mobile'), TopupLogos::url('Etisalat', 'NG'));
        $this->assertNotSame(TopupLogos::url('9mobile'), TopupLogos::url('Etisalat', 'AE'));
    }

    public function test_hero_defaults_to_the_numbers_banners_and_admin_save_overrides(): void
    {
        $h = TopupHeroContent::current();
        $this->assertSame([], $h['slides'], 'the fork ships the simple hero with no branded banners');
        TopupHeroContent::save(['title' => 'Big offer', 'description' => 'x', 'enabled' => true, 'autoplay' => false, 'interval' => 7, 'slides' => [['image' => '/storage/a.webp', 'title' => 'T', 'link' => '/top-up']]]);
        $h = TopupHeroContent::current();
        $this->assertSame('Big offer', $h['title']);
        $this->assertCount(1, $h['slides']);
    }

    public function test_page_shows_the_hero_with_svg_and_banners(): void
    {
        $this->ng();
        config(['services.zendit.api_key' => 'k']);
        $this->actingAs($this->user())->get(route('topup'))->assertOk()->assertSee('ns-tp__art', false)->assertDontSee('ns-tp__car', false);
    }

    // ── Dashboard card ──────────────────────────────────────────────────────
}
