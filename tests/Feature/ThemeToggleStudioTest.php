<?php

namespace Tests\Feature;

use App\Livewire\Admin\ThemeToggleStudio;
use App\Models\User;
use App\Support\ThemeToggleSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ThemeToggleStudioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_unconfigured_install_defaults_to_sun_moon(): void
    {
        $this->assertSame('sun-moon', ThemeToggleSettings::current());
    }

    public function test_studio_page_is_admin_only(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(ThemeToggleStudio::class)->assertForbidden();
    }

    public function test_admin_can_select_and_save_each_preset(): void
    {
        foreach (array_keys(ThemeToggleSettings::PRESETS) as $slug) {
            Livewire::actingAs($this->admin())->test(ThemeToggleStudio::class)
                ->call('selectStyle', $slug)
                ->assertSet('style', $slug)
                ->call('save')
                ->assertSet('saved', $slug);

            $this->assertSame($slug, ThemeToggleSettings::current());
        }
    }

    public function test_unknown_preset_slug_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(ThemeToggleStudio::class)
            ->call('selectStyle', 'not-a-real-preset')
            ->assertSet('style', 'sun-moon'); // unchanged — the click was ignored

        // A direct call bypassing the picker's own hidden state is refused too.
        Livewire::actingAs($this->admin())->test(ThemeToggleStudio::class)
            ->set('style', 'not-a-real-preset')
            ->call('save')
            ->assertForbidden();
    }

    public function test_non_privileged_user_cannot_save(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(ThemeToggleStudio::class)->assertForbidden();
    }

    public function test_save_persists_and_flushes_cache_for_the_public_resolver(): void
    {
        ThemeToggleSettings::save('aurora-pill');
        $this->assertSame('aurora-pill', ThemeToggleSettings::current());

        ThemeToggleSettings::save('sun-moon');
        $this->assertSame('sun-moon', ThemeToggleSettings::current());
    }

    public function test_saved_style_renders_through_the_theme_toggle_component(): void
    {
        ThemeToggleSettings::save('horizon-track');

        $html = \Illuminate\Support\Facades\Blade::render('<x-theme-toggle />');

        $this->assertStringContainsString('nx-theme-toggle--horizon-track', $html);
    }
}
