<?php

namespace Tests\Feature;

use App\Livewire\Admin\LinkPreviews;
use App\Models\MerchantInvoice;
use App\Models\Setting;
use App\Models\User;
use App\Support\LinkPreviewSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owner request 2026-09-08: pasting a Naara link into WhatsApp/Slack/etc.
 * should show a real, admin-controllable preview image per context, not the
 * bare site favicon. Every context ships with a real banner (never blank) and
 * is independently overridable.
 */
class LinkPreviewSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_every_context_resolves_to_a_real_shipped_banner_by_default(): void
    {
        foreach (LinkPreviewSettings::CONTEXTS as $context) {
            $url = LinkPreviewSettings::resolve($context);
            $this->assertNotEmpty($url);
            $this->assertStringContainsString('/images/marketing/banners/', $url);
        }
    }

    /**
     * Regression: og:image now renders on every page via this class, so a
     * missing/unmigrated `settings` table (any test that doesn't use
     * RefreshDatabase, or a fresh install before its first migration) must
     * never break page rendering — mirrors NumbersBento::cards()'s own
     * try/catch-to-defaults resilience.
     */
    public function test_resolve_falls_back_safely_when_the_settings_table_is_unavailable(): void
    {
        LinkPreviewSettings::bust(); // force a real query, not a cache hit from an earlier test
        \Illuminate\Support\Facades\Schema::drop('settings');

        $this->assertStringContainsString('esim-virtual-numbers-banner', LinkPreviewSettings::resolve('default'));
    }

    public function test_an_admin_override_replaces_the_shipped_default(): void
    {
        Setting::setValue('link_preview.invoice', 'https://cdn.example.test/custom-invoice.webp');
        LinkPreviewSettings::bust();

        $this->assertSame('https://cdn.example.test/custom-invoice.webp', LinkPreviewSettings::resolve('invoice'));
        // Other contexts are untouched.
        $this->assertStringContainsString('esim-virtual-numbers-banner', LinkPreviewSettings::resolve('default'));
    }

    public function test_the_homepage_shows_the_default_preview_image_normally(): void
    {
        $res = $this->get('/');
        $res->assertOk();
        $res->assertSee('og:image', false);
        $res->assertSee(LinkPreviewSettings::resolve('default'), false);
    }

    public function test_the_homepage_shows_the_referral_preview_image_when_opened_via_a_referral_link(): void
    {
        // The default banner image also happens to be one of the carousel
        // slides further down the page, so assert on the actual og:image meta
        // tag content specifically rather than "appears anywhere in the HTML".
        $res = $this->get('/?ref=SOMECODE');
        $res->assertOk();
        $res->assertSee('<meta property="og:image" content="'.LinkPreviewSettings::resolve('referral').'">', false);
        $res->assertDontSee('<meta property="og:image" content="'.LinkPreviewSettings::resolve('default').'">', false);
    }

    public function test_a_public_invoice_link_shows_the_invoice_preview_image(): void
    {
        $merchant = \App\Models\Merchant::create([
            'owner_user_id' => User::factory()->create()->id,
            'business_name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'tier' => 'standard',
            'reseller_margin_pct' => 10,
        ]);
        $client = \App\Models\MerchantClient::create([
            'merchant_id' => $merchant->id, 'name' => 'Client X', 'email' => 'x@x.test',
        ]);
        $invoice = MerchantInvoice::create([
            'merchant_id' => $merchant->id, 'merchant_client_id' => $client->id,
            'description' => 'Renewal', 'amount' => 50, 'status' => MerchantInvoice::SENT,
            'reference' => 'inv-1', 'public_token' => 'tok-'.uniqid(), 'sent_at' => now(),
        ]);

        $res = $this->get('/i/'.$invoice->public_token);
        $res->assertOk();
        $res->assertSee(LinkPreviewSettings::resolve('invoice'), false);
    }

    // --- Admin screen ---

    public function test_a_non_admin_gets_a_403_on_the_link_previews_screen(): void
    {
        $this->seed(RoleSeeder::class);
        Livewire::actingAs(User::factory()->create())->test(LinkPreviews::class)->assertStatus(403);
    }

    public function test_an_admin_can_upload_a_new_invoice_preview_image(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        Livewire::actingAs($this->admin())
            ->test(LinkPreviews::class)
            ->set('invoice_image', UploadedFile::fake()->image('new-invoice.jpg', 800, 450))
            ->call('save');

        $this->assertNotEmpty(Setting::getValue('link_preview.invoice', ''));
    }

    public function test_resetting_a_context_clears_the_override_back_to_the_shipped_default(): void
    {
        Setting::setValue('link_preview.invoice', 'https://cdn.example.test/custom.webp');
        LinkPreviewSettings::bust();

        Livewire::actingAs($this->admin())
            ->test(LinkPreviews::class)
            ->call('resetContext', 'invoice');

        $this->assertStringContainsString('invoice-banner', LinkPreviewSettings::resolve('invoice'));
    }

    // --- Homepage banner carousel ---

    public function test_the_homepage_shows_the_banner_carousel_by_default(): void
    {
        $res = $this->get('/');
        $res->assertOk();
        $res->assertSee('home-banners', false);
    }

    public function test_the_banner_carousel_can_be_disabled_by_an_admin(): void
    {
        Setting::setValue('home.banner_carousel_enabled', false);

        $res = $this->get('/');
        $res->assertOk();
        $res->assertDontSee('home-banners', false);
    }

    /**
     * Regression: the homepage renders in tests that don't use
     * RefreshDatabase at all (e.g. the base ExampleTest, which has no
     * `settings` table ever migrated) — the carousel's on/off check must
     * never 500 the whole homepage over that, same resilience as
     * LinkPreviewSettings itself. Reproduced narrowly at the partial level
     * rather than dropping the whole table on a fully migrated app (which
     * would also exercise unrelated homepage dependencies this change isn't
     * responsible for).
     */
    public function test_the_carousel_partial_defaults_to_shown_when_settings_lookup_throws(): void
    {
        \Illuminate\Support\Facades\Schema::drop('settings');

        $html = view('marketing.home._banner_carousel')->render();

        $this->assertStringContainsString('home-banners', $html);
    }

    /**
     * Frontend-UX-fix blueprint Phase D — root-caused via Playwright: a fixed
     * `h-56 sm:h-80` stage height stayed constant while the stage's own width
     * swung from "full single column" (below `lg`) to "half a 2-column row"
     * (at `lg`+), spiking its aspect ratio to ~2.68:1 in the 640-1023px
     * range against the banners' genuine 16:9 — `object-fit: cover` cropped
     * off ~34% of every banner there, cutting the bottom tagline band clean
     * off (confirmed live: intact at 375px and 1440px, gone at 900px).
     * `aspect-[16/9]` ties the stage's height to its own width at every
     * breakpoint instead of guessing, so it always matches these banners'
     * real ratio. A fitness guard: if this regresses back to a fixed height,
     * the mid-viewport crop silently comes back.
     */
    public function test_the_banner_carousel_stage_uses_the_banners_own_aspect_ratio_not_a_fixed_height(): void
    {
        // Frontend-UX-fix blueprint Phase G moved the actual carousel markup
        // out of this file and into the shared `partials.banner-carousel`
        // (App\Support\BannerPlacements) that every placement now renders
        // through — this homepage-specific file is just a thin `@include`
        // wrapper today, so the aspect-ratio fix from Phase D lives there.
        $html = file_get_contents(resource_path('views/partials/banner-carousel.blade.php'));

        $this->assertStringContainsString('height="aspect-[16/9]"', $html);
        $this->assertStringNotContainsString('height="h-56 sm:h-80"', $html);
    }
}
