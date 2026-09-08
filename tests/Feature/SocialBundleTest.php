<?php

namespace Tests\Feature;

use App\Livewire\Admin\Integrations;
use App\Models\User;
use App\Support\SocialAuth;
use App\Support\SocialLinks;
use App\Support\Tracking;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Social bundle (owner request): footer social links (hidden until configured),
 * Facebook Pixel / Google Analytics tracking, and extra social sign-in providers
 * — each gated on being configured so nothing ever dangles.
 */
class SocialBundleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Cache::flush();
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    // ── Social sign-in gating ────────────────────────────────────────────────

    public function test_a_provider_is_only_enabled_with_both_keys(): void
    {
        config(['services.discord.client_id' => '', 'services.discord.client_secret' => '']);
        $this->assertFalse(SocialAuth::enabled('discord'));

        config(['services.discord.client_id' => 'id', 'services.discord.client_secret' => 'sec']);
        $this->assertTrue(SocialAuth::enabled('discord'));
        $this->assertArrayHasKey('discord', SocialAuth::enabledProviders());
    }

    public function test_a_social_redirect_404s_until_the_provider_is_configured(): void
    {
        config(['services.discord.client_id' => '', 'services.discord.client_secret' => '']);
        $this->get('/auth/discord/redirect')->assertNotFound();
    }

    // ── Footer links ─────────────────────────────────────────────────────────

    public function test_footer_links_are_hidden_until_saved_then_shown(): void
    {
        $this->assertFalse(SocialLinks::any());

        SocialLinks::save(['x' => 'https://x.com/naarasim', 'facebook' => 'not-a-url', 'instagram' => 'https://instagram.com/naarasim']);
        Cache::flush();

        $links = SocialLinks::forFooter();
        $keys = collect($links)->pluck('platform')->all();
        $this->assertContains('x', $keys);
        $this->assertContains('instagram', $keys);
        $this->assertNotContains('facebook', $keys); // invalid URL dropped
    }

    public function test_share_targets_only_include_configured_shareable_platforms(): void
    {
        // instagram has no web share-intent; whatsapp does. Only configured ones show.
        SocialLinks::save([
            'x' => 'https://x.com/naarasim',
            'whatsapp' => 'https://wa.me/2340000',
            'instagram' => 'https://instagram.com/naarasim',
        ]);
        Cache::flush();

        $targets = SocialLinks::shareTargets('https://naara.test/blog/hello', 'Hello world');
        $keys = collect($targets)->pluck('platform')->all();

        $this->assertContains('x', $keys);
        $this->assertContains('whatsapp', $keys);
        $this->assertNotContains('instagram', $keys); // configured but not shareable
        $this->assertNotContains('facebook', $keys);   // shareable but not configured

        // URL + title are encoded into the intent href.
        $x = collect($targets)->firstWhere('platform', 'x');
        $this->assertStringContainsString(rawurlencode('https://naara.test/blog/hello'), $x['href']);
        $this->assertStringContainsString(rawurlencode('Hello world'), $x['href']);
    }

    // ── Tracking ─────────────────────────────────────────────────────────────

    public function test_tracking_ids_are_shape_validated(): void
    {
        Tracking::save('123456789012345', 'G-ABC123XYZ');
        $this->assertSame('123456789012345', Tracking::pixelId());
        $this->assertSame('G-ABC123XYZ', Tracking::gaId());

        Tracking::save('not-a-pixel', 'nope');
        $this->assertNull(Tracking::pixelId());
        $this->assertNull(Tracking::gaId());
    }

    public function test_tracking_snippets_render_only_when_set(): void
    {
        // No IDs → no snippet on the public page.
        $this->get('/')->assertDontSee('googletagmanager.com/gtag');

        Tracking::save('123456789012345', 'G-ABC123XYZ');
        $res = $this->get('/');
        $res->assertSee('googletagmanager.com/gtag', false);
        $res->assertSee('fbq(', false);
        $res->assertSee('G-ABC123XYZ', false);
    }

    // ── Admin ────────────────────────────────────────────────────────────────

    public function test_admin_can_save_links_and_tracking(): void
    {
        Livewire::actingAs($this->admin())->test(Integrations::class)
            ->set('social.x', 'https://x.com/naarasim')
            ->call('saveSocial')
            ->set('gaId', 'G-TESTID99')
            ->set('pixelId', '123456789012345')
            ->call('saveTracking')
            ->assertHasNoErrors();

        $this->assertTrue(SocialLinks::any());
        $this->assertSame('G-TESTID99', Tracking::gaId());
    }

    public function test_the_integrations_page_is_admin_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Integrations::class)->assertStatus(404);
    }
}
