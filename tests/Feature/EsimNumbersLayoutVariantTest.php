<?php

namespace Tests\Feature;

use App\Livewire\Catalogue;
use App\Livewire\GetNumber;
use App\Models\Setting;
use App\Models\User;
use App\Support\ThemePreset;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA THEME SYSTEM — Batch 2 §2. eSIM + Numbers use a HEADER/HERO REFLOW: the
 * storefront/number BODY is identical in every theme, only the hero-vs-header
 * order at the top changes. Both variants must render the same content, and an
 * absent choice falls back to variant-a.
 */
class EsimNumbersLayoutVariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
    }

    private function useTheme(string $slug): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();
    }

    public function test_esim_renders_both_orders_with_the_same_head(): void
    {
        $u = User::factory()->create();

        // Default (naara-official) → variant-a: hero then header.
        $this->assertSame('variant-a', ThemePreset::layoutVariant('esim'));
        Livewire::actingAs($u)->test(Catalogue::class)->assertOk()->assertSee('Browse by country');

        // Paperwhite → variant-b: header then hero. Same content still renders.
        $this->useTheme('paperwhite');
        $this->assertSame('variant-b', ThemePreset::layoutVariant('esim'));
        Livewire::actingAs($u)->test(Catalogue::class)->assertOk()->assertSee('Browse by country');
    }

    public function test_numbers_renders_both_orders(): void
    {
        $u = User::factory()->create();

        $this->assertSame('variant-a', ThemePreset::layoutVariant('numbers'));
        Livewire::actingAs($u)->test(GetNumber::class)->assertOk();

        // Coral Current → variant-b: bento leads, hero below.
        $this->useTheme('coral-current');
        $this->assertSame('variant-b', ThemePreset::layoutVariant('numbers'));
        Livewire::actingAs($u)->test(GetNumber::class)->assertOk();
    }

    public function test_absent_choice_falls_back_to_variant_a(): void
    {
        // A theme with no esim/numbers key (aurora-shift) → variant-a everywhere.
        $this->useTheme('aurora-shift');
        $this->assertSame('variant-a', ThemePreset::layoutVariant('esim'));
        $this->assertSame('variant-a', ThemePreset::layoutVariant('numbers'));
    }
}
