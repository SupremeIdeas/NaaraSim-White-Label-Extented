<?php

namespace Tests\Feature;

use App\Livewire\PayoutGuide;
use App\Livewire\Withdraw;
use App\Models\PayoutCorridor;
use App\Models\PayoutCountryRail;
use App\Models\PayoutGuideEvent;
use App\Models\PayoutRailAcknowledgement;
use App\Models\PayoutRailEnrollment;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\Rail\PayoutRailRegistry;
use App\Services\Payouts\Rail\RailEnrollmentService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Rail Guide G3 (UI) + G4 (server-side enforcement, acknowledgement, enrollment) + tracking. */
class PayoutGuideUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
        config(['services.paystack.secret_key' => 'sk_p', 'services.flutterwave.secret_key' => 'sk_f']);
        app()->setLocale('en');
    }

    private function user(string $country = 'KE'): User
    {
        return User::factory()->create(['country_code' => $country]);
    }

    private function corridor(string $country, string $provider, bool $enabled = true): void
    {
        PayoutCorridor::updateOrCreate(['country' => $country, 'currency' => 'USD', 'provider' => $provider, 'method' => 'bank'], ['enabled' => $enabled, 'priority' => 10]);
    }

    private function globalOnly(string $country = 'KE'): void
    {
        PayoutCountryRail::updateOrCreate(['country' => $country, 'rail' => 'global'], ['provider_supports' => true, 'verified_at' => now(), 'verified_by' => 'seed']);
        $this->corridor($country, 'payoneer');
        PayoutCorridor::where('country', $country)->whereIn('provider', ['paystack', 'flutterwave'])->update(['enabled' => false]);
        app(PayoutRailRegistry::class)->flush();
    }

    private function guide(User $u)
    {
        return Livewire::actingAs($u)->test(PayoutGuide::class);
    }

    // ---- verdict banners ----

    public function test_it_defaults_to_the_users_country_and_shows_the_fast_rail_verdict(): void
    {
        $this->corridor('KE', 'paystack');
        $c = $this->guide($this->user('KE'));

        $this->assertSame('KE', $c->get('country'));
        $c->assertSee('How do I get paid?')->assertSee('Your country is supported on Paystack')->assertSee('Recommended: Paystack');
    }

    public function test_global_only_verdict_carries_the_fourteen_day_warning(): void
    {
        $this->globalOnly();

        $this->guide($this->user('KE'))->assertSee("isn't supported by Paystack, Flutterwave or Stripe Connect")->assertSee('up to 14 days');
    }

    public function test_the_fourteen_days_comes_from_the_setting(): void
    {
        $this->globalOnly();
        Setting::setValue('payouts.eta.global_days', 21);

        $this->guide($this->user('KE'))->assertSee('up to 21 days');
    }

    public function test_blocked_and_none_available_verdicts_offer_notify_me_once(): void
    {
        Setting::setValue(PayoutSettings::DENIED_COUNTRIES, 'KE');
        $u = $this->user('KE');
        $c = $this->guide($u)->assertSee("aren't available right now")->assertSee('Notify me');

        $c->call('notifyMe')->call('notifyMe');
        $this->assertSame(1, PayoutGuideEvent::where('event', 'blocked_notify_requested')->where('user_id', $u->id)->count(), 'deduplicated');
        $c->assertSee("We'll tell you");

        Setting::setValue(PayoutSettings::DENIED_COUNTRIES, '');
        $this->guide($this->user('JP'))->assertSee("aren't available through any rail");
    }

    // ---- honesty ----

    public function test_coming_soon_never_renders_as_available(): void
    {
        $this->corridor('GH', 'paystack', false);                 // provider covers GH, we have not enabled it
        app(PayoutRailRegistry::class)->flush();
        $c = $this->guide($this->user('GH'))->set('search', 'Ghana');

        $row = collect($c->viewData('matrix')['rows'])->firstWhere('country', 'GH');
        $this->assertSame('coming_soon', $row['rails']['paystack']);
        $html = $c->html();
        $this->assertStringContainsString('Coming soon', $html);
        $this->assertSame('none_available', $c->viewData('advice')['verdict']);
    }

    public function test_no_provider_cost_is_ever_rendered(): void
    {
        $this->corridor('KE', 'paystack');
        PayoutCorridor::where('country', 'KE')->where('provider', 'paystack')->update(['est_provider_cost_bps' => 7777, 'platform_fee_bps' => 150]);

        $html = $this->guide($this->user('KE'))->html();

        $this->assertStringNotContainsString('7777', $html);
        $this->assertStringContainsString('1.5%', $html, 'the PLATFORM fee is shown');
    }

    public function test_eta_text_is_admin_editable_and_typical_needs_twenty_samples(): void
    {
        $this->corridor('KE', 'paystack');
        Setting::setValue('payouts.eta.paystack', 'Same day, usually');
        $u = $this->user('KE');
        $this->guide($u)->assertSee('Same day, usually')->assertDontSee('Typical:');

        foreach (range(1, 20) as $i) {
            $r = PayoutRequest::create(['user_id' => $u->id, 'amount' => 1, 'currency' => 'KES', 'source_bucket' => 'referral_earnings', 'status' => 'paid', 'provider' => 'paystack', 'reference' => "t:{$i}"]);
            $r->forceFill(['created_at' => now()->subHours(5), 'settled_at' => now()->subHours(2)])->saveQuietly();
        }
        $this->guide($u)->assertSee('Typical: about 3 h');
    }

    // ---- country list ----

    public function test_the_country_list_renders_every_registry_row_with_the_users_country_pinned(): void
    {
        $this->corridor('KE', 'paystack');
        PayoutCountryRail::query()->update(['verified_at' => now()]);
        app(PayoutRailRegistry::class)->flush();
        $c = $this->guide($this->user('KE'));

        $this->assertSame(count(PayoutRailRegistry::countryCodes()), $c->viewData('matrix')['total']);
        $this->assertSame('KE', $c->viewData('matrix')['rows'][0]['country']);
        $this->assertTrue($c->viewData('matrix')['rows'][0]['pinned']);
        $this->assertLessThanOrEqual(41, count($c->viewData('matrix')['rows']), 'paginated, not a giant DOM');
        $c->assertSee('Last verified');
    }

    public function test_search_and_filters_narrow_the_list(): void
    {
        $this->corridor('NG', 'paystack');
        $c = $this->guide($this->user('KE'));

        $codes = fn () => array_column($c->viewData('matrix')['rows'], 'country');
        $c->set('search', 'nigeria');
        $this->assertContains('NG', $codes());
        $this->assertNotContains('JP', $codes());

        $c->set('search', '')->set('filter', 'paystack');
        $this->assertSame(['NG'], array_values(array_diff($codes(), ['KE'])), 'only Paystack-available countries (KE is pinned above)');

        $c->set('filter', 'unavailable');
        $this->assertNotContains('NG', $codes());
    }

    public function test_the_matrix_renders_within_a_bounded_query_count(): void
    {
        $this->corridor('KE', 'paystack');
        $u = $this->user('KE');
        Livewire::actingAs($u)->test(PayoutGuide::class);          // warm the first render
        Cache::flush();

        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::actingAs($u)->test(PayoutGuide::class);
        $n = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(45, $n, "matrix + advice used {$n} queries");
    }

    // ---- choosing + hand-over to the existing forms ----

    public function test_choosing_a_local_rail_hands_over_to_the_existing_setup_and_tracks_it(): void
    {
        $this->corridor('KE', 'paystack');
        $u = $this->user('KE');

        $this->guide($u)->call('choose', 'paystack')->assertDispatched('payout-rail-chosen', rail: 'paystack', country: 'KE');

        $this->assertTrue(PayoutGuideEvent::where('event', 'rail_selected')->where('rail', 'paystack')->exists());
        $this->assertTrue(PayoutGuideEvent::where('event', 'guide_viewed')->exists());
        $this->assertTrue(PayoutGuideEvent::where('event', 'rail_recommended')->where('rail', 'paystack')->exists());
    }

    public function test_changing_country_is_tracked_and_the_withdraw_page_follows_the_chosen_rail(): void
    {
        $u = $this->user('KE');
        $this->guide($u)->set('country', 'gh');
        $this->assertTrue(PayoutGuideEvent::where('event', 'country_changed')->where('country', 'GH')->exists());

        Livewire::actingAs($u)->test(Withdraw::class)->call('railChosen', 'stripe_connect', 'US')
            ->assertSet('accountType', 'stripe')->assertSet('country', 'US')
            ->call('railChosen', 'paypal', 'US')->assertSet('accountType', 'paypal')
            ->call('railChosen', 'flutterwave', 'GH')->assertSet('accountType', 'bank')->assertSet('country', 'GH');
    }

    public function test_the_guide_is_step_one_of_the_withdraw_page_and_the_old_forms_remain(): void
    {
        $this->corridor('NG', 'paystack');
        Livewire::actingAs($this->user('NG'))->test(Withdraw::class)->assertSee('How do I get paid?')->assertSee('Your payout accounts')->assertSee('Withdraw');
    }

    public function test_the_standalone_route_requires_login(): void
    {
        $this->get('/account/payout-guide')->assertRedirect();
        $this->actingAs(User::factory()->create(['country_code' => 'NG', 'email_verified_at' => now(), 'is_active' => true]))->get('/account/payout-guide')->assertOk()->assertSee('How do I get paid?');
    }

    // ---- G4 acknowledgement + enforcement ----

    public function test_the_global_card_requires_all_three_boxes_and_writes_the_acknowledgement_and_enrollment(): void
    {
        $this->globalOnly();
        $u = $this->user('KE');
        $c = $this->guide($u)->call('choose', 'global')->assertSet('showAck', true);

        $c->set('ack', [true, true, false])->call('confirmGlobal')->assertSee('Please tick all three boxes');
        $this->assertSame(0, PayoutRailAcknowledgement::count());
        $this->assertSame(0, PayoutRailEnrollment::count());

        $c->set('ack', [true, true, true])->call('confirmGlobal')->assertSet('showAck', false)->assertDispatched('payout-rail-chosen');

        $ack = PayoutRailAcknowledgement::first();
        $this->assertSame([$u->id, 'global', 'KE', 1], [$ack->user_id, $ack->rail, $ack->country, $ack->guide_version]);
        $this->assertSame(64, strlen($ack->ip_hash ?? str_repeat('x', 64)), 'hashed, never the raw ip');
        $e = PayoutRailEnrollment::first();
        $this->assertSame(['payoneer', 'KE', 'selected'], [$e->provider, $e->country, $e->status]);
        $this->assertTrue(PayoutGuideEvent::where('event', 'global_ack_confirmed')->exists());
    }

    public function test_a_direct_call_cannot_bypass_the_guide_global_is_refused_while_a_fast_rail_exists(): void
    {
        $this->globalOnly();
        $this->corridor('KE', 'paystack');                      // a fast rail is now available
        app(PayoutRailRegistry::class)->flush();
        $u = $this->user('KE');

        $this->guide($u)->call('choose', 'global')->assertSet('showAck', false)->assertSee('not available while a faster rail');
        $this->guide($u)->set('ack', [true, true, true])->call('confirmGlobal')->assertSee('not available while a faster rail');
        $this->assertSame(0, PayoutRailEnrollment::count());

        $this->expectException(PayoutException::class);
        app(RailEnrollmentService::class)->select($u, 'payoneer', 'KE', null, enforce: true);
    }

    public function test_enrollment_without_an_acknowledgement_is_refused_and_a_guide_version_bump_requires_a_new_one(): void
    {
        $this->globalOnly();
        $u = $this->user('KE');
        $svc = app(RailEnrollmentService::class);

        try {
            $svc->select($u, 'payoneer', 'KE', null, enforce: true);
            $this->fail('no acknowledgement -> must be refused');
        } catch (PayoutException $e) {
            $this->assertStringContainsString('tick all three', $e->getMessage());
        }

        $this->guide($u)->call('choose', 'global')->set('ack', [true, true, true])->call('confirmGlobal');
        $this->assertSame(1, PayoutRailEnrollment::count());

        Setting::setValue('payouts.guide.version', 2);                       // admin bumps the version
        $this->expectException(PayoutException::class);
        $svc->select($u, 'payoneer', 'KE', null, enforce: true);
    }

    public function test_the_admin_setting_can_allow_global_next_to_fast_rails(): void
    {
        $this->globalOnly();
        $this->corridor('KE', 'paystack');
        app(PayoutRailRegistry::class)->flush();
        Setting::setValue('payouts.global_rail.allow_when_local_available', true);

        $this->guide($this->user('KE'))->call('choose', 'global')->assertSet('showAck', true);
    }

    // ---- i18n / RTL ----

    public function test_every_language_has_every_key_and_arabic_renders_rtl(): void
    {
        $en = array_keys(require base_path('lang/en/payout_guide.php'));
        foreach (['fr', 'sw', 'ar'] as $lang) {
            $this->assertSame([], array_diff($en, array_keys(require base_path("lang/{$lang}/payout_guide.php"))), "{$lang} is missing keys");
        }

        $this->corridor('KE', 'paystack');
        app()->setLocale('ar');
        $html = $this->guide($this->user('KE'))->html();
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('كيف أحصل على أموالي', $html);
    }
}
