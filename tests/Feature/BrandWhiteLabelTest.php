<?php

namespace Tests\Feature;

use App\Livewire\Admin\Branding;
use App\Models\Setting;
use App\Models\User;
use App\Support\BrandSettings;
use App\Support\ProviderModels;
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
}
