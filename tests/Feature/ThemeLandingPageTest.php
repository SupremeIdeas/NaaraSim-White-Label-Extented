<?php

namespace Tests\Feature;

use App\Livewire\Admin\ThemePicker;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Models\User;
use App\Support\LandingHeroLibrary;
use App\Support\ThemePreset;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Per-theme custom landing pages (owner request, 2026-09-07): a theme with
 * a genuinely unique, hand-built landing layout (neon-vertex, midnight-
 * signal) takes over the whole homepage; every other theme keeps using the
 * existing SiteContent/PageBuilder homepage content untouched. The admin
 * editor is schema-driven off LandingHeroLibrary, not hardcoded per field.
 */
class ThemeLandingPageTest extends TestCase
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

    public function test_naara_official_homepage_is_unaffected(): void
    {
        $this->assertSame('naara-official', ThemePreset::slug());
        $this->assertSame([], ThemePreset::landingContent());

        $this->get('/')->assertOk();
    }

    public function test_neon_vertex_homepage_renders_its_custom_landing_page(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePreset::bust();

        $this->assertSame('neon-vertex', ThemePreset::sectionStyle('landing_hero'));

        // The headline's last word renders in its own gradient <span> (like
        // the default hero's own gradient-last-word treatment), so assert on
        // the description instead of the full headline string.
        $this->get('/')
            ->assertOk()
            ->assertSee(LandingHeroLibrary::defaultsFor('neon-vertex')['description']);
    }

    public function test_midnight_signal_homepage_renders_its_custom_landing_page(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'midnight-signal');
        ThemePreset::bust();

        $this->get('/')
            ->assertOk()
            ->assertSee(LandingHeroLibrary::defaultsFor('midnight-signal')['headline']);
    }

    public function test_landing_content_falls_back_to_field_defaults_when_unset(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePreset::bust();

        $content = ThemePreset::landingContent();

        $this->assertSame(LandingHeroLibrary::defaultsFor('neon-vertex')['headline'], $content['headline']);
        $this->assertSame('xl', $content['image_radius']);
    }

    public function test_landing_content_rejects_an_out_of_whitelist_select_value(): void
    {
        ThemePresetModel::where('slug', 'neon-vertex')->update([
            'landing_content' => ['image_position' => 'javascript:alert(1)'],
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePreset::bust();

        $this->assertSame('right', ThemePreset::landingContent()['image_position']);
    }

    public function test_landing_content_rejects_a_non_url_image_value(): void
    {
        ThemePresetModel::where('slug', 'neon-vertex')->update([
            'landing_content' => ['image' => 'javascript:alert(1)'],
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePreset::bust();

        $this->assertSame(LandingHeroLibrary::defaultsFor('neon-vertex')['image'], ThemePreset::landingContent()['image']);
    }

    // ---- Admin editor -------------------------------------------------------

    public function test_edit_landing_is_rejected_for_a_theme_still_on_default(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editLanding', 'verdant-pulse')
            ->assertSet('showLandingModal', false);
    }

    public function test_edit_landing_opens_seeded_with_defaults_for_a_custom_style(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editLanding', 'neon-vertex')
            ->assertSet('showLandingModal', true)
            ->assertSet('landingEditingSlug', 'neon-vertex')
            ->assertSet('landingValues.headline', LandingHeroLibrary::defaultsFor('neon-vertex')['headline']);
    }

    public function test_save_landing_persists_text_field_edits(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editLanding', 'neon-vertex')
            ->set('landingValues.headline', 'Custom edited headline')
            ->set('landingValues.description', 'A custom description under the max length.')
            ->call('saveLanding')
            ->assertHasNoErrors()
            ->assertSet('showLandingModal', false);

        $saved = ThemePresetModel::where('slug', 'neon-vertex')->value('landing_content');
        $this->assertSame('Custom edited headline', $saved['headline']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.landing_updated']);
    }

    public function test_save_landing_uploads_and_persists_the_image(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editLanding', 'neon-vertex')
            ->set('landing_image_upload', UploadedFile::fake()->image('feature.jpg', 1200, 1200))
            ->call('saveLanding')
            ->assertHasNoErrors();

        $saved = ThemePresetModel::where('slug', 'neon-vertex')->value('landing_content');
        $this->assertNotEmpty($saved['image'] ?? null);
    }

    public function test_save_landing_rejects_a_headline_over_the_field_max_length(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editLanding', 'neon-vertex')
            ->set('landingValues.headline', str_repeat('a', 90))
            ->call('saveLanding')
            ->assertHasErrors('landingValues.headline');
    }

    public function test_a_non_admin_cannot_edit_or_save_landing_content(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ThemePicker::class)->assertStatus(403);
    }
}
