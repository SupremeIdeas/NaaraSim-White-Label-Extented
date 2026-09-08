<?php

namespace Tests\Feature;

use App\Livewire\Admin\ThemePicker;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Models\User;
use App\Support\ThemePreset;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin colour override (owner request, 2026-09-07): "admin Also change
 * color pallet any any theme he picks... advance settings to change each
 * color with color code and save to override each color then with a
 * reset to default color." Works on ANY theme, including naara-official —
 * an override is stored separately from the seeded palette (`tokens`), so
 * "reset to default" can never lose the original.
 */
class ThemeColorOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    // ---- hex <-> channel-triple conversion --------------------------------

    public function test_hex_to_channel_triple_converts_correctly(): void
    {
        $this->assertSame('10 110 110', ThemePreset::hexToChannelTriple('#0a6e6e'));
        $this->assertSame('255 255 255', ThemePreset::hexToChannelTriple('ffffff'));
        $this->assertSame('0 0 0', ThemePreset::hexToChannelTriple('#000000'));
    }

    public function test_hex_to_channel_triple_rejects_invalid_input(): void
    {
        $this->assertNull(ThemePreset::hexToChannelTriple('not-a-color'));
        $this->assertNull(ThemePreset::hexToChannelTriple('#fff'));
        $this->assertNull(ThemePreset::hexToChannelTriple('javascript:alert(1)'));
    }

    public function test_channel_triple_to_hex_converts_correctly(): void
    {
        $this->assertSame('#0a6e6e', ThemePreset::channelTripleToHex('10 110 110'));
        $this->assertSame('#ffffff', ThemePreset::channelTripleToHex('255 255 255'));
    }

    // ---- resolver: overrides merge into tokens.colors ----------------------

    public function test_naara_official_colors_are_unaffected_by_default(): void
    {
        $before = ThemePreset::active()['tokens']['colors'];
        $this->assertSame($before, ThemePreset::active()['tokens']['colors']);
    }

    public function test_a_saved_override_replaces_the_active_theme_color(): void
    {
        ThemePresetModel::where('slug', 'naara-official')->update([
            'color_overrides' => ['primary' => '200 30 30'],
        ]);
        ThemePreset::bust();

        $this->assertSame('200 30 30', ThemePreset::active()['tokens']['colors']['primary']);
    }

    public function test_an_override_never_touches_the_seeded_tokens_column(): void
    {
        $originalPrimary = ThemePresetModel::where('slug', 'naara-official')->value('tokens')['colors']['primary'];

        ThemePresetModel::where('slug', 'naara-official')->update([
            'color_overrides' => ['primary' => '200 30 30'],
        ]);

        $this->assertSame($originalPrimary, ThemePresetModel::where('slug', 'naara-official')->value('tokens')['colors']['primary']);
    }

    public function test_an_invalid_override_value_is_ignored_and_the_default_is_kept(): void
    {
        $row = ThemePresetModel::where('slug', 'naara-official')->first();
        $defaultPrimary = $row->tokens['colors']['primary'];

        ThemePresetModel::where('slug', 'naara-official')->update([
            'color_overrides' => ['primary' => 'javascript:alert(1)'],
        ]);
        ThemePreset::bust();

        $this->assertSame($defaultPrimary, ThemePreset::active()['tokens']['colors']['primary']);
    }

    public function test_the_css_variable_output_reflects_an_override(): void
    {
        // naara-official is the built-in default and deliberately emits no
        // override CSS of its own (its look ships in the static bundle) —
        // use a real switchable preset instead to see emitVars() at work.
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePresetModel::where('slug', 'neon-vertex')->update([
            'color_overrides' => ['primary' => '200 30 30'],
        ]);
        ThemePreset::bust();

        $this->assertStringContainsString('--brand-primary:200 30 30', ThemePreset::styleCss());
    }

    // ---- admin editor -------------------------------------------------------

    public function test_edit_colors_seeds_the_form_with_current_effective_values(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'naara-official')
            ->assertSet('showColorsModal', true)
            ->assertSet('colorEditingSlug', 'naara-official')
            ->assertSet('colorValues.primary', ThemePreset::channelTripleToHex(
                ThemePresetModel::where('slug', 'naara-official')->first()->tokens['colors']['primary']
            ));
    }

    public function test_edit_colors_prefers_an_existing_override_over_the_default(): void
    {
        ThemePresetModel::where('slug', 'naara-official')->update([
            'color_overrides' => ['accent' => '10 20 30'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'naara-official')
            ->assertSet('colorValues.accent', '#0a141e');
    }

    public function test_save_colors_persists_a_valid_hex_value_as_a_channel_triple(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'naara-official')
            ->set('colorValues.primary', '#ff0000')
            ->call('saveColors')
            ->assertHasNoErrors()
            ->assertSet('showColorsModal', false);

        $saved = ThemePresetModel::where('slug', 'naara-official')->value('color_overrides');
        $this->assertSame('255 0 0', $saved['primary']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.colors_updated']);
    }

    public function test_save_colors_ignores_values_that_match_the_seeded_default(): void
    {
        // Opening the modal and saving without changing anything (or saving
        // right after "Reset all to default" restores the form to the
        // defaults) must not re-flag the theme as customized — otherwise a
        // reflexive Save click would pin every colour to today's default
        // forever, defeating "reset to default" and hiding future palette
        // retunes behind a phantom override.
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'naara-official')
            ->call('saveColors')
            ->assertHasNoErrors();

        $saved = ThemePresetModel::where('slug', 'naara-official')->value('color_overrides');
        $this->assertSame([], $saved);
    }

    public function test_save_colors_only_persists_the_keys_that_actually_changed(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'naara-official')
            ->set('colorValues.primary', '#ff0000')
            ->call('saveColors')
            ->assertHasNoErrors();

        $saved = ThemePresetModel::where('slug', 'naara-official')->value('color_overrides');
        $this->assertSame(['primary' => '255 0 0'], $saved);
    }

    public function test_reset_all_then_a_reflexive_save_leaves_no_overrides(): void
    {
        ThemePresetModel::where('slug', 'naara-official')->update([
            'color_overrides' => ['primary' => '255 0 0', 'accent' => '0 255 0'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'naara-official')
            ->call('resetAllColors')
            ->call('saveColors')
            ->assertHasNoErrors();

        $saved = ThemePresetModel::where('slug', 'naara-official')->value('color_overrides');
        $this->assertSame([], $saved);
    }

    public function test_save_colors_rejects_a_malformed_hex_value(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'naara-official')
            ->set('colorValues.primary', 'not-a-hex-color')
            ->call('saveColors')
            ->assertHasErrors('colorValues.primary');
    }

    public function test_reset_color_removes_just_that_one_override_key(): void
    {
        ThemePresetModel::where('slug', 'naara-official')->update([
            'color_overrides' => ['primary' => '255 0 0', 'accent' => '0 255 0'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'naara-official')
            ->call('resetColor', 'primary')
            ->assertHasNoErrors();

        $saved = ThemePresetModel::where('slug', 'naara-official')->value('color_overrides');
        $this->assertArrayNotHasKey('primary', $saved);
        $this->assertSame('0 255 0', $saved['accent']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.color_reset']);
    }

    public function test_reset_all_colors_clears_every_override(): void
    {
        ThemePresetModel::where('slug', 'naara-official')->update([
            'color_overrides' => ['primary' => '255 0 0', 'accent' => '0 255 0'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'naara-official')
            ->call('resetAllColors');

        $saved = ThemePresetModel::where('slug', 'naara-official')->value('color_overrides');
        $this->assertSame([], $saved);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.colors_reset']);
    }

    public function test_edit_colors_rejects_an_unknown_theme(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editColors', 'not-a-real-theme')
            ->assertSet('showColorsModal', false);
    }

    public function test_a_non_admin_cannot_edit_or_save_colors(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ThemePicker::class)->assertStatus(403);
    }
}
