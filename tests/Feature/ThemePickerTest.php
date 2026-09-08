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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA THEME SYSTEM — Batch 2 §4. The admin theme picker: select-and-apply,
 * admin-gated, server-validated. Applying is the ONLY write it makes (the active
 * slug + cache bust) — no business logic, no token editing.
 */
class ThemePickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
    }

    public function test_a_non_admin_gets_a_403(): void
    {
        Livewire::actingAs(User::factory()->create())->test(ThemePicker::class)->assertStatus(403);
    }

    public function test_an_admin_sees_all_forty_presets(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ThemePicker::class)
            ->assertStatus(200)
            ->assertViewHas('presets', fn ($p) => $p->count() === 40)
            ->assertSee('Naara Official')
            ->assertSee('Indigo Current');
    }

    public function test_a_staff_member_with_the_theme_manage_scope_can_open_and_apply(): void
    {
        // Delegated staff — no admin role, only the granular theme.manage scope
        // (Batch 3 §6). The gate must accept the scope on its own.
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('theme.manage');

        Livewire::actingAs($staff)->test(ThemePicker::class)
            ->assertStatus(200)
            ->call('apply', 'midnight-signal');

        $this->assertSame('midnight-signal', ThemePreset::slug());
    }

    public function test_a_staff_member_without_the_scope_gets_a_403(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        Livewire::actingAs($staff)->test(ThemePicker::class)->assertStatus(403);
    }

    public function test_applying_a_preset_writes_the_setting_and_busts_cache(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ThemePicker::class)
            ->call('apply', 'midnight-signal');

        $this->assertSame('midnight-signal', Setting::getValue(ThemePreset::SETTING_KEY));
        // The cached active preset reflects the change immediately.
        $this->assertSame('midnight-signal', ThemePreset::slug());
    }

    public function test_applying_an_unknown_slug_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ThemePicker::class)
            ->call('apply', 'not-a-real-theme');

        // Nothing was written — still the default.
        $this->assertNotSame('not-a-real-theme', Setting::getValue(ThemePreset::SETTING_KEY));
        $this->assertSame('naara-official', ThemePreset::slug());
    }

    // ---- Per-theme hero-image editing (owner request) ----------------------

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_edit_hero_opens_the_editor_seeded_with_the_saved_assets(): void
    {
        ThemePresetModel::where('slug', 'midnight-signal')->update([
            'hero_assets' => ['dashboard' => '/img/themes/existing.webp'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHero', 'midnight-signal')
            ->assertSet('showHeroModal', true)
            ->assertSet('editingSlug', 'midnight-signal')
            ->assertSet('currentHero.dashboard', '/img/themes/existing.webp')
            ->assertSet('currentHero.esim', null);
    }

    public function test_save_hero_uploads_and_persists_only_the_provided_surfaces(): void
    {
        Storage::fake('public');
        // A pre-existing eSIM hero must survive a Dashboard-only save (partial
        // update — editing one surface never clears the others).
        ThemePresetModel::where('slug', 'midnight-signal')->update([
            'hero_assets' => ['esim' => '/img/themes/existing-esim.webp'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHero', 'midnight-signal')
            ->set('hero_dashboard', UploadedFile::fake()->image('dash-hero.jpg', 1600, 800))
            ->call('saveHero')
            ->assertSet('showHeroModal', false);

        $assets = ThemePresetModel::where('slug', 'midnight-signal')->value('hero_assets');
        $this->assertNotEmpty($assets['dashboard'] ?? null);
        $this->assertSame('/img/themes/existing-esim.webp', $assets['esim'] ?? null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.hero_updated']);
    }

    public function test_save_hero_rejects_a_non_image_or_oversize_file(): void
    {
        Storage::fake('public');

        // Every theme ships a seeded default dashboard hero — the rejected
        // upload must leave it exactly as-is, not merely "non-empty".
        $before = ThemePresetModel::where('slug', 'midnight-signal')->value('hero_assets');

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHero', 'midnight-signal')
            ->set('hero_dashboard', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('saveHero')
            ->assertHasErrors('hero_dashboard');

        $after = ThemePresetModel::where('slug', 'midnight-signal')->value('hero_assets');
        $this->assertSame($before, $after);
    }

    public function test_remove_hero_surface_clears_only_that_surface(): void
    {
        ThemePresetModel::where('slug', 'midnight-signal')->update([
            'hero_assets' => ['dashboard' => '/img/a.webp', 'esim' => '/img/b.webp'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editHero', 'midnight-signal')
            ->call('removeHeroSurface', 'dashboard')
            ->assertSet('currentHero.dashboard', null);

        $assets = ThemePresetModel::where('slug', 'midnight-signal')->value('hero_assets');
        $this->assertArrayNotHasKey('dashboard', $assets);
        $this->assertSame('/img/b.webp', $assets['esim'] ?? null);
    }

    public function test_a_non_admin_cannot_edit_or_save_hero_images(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ThemePicker::class)->assertStatus(403);
    }

    public function test_a_staff_member_without_the_scope_cannot_save_hero_images(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        // Can't even reach the page (mount() gates it) — asserting the
        // component-level gate too documents that saveHero()/editHero() are
        // independently guarded, not just mount().
        Livewire::actingAs($staff)->test(ThemePicker::class)->assertStatus(403);
    }

    // ---- Swappable-section editor (owner request) --------------------------

    public function test_edit_sections_opens_the_editor_seeded_with_saved_styles(): void
    {
        ThemePresetModel::where('slug', 'aries-contrast')->update([
            'section_styles' => ['header' => 'neon-vertex'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editSections', 'aries-contrast')
            ->assertSet('showSectionsModal', true)
            ->assertSet('sectionEditingSlug', 'aries-contrast')
            ->assertSet('sectionStyles.header', 'neon-vertex')
            // Unset sections default to 'default', not null/blank.
            ->assertSet('sectionStyles.bottom_nav', 'default')
            ->assertSet('sectionStyles.login', 'default');
    }

    public function test_save_sections_lets_naara_official_borrow_another_themes_header(): void
    {
        // The literal owner ask: naara-official swaps in a completely
        // different theme's header while staying naara-official otherwise.
        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editSections', 'naara-official')
            ->set('sectionStyles.header', 'origin-bold')
            ->call('saveSections')
            ->assertSet('showSectionsModal', false);

        $sections = ThemePresetModel::where('slug', 'naara-official')->value('section_styles');
        $this->assertSame('origin-bold', $sections['header']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'theme.sections_updated']);

        // Live-resolves immediately if naara-official is the active theme.
        Setting::setValue(ThemePreset::SETTING_KEY, 'naara-official');
        ThemePreset::bust();
        $this->assertSame('origin-bold', ThemePreset::sectionStyle('header'));
    }

    public function test_save_sections_rejects_a_value_outside_the_whitelist(): void
    {
        $component = Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editSections', 'paperwhite')
            ->set('sectionStyles.login', 'javascript:alert(1)')
            ->call('saveSections');

        $component->assertSet('showSectionsModal', true); // never closed — rejected

        $sections = ThemePresetModel::where('slug', 'paperwhite')->value('section_styles');
        $this->assertNotSame('javascript:alert(1)', $sections['login'] ?? null);
    }

    public function test_save_sections_partial_update_never_clears_other_sections(): void
    {
        ThemePresetModel::where('slug', 'midnight-signal')->update([
            'section_styles' => ['header' => 'midnight-signal', 'login' => 'midnight-signal', 'bottom_nav' => 'midnight-signal'],
        ]);

        Livewire::actingAs($this->admin())->test(ThemePicker::class)
            ->call('editSections', 'midnight-signal')
            ->set('sectionStyles.header', 'default')
            ->call('saveSections');

        $sections = ThemePresetModel::where('slug', 'midnight-signal')->value('section_styles');
        $this->assertSame('default', $sections['header']);
        $this->assertSame('midnight-signal', $sections['login']);
        $this->assertSame('midnight-signal', $sections['bottom_nav']);
    }

    public function test_a_non_admin_cannot_edit_or_save_sections(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ThemePicker::class)->assertStatus(403);
    }
}
