<?php

namespace Tests\Feature;

use App\Livewire\Admin\Branding;
use App\Models\Setting;
use App\Models\User;
use App\Support\BrandSettings;
use App\Support\NumbersBento;
use App\Support\ProductLineSettings;
use App\Support\ProviderModels;
use App\Support\SectionLibrary;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * White-label brand word: an admin-set business name swaps the shipped "Naara"
 * token through product/sub-brand names, with a full palette system on the side.
 */
class BrandWhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    private function setWord(string $word): void
    {
        Setting::setValue('brand.word', $word, 'brand');
        BrandSettings::flush();
    }

    public function test_default_install_is_a_no_op(): void
    {
        $this->assertSame('Naara', BrandSettings::word());
        $this->assertSame('Naara Rent', BrandSettings::rebrand('Naara Rent'));
        $this->assertFalse(BrandSettings::isWhiteLabelled());
    }

    public function test_the_word_swaps_through_names_but_not_lowercase_asset_paths(): void
    {
        $this->setWord('Acme');

        $this->assertSame('Acme Rent', BrandSettings::rebrand('Naara Rent'));
        $this->assertSame('AcmeCredits', BrandSettings::rebrand('NaaraCredits'));
        $this->assertSame('AcmeSim', BrandSettings::rebrand('NaaraSim'));
        // Lowercase asset-style tokens are left alone.
        $this->assertSame('build/naara-x.js', BrandSettings::rebrand('build/naara-x.js'));
        $this->assertTrue(BrandSettings::isWhiteLabelled());
    }

    public function test_product_model_names_are_rebranded(): void
    {
        $this->setWord('Acme');

        $this->assertSame('Acme Rent', ProviderModels::find('naara_rent')['name']);
        $this->assertSame('Acme Line', ProviderModels::find('naara_line')['name']);
        // The umbrella data plan too.
        $this->assertStringContainsString('Acme', ProviderModels::find('naara_data')['name']);
    }

    public function test_admin_can_save_the_brand_word_and_apply_a_palette(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('brand_word', 'Acme')
            ->call('applyPalette', 'Ocean Blue')
            ->assertSet('color_primary', BrandSettings::PALETTES['Ocean Blue']['primary'])
            ->call('saveTheme')
            ->assertHasNoErrors();

        BrandSettings::flush();
        $this->assertSame('Acme', BrandSettings::word());
        $this->assertSame(BrandSettings::PALETTES['Ocean Blue']['primary'], BrandSettings::color('primary'));
    }

    public function test_there_are_25_curated_palettes(): void
    {
        $this->assertCount(25, BrandSettings::PALETTES);
        foreach (BrandSettings::PALETTES as $name => $c) {
            foreach (['primary', 'accent', 'navy', 'action'] as $k) {
                $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $c[$k], "$name.$k");
            }
        }
    }

    // --- Owner audit (2026-09-15): rebrand() wired beyond ProviderModels ---
    // "Naara Verify"/"Naara Rent" (and NaaraCredits/Naara Gift) were hardcoded
    // in half a dozen places with no rename path — this closes the gap for the
    // ones the audit named.

    public function test_numbers_lang_strings_are_rebranded(): void
    {
        $this->setWord('Acme');

        $this->assertSame('Acme Verify', __('numbers.modal_title')['verify']);
        $this->assertSame('Acme Rent', __('numbers.modal_title')['rent']);
        $this->assertSame('Acme Line', __('numbers.modal_title')['line']);
        $this->assertSame('Use my AcmeCredits', __('numbers.use_naaracredits'));
        $this->assertSame('Acme Line is coming soon', __('numbers.line.coming_soon_title'));
        $this->assertSame('Acme Line active', __('numbers.line.active_title'));
        $this->assertStringContainsString('Acme Line is a monthly subscription', __('numbers.line.monthly_note'));
    }

    public function test_checkout_lang_strings_are_rebranded(): void
    {
        $this->setWord('Acme');

        $this->assertSame('Use my AcmeCredits', __('checkout.use_naaracredits'));
        $this->assertStringContainsString('AcmeSim wallet', __('checkout.wallet_disclaimer'));
    }

    public function test_numbers_bento_titles_are_rebranded(): void
    {
        $this->setWord('Acme');

        $cards = collect(NumbersBento::cards())->keyBy('key');
        $this->assertSame('Acme Verify', $cards['verify']['title']);
        $this->assertSame('Acme Rent', $cards['rent']['title']);
        $this->assertSame('Acme Line', $cards['line']['title']);
        $this->assertStringContainsString('Acme number', $cards['call_forwarding']['subtitle']);
    }

    public function test_section_library_bento_defaults_are_rebranded(): void
    {
        $this->setWord('Acme');

        $cards = SectionLibrary::types()['bento']['defaults']['cards'];
        $titles = array_column($cards, 'title');
        $this->assertContains('Acme Line', $titles);
        $this->assertContains('Acme Verify', $titles);
    }

    public function test_product_line_titles_are_rebranded_and_the_cache_busts_on_brand_change(): void
    {
        // Warm the cache under the shipped default first.
        $this->assertSame('Naara Verify', collect(ProductLineSettings::products())->firstWhere('slug', 'naara-verify')['title']);

        $this->setWord('Acme');

        // A brand-word save must invalidate ProductLineSettings' forever-cache
        // too, not just BrandSettings' own — otherwise this stays stale until
        // an unrelated product-line save.
        $bySlug = collect(ProductLineSettings::products())->keyBy('slug');
        $this->assertSame('Acme Data', $bySlug['naara-data']['title']);
        $this->assertSame('Acme Connect', $bySlug['naara-connect']['title']);
        $this->assertSame('Acme Verify', $bySlug['naara-verify']['title']);
        $this->assertSame('Acme Rent', $bySlug['naara-rent']['title']);
        $this->assertSame('Acme Line', $bySlug['naara-line']['title']);
        $this->assertSame('Acme Gift', $bySlug['naara-gift']['title']);
        $this->assertSame('Explore Acme Connect', $bySlug['naara-connect']['cta_label']);

        // slides() (the marketing-facing consumer) reflects it too.
        $slides = collect(ProductLineSettings::slides())->keyBy(fn ($s) => $s['title']);
        $this->assertArrayHasKey('Acme Verify', $slides->toArray());
    }
}
