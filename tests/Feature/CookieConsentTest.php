<?php

namespace Tests\Feature;

use App\Livewire\Admin\CookieSettings;
use App\Models\CookieConsentLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\CookieConsent\CookieConsent;
use App\Support\Tracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Cookie consent: opt-in gating of tracking, the banner and preferences page, proof-of-consent log, and the admin controls. */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'super_admin'])->save();
        $u->assignRole(Role::findOrCreate('super_admin'));

        return $u;
    }

    private function withTracking(): void
    {
        Tracking::save('123456789012345', 'G-ABCDE12345');
    }

    public function test_tracking_is_blocked_until_the_visitor_agrees(): void
    {
        $this->withTracking();
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString("gtag('consent', 'default'", $html, 'Consent Mode starts denied');
        $this->assertStringContainsString("ad_user_data: 'denied'", $html);
        $this->assertMatchesRegularExpression('#<script type="text/plain" data-consent="analytics" data-src="https://www.googletagmanager.com/gtag/js\?id=G-ABCDE12345" async></script>#', $html);
        $this->assertStringNotContainsString('<script async src="https://www.googletagmanager.com', $html, 'no live request to Google before consent');
        $this->assertMatchesRegularExpression('#<script\s+type="text/plain" data-consent="marketing"\s*>#', $html);
        $this->assertStringNotContainsString('facebook.com/tr?id=', $html, 'the no-script tracking pixel would track without consent');
        $this->assertStringContainsString('data-cookie-root', $html);
    }

    public function test_turning_the_banner_off_restores_plain_tracking(): void
    {
        $this->withTracking();
        Setting::setValue('cookies.enabled', false);
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<script async src="https://www.googletagmanager.com/gtag/js?id=G-ABCDE12345"></script>', $html);
        $this->assertStringNotContainsString('data-cookie-root', $html);
    }

    public function test_no_banner_when_there_is_nothing_optional_to_ask_about(): void
    {
        $this->assertFalse(CookieConsent::hasOptional());
        $this->get('/')->assertOk()->assertDontSee('data-cookie-root', false);
        $this->withTracking();
        $this->assertTrue(CookieConsent::hasOptional());
        $this->assertSame(['necessary', 'analytics', 'marketing'], CookieConsent::categoriesInUse());
    }

    public function test_the_banner_has_reject_beside_accept_and_the_admin_wording(): void
    {
        $this->withTracking();
        Setting::setValue('cookies.title', 'We value your privacy');
        Setting::setValue('cookies.reject', 'No thanks');
        $this->get('/')->assertSee('We value your privacy')->assertSee('data-cookie-accept', false)->assertSee('data-cookie-reject', false)->assertSee('No thanks')->assertSee('data-cookie-save', false);
    }

    public function test_the_admin_and_installer_surfaces_never_show_the_banner(): void
    {
        $this->withTracking();
        $this->actingAs($this->admin())->get('/'.config('admin.path'))->assertDontSee('data-cookie-root', false);
    }

    public function test_a_choice_is_logged_as_proof_with_a_hashed_address(): void
    {
        $r = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/cookie-consent', ['id' => 'abcdef0123456789abcd', 'action' => 'accept_all', 'path' => '/pricing?x=1'])->assertOk()->assertJson(['ok' => true]);
        $row = CookieConsentLog::first();

        $this->assertTrue($row->analytics && $row->marketing && $row->functional);
        $this->assertSame('/pricing', $row->path);
        $this->assertNotSame('203.0.113.9', $row->ip_hash);
        $this->assertSame(CookieConsent::hashIp('203.0.113.9'), $row->ip_hash);
        $this->assertSame(CookieConsent::version(), $row->policy_version);
    }

    public function test_the_action_decides_the_choices_so_a_tampered_body_cannot_lie(): void
    {
        $this->postJson('/cookie-consent', ['id' => 'abcdef0123456789abcd', 'action' => 'reject_all', 'analytics' => true, 'marketing' => true])->assertOk();
        $this->postJson('/cookie-consent', ['id' => 'abcdef0123456789abcd', 'action' => 'custom', 'analytics' => true])->assertOk();

        [$reject, $custom] = CookieConsentLog::orderBy('id')->get()->all();
        $this->assertFalse($reject->analytics || $reject->marketing || $reject->functional);
        $this->assertTrue($custom->analytics);
        $this->assertFalse($custom->marketing);
    }

    public function test_bad_input_is_refused_and_the_endpoint_is_throttled(): void
    {
        $this->postJson('/cookie-consent', ['id' => 'short', 'action' => 'accept_all'])->assertStatus(422);
        $this->postJson('/cookie-consent', ['id' => 'abcdef0123456789abcd', 'action' => 'launch_missiles'])->assertStatus(422);
        $this->assertSame(0, CookieConsentLog::count());
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/cookie-consent', ['id' => 'abcdef0123456789abcd', 'action' => 'custom']);
        }
        $this->postJson('/cookie-consent', ['id' => 'abcdef0123456789abcd', 'action' => 'custom'])->assertStatus(429);
    }

    public function test_the_preferences_page_lists_what_is_in_use(): void
    {
        $this->withTracking();
        $this->get('/cookie-preferences')->assertOk()->assertSee('Cookie preferences')->assertSee('Google Analytics 4')->assertSee('_ga')->assertSee('Meta Pixel')->assertSee('data-pref-save', false)
            ->assertSee('Always on')->assertSee('naara_consent');
    }

    public function test_asking_everyone_again_bumps_the_version_the_page_carries(): void
    {
        $this->withTracking();
        $this->assertSame(1, CookieConsent::version());
        Livewire::actingAs($this->admin())->test(CookieSettings::class)->call('askAgain');
        $this->assertSame(2, CookieConsent::version());
        $this->get('/')->assertSee('data-version="2"', false);
    }

    public function test_the_admin_saves_wording_and_colours_with_validation(): void
    {
        Livewire::actingAs($this->admin())->test(CookieSettings::class)
            ->set('theme', 'custom')->set('custom.bg', 'red')->call('save')->assertHasErrors(['custom.bg'])
            ->set('custom.bg', '#112233')->set('title', 'Hello <b>there</b>')->set('layout', 'bar')->set('renewDays', '10')->call('save')->assertHasErrors(['renewDays'])
            ->set('renewDays', '200')->call('save')->assertHasNoErrors();
        $d = CookieConsent::design();
        $this->assertSame('bar', $d['layout']);
        $this->assertSame('Hello there', $d['title'], 'markup is stripped');
        $this->assertSame('--ck-bg:#112233;--ck-text:#F1F5F9;--ck-btn:#D4A017;--ck-btn-text:#0D1B2A;', CookieConsent::cssVars($d));
        $this->assertSame(200, $d['renew_days']);
    }

    public function test_a_stored_bad_colour_never_reaches_the_page(): void
    {
        $this->withTracking();
        Setting::setValue('cookies.theme', 'custom');
        Setting::setValue('cookies.custom', ['bg' => 'red;}</style><script>alert(1)</script>', 'text' => '#fff', 'button' => '#D4A017', 'button_text' => '#0D1B2A']);
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('--ck-bg:#0D1B2A;', $html, 'falls back to the default colour');
    }

    public function test_prune_removes_only_records_past_the_retention_period(): void
    {
        Setting::setValue('cookies.retention_months', 12);
        CookieConsentLog::create(['consent_id' => 'old000000000000000000', 'policy_version' => 1, 'action' => 'accept_all', 'created_at' => now()->subMonths(13)]);
        CookieConsentLog::create(['consent_id' => 'new000000000000000000', 'policy_version' => 1, 'action' => 'accept_all', 'created_at' => now()->subMonths(2)]);

        $this->artisan('cookies:prune')->expectsOutputToContain('Removed 1')->assertExitCode(0);
        $this->assertSame(['new000000000000000000'], CookieConsentLog::pluck('consent_id')->all());
    }

    public function test_the_export_is_admin_only_and_carries_no_address(): void
    {
        CookieConsent::record('exp0000000000000000000', 'accept_all', ['analytics' => true], null, '198.51.100.7', 'UA', '/');
        $this->get(route('admin.cookies.export'))->assertStatus(302);   // guests are bounced
        $csv = $this->actingAs($this->admin())->get(route('admin.cookies.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('exp0000000000000000000', $csv);
        $this->assertStringNotContainsString('198.51.100.7', $csv);
        $this->assertStringContainsString(CookieConsent::hashIp('198.51.100.7'), $csv);
    }

    public function test_stats_count_the_share_that_allow_each_category(): void
    {
        foreach ([['accept_all', 1, 1], ['accept_all', 1, 1], ['reject_all', 0, 0], ['custom', 1, 0]] as $i => [$a, $an, $m]) {
            CookieConsent::record('st'.$i.'0000000000000000000', $a, ['analytics' => (bool) $an, 'marketing' => (bool) $m, 'functional' => $a === 'accept_all'], null, null, null, '/');
        }
        $s = CookieConsent::stats(30);

        $this->assertSame(4, $s['total']);
        $this->assertSame(75, $s['analytics']);
        $this->assertSame(50, $s['marketing']);
    }

    public function test_the_default_legal_footer_links_to_cookie_settings_and_the_policy_text_describes_opt_in(): void
    {
        $this->assertContains('/cookie-preferences', array_column(\App\Support\SiteChrome::defaultLegal(), 'url'));
        $this->get('/legal/cookies')->assertOk()->assertSee('Nothing optional runs until you say yes')->assertSee('Global Privacy Control');
    }
}
