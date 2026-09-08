<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Setting;
use App\Models\SmsOrder;
use App\Models\User;
use App\Support\ThemePreset;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA THEME SYSTEM — Batch 2 §2. The dashboard home renders through a
 * theme-selected structural variant. Both variants must render the SAME content
 * (same blocks, same data) — only the order differs — and an unknown/absent
 * choice must fall back to variant-a without breaking.
 */
class DashboardLayoutVariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
    }

    private function userWithALine(): User
    {
        $u = User::factory()->create();
        SmsOrder::create([
            'user_id' => $u->id, 'provider' => 'fivesim', 'service_name' => 'whatsapp',
            'type' => 'otp', 'phone_number' => '+15550001234', 'status' => 'completed',
            'provider_cost' => 0.2, 'charged_to_user' => 0.5, 'profit' => 0.3, 'ordered_at' => now(),
        ]);

        return $u;
    }

    public function test_default_theme_renders_variant_a_content(): void
    {
        // naara-official → variant-a. All the key blocks are present.
        Livewire::actingAs($this->userWithALine())->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Connectivity')   // hero
            ->assertSee('eSIM')              // action tiles
            ->assertSee('My Lines');         // connectivity summary
    }

    public function test_variant_b_theme_renders_the_same_blocks_reordered(): void
    {
        // A data-forward persona uses variant-b for the dashboard home.
        Setting::setValue(ThemePreset::SETTING_KEY, 'fintra-clean');
        ThemePreset::bust();
        $this->assertSame('variant-b', ThemePreset::layoutVariant('dashboard_home'));

        // Same content renders — the wallet/lines simply lead now.
        Livewire::actingAs($this->userWithALine())->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Connectivity')
            ->assertSee('eSIM')
            ->assertSee('My Lines');
    }

    public function test_applying_a_theme_swaps_the_home_hero(): void
    {
        // naara-official ships a default home hero (the reference traveller scene).
        $this->assertSame('/img/themes/balloons.webp', ThemePreset::heroFor('dashboard'));

        // A themed persona carries its own hero image, shown on the home.
        Setting::setValue(ThemePreset::SETTING_KEY, 'aurora-shift');
        ThemePreset::bust();
        $this->assertSame('/img/themes/islands-female.webp', ThemePreset::heroFor('dashboard'));

        Livewire::actingAs($this->userWithALine())->test(Dashboard::class)
            ->assertOk()
            ->assertSee('/img/themes/islands-female.webp');
    }

    public function test_hero_for_rejects_a_non_path_value(): void
    {
        \App\Models\ThemePreset::where('slug', 'aurora-shift')
            ->update(['hero_assets' => ['dashboard' => 'javascript:alert(1)']]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'aurora-shift');
        ThemePreset::bust();

        $this->assertNull(ThemePreset::heroFor('dashboard'), 'Only same-origin paths / URLs are allowed.');
    }

    public function test_unknown_variant_falls_back_to_variant_a(): void
    {
        // Even a corrupt layout_variants value renders (defaults to variant-a).
        \App\Models\ThemePreset::where('slug', 'aurora-shift')
            ->update(['layout_variants' => ['dashboard_home' => 'variant-zzz']]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'aurora-shift');
        ThemePreset::bust();

        $this->assertSame('variant-a', ThemePreset::layoutVariant('dashboard_home'));
        Livewire::actingAs($this->userWithALine())->test(Dashboard::class)->assertOk()->assertSee('Connectivity');
    }
}
