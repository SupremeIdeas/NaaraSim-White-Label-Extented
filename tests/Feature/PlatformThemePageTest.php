<?php

namespace Tests\Feature;

use App\Livewire\Admin\PlatformThemePage;
use App\Models\User;
use App\Support\GlassmorphismSettings;
use App\Support\PlatformTheme;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Dashboard theme. The panel is admin-only and every tuning value it
 * persists goes through the server-side clamp (PlatformTheme::save).
 */
class PlatformThemePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        PlatformTheme::flush();
        GlassmorphismSettings::flush();
    }

    public function test_the_page_is_admin_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/dashboard-theme')->assertNotFound();
    }

    public function test_it_saves_the_mode_and_clamps_tuning(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(PlatformThemePage::class)
            ->call('save', [
                'mode' => 'animated_gradient',
                'customize_dark' => true,
                'light' => ['up_intensity' => 9.0, 'lo_feather' => 5],
                'dark' => ['up_intensity' => 1.0],
            ])
            ->assertHasNoErrors();

        $c = PlatformTheme::current();
        $this->assertSame('animated_gradient', $c['mode']);
        $this->assertSame(1.5, $c['light']['up_intensity']); // clamped from 9.0
        $this->assertSame(70, $c['light']['lo_feather']);     // clamped from 5
        $this->assertStringContainsString('dashboard-bg--animated', PlatformTheme::bodyClass());
    }

    public function test_linked_dark_is_derived_from_light_on_save(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(PlatformThemePage::class)
            ->call('save', [
                'mode' => 'static_gradient',
                'customize_dark' => false, // linked
                'light' => ['lo_intensity' => 1.0, 'extra_color' => 'action', 'extra_alpha' => 0.3],
                'dark' => ['lo_intensity' => 1.0],
            ])
            ->assertHasNoErrors();

        $dark = PlatformTheme::current()['dark'];
        // Derivation eased the lower bloom and dropped coral from the extra stop.
        $this->assertSame(0.85, $dark['lo_intensity']);
        $this->assertSame(0.0, $dark['extra_alpha']);
    }

    public function test_reset_all_returns_to_the_default_mode(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        PlatformTheme::save(['mode' => 'image', 'image_light' => 'https://cdn.test/x.webp']);

        Livewire::actingAs($admin)->test(PlatformThemePage::class)
            ->call('resetAll')
            ->assertHasNoErrors();

        $this->assertSame('default', PlatformTheme::mode());
        $this->assertSame('', PlatformTheme::styleCss());
    }

    public function test_it_saves_the_glass_dial_and_clamps_out_of_range_values(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(PlatformThemePage::class)
            ->set('glass_opacity', 500)
            ->set('glass_blur', -5)
            ->call('saveGlass')
            ->assertHasErrors(['glass_opacity', 'glass_blur']);

        Livewire::actingAs($admin)->test(PlatformThemePage::class)
            ->set('glass_opacity', 80)
            ->set('glass_blur', 6)
            ->call('saveGlass')
            ->assertHasNoErrors();

        $this->assertSame(80, GlassmorphismSettings::opacity());
        $this->assertSame(6, GlassmorphismSettings::blur());
    }

    public function test_reset_glass_returns_to_the_shipped_default(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        GlassmorphismSettings::save(90, 20);

        Livewire::actingAs($admin)->test(PlatformThemePage::class)
            ->call('resetGlass')
            ->assertHasNoErrors();

        $this->assertSame(GlassmorphismSettings::DEFAULT_OPACITY, GlassmorphismSettings::opacity());
        $this->assertSame(GlassmorphismSettings::DEFAULT_BLUR, GlassmorphismSettings::blur());
    }

    public function test_glass_dial_is_admin_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(PlatformThemePage::class)
            ->set('glass_opacity', 80)
            ->call('saveGlass')
            ->assertStatus(403);

        $this->assertSame(GlassmorphismSettings::DEFAULT_OPACITY, GlassmorphismSettings::opacity());
    }
}
