<?php

namespace Tests\Feature;

use App\Livewire\Admin\ThemePicker;
use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Models\User;
use App\Support\ThemePageLibrary;
use App\Support\ThemePreset;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The full per-theme page suite (owner request, 2026-09-07): "for each
 * theme, will and must carry its own homepage, about us page, and 3 extra
 * important page layouts." neon-vertex and midnight-signal are the first
 * two themes with hand-built about/how-it-works/contact pages; every other
 * theme keeps using the existing SiteContent/PageBuilder content untouched.
 * The admin editor is schema-driven off ThemePageLibrary, not hardcoded
 * per field — mirrors ThemeLandingPageTest's exact coverage shape.
 */
class ThemeFullPageSuiteTest extends TestCase
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

    public function test_naara_official_pages_are_unaffected(): void
    {
        $this->assertSame('naara-official', ThemePreset::slug());
        $this->assertSame([], ThemePreset::pageContent('about_page'));

        $this->get('/about')->assertOk();
        $this->get('/how-it-works')->assertOk();
        $this->get('/contact')->assertOk();
    }

    public function test_neon_vertex_renders_its_custom_about_page(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePreset::bust();

        $this->assertSame('neon-vertex', ThemePreset::sectionStyle('about_page'));

        $this->get('/about')
            ->assertOk()
            ->assertSee(ThemePageLibrary::defaultsFor('about_page', 'neon-vertex')['intro']);
    }

    public function test_neon_vertex_renders_its_custom_how_it_works_page(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePreset::bust();

        $this->get('/how-it-works')
            ->assertOk()
            ->assertSee(ThemePageLibrary::defaultsFor('how_it_works_page', 'neon-vertex')['headline']);
    }

    public function test_neon_vertex_renders_its_custom_contact_page(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePreset::bust();

        $this->get('/contact')
            ->assertOk()
            ->assertSee(ThemePageLibrary::defaultsFor('contact_page', 'neon-vertex')['headline']);
    }

    public function test_midnight_signal_renders_its_custom_about_page(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'midnight-signal');
        ThemePreset::bust();

        $this->get('/about')
            ->assertOk()
            ->assertSee(ThemePageLibrary::defaultsFor('about_page', 'midnight-signal')['intro']);
    }

    public function test_midnight_signal_renders_its_custom_how_it_works_page(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'midnight-signal');
        ThemePreset::bust();

        $this->get('/how-it-works')
            ->assertOk()
            ->assertSee(ThemePageLibrary::defaultsFor('how_it_works_page', 'midnight-signal')['headline']);
    }

    public function test_midnight_signal_renders_its_custom_contact_page(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'midnight-signal');
        ThemePreset::bust();

        $this->get('/contact')
            ->assertOk()
            ->assertSee(ThemePageLibrary::defaultsFor('contact_page', 'midnight-signal')['headline']);
    }

    public function test_page_content_falls_back_to_field_defaults_when_unset(): void
    {
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePreset::bust();

        $content = ThemePreset::pageContent('about_page');

        $this->assertSame(ThemePageLibrary::defaultsFor('about_page', 'neon-vertex')['headline'], $content['headline']);
    }

    public function test_page_content_ignores_data_saved_under_a_different_page_key(): void
    {
        ThemePresetModel::where('slug', 'neon-vertex')->update([
            'page_content' => ['contact_page' => ['headline' => 'Should not leak into about']],
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'neon-vertex');
        ThemePreset::bust();

        $this->assertSame(
            ThemePageLibrary::defaultsFor('about_page', 'neon-vertex')['headline'],
            ThemePreset::pageContent('about_page')['headline'],
        );
    }

    // ---- Admin editor -------------------------------------------------------

    public function test_edit_page_is_rejected_for_a_theme_still_on_default(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editPage', 'verdant-pulse', 'about_page')
            ->assertSet('showPageModal', false);
    }

    public function test_edit_page_is_rejected_for_an_unknown_page_key(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editPage', 'neon-vertex', 'pricing_page')
            ->assertSet('showPageModal', false);
    }

    public function test_edit_page_opens_seeded_with_defaults_for_a_custom_style(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editPage', 'neon-vertex', 'about_page')
            ->assertSet('showPageModal', true)
            ->assertSet('pageEditingSlug', 'neon-vertex')
            ->assertSet('pageEditingPage', 'about_page')
            ->assertSet('pageValues.headline', ThemePageLibrary::defaultsFor('about_page', 'neon-vertex')['headline']);
    }

    public function test_save_page_persists_text_field_edits(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editPage', 'neon-vertex', 'about_page')
            ->set('pageValues.headline', 'Custom edited about headline')
            ->call('savePage')
            ->assertHasNoErrors()
            ->assertSet('showPageModal', false);

        $saved = ThemePresetModel::where('slug', 'neon-vertex')->value('page_content');
        $this->assertSame('Custom edited about headline', $saved['about_page']['headline']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.page_updated']);
    }

    public function test_save_page_keeps_other_pages_untouched(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editPage', 'neon-vertex', 'contact_page')
            ->set('pageValues.headline', 'Custom edited contact headline')
            ->call('savePage')
            ->assertHasNoErrors();

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editPage', 'neon-vertex', 'about_page')
            ->assertSet('pageValues.headline', ThemePageLibrary::defaultsFor('about_page', 'neon-vertex')['headline']);

        $saved = ThemePresetModel::where('slug', 'neon-vertex')->value('page_content');
        $this->assertSame('Custom edited contact headline', $saved['contact_page']['headline']);
        $this->assertArrayNotHasKey('about_page', $saved);
    }

    public function test_save_page_rejects_a_headline_over_the_field_max_length(): void
    {
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editPage', 'neon-vertex', 'about_page')
            ->set('pageValues.headline', str_repeat('a', 90))
            ->call('savePage')
            ->assertHasErrors('pageValues.headline');
    }

    public function test_a_non_admin_cannot_edit_or_save_page_content(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ThemePicker::class)->assertStatus(403);
    }
}
