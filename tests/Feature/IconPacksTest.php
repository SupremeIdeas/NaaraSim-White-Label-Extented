<?php

namespace Tests\Feature;

use App\Livewire\Admin\IconPacksPage;
use App\Models\User;
use App\Support\IconPacks\IconPacks;
use App\Support\IconPacks\SlotIcons;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Icon packs: one canonical SVG set, 40 colourways, recoloured on the fly and kept readable on both light and dark surfaces. */
class IconPacksTest extends TestCase
{
    use RefreshDatabase;

    private function canonical(): string
    {
        return (string) file_get_contents(public_path('images/icon-packs/more/svg/app-more-numbers.svg'));
    }

    public function test_the_registry_has_every_pack_and_the_default(): void
    {
        $this->assertCount(40, IconPacks::all());
        $this->assertTrue(IconPacks::exists('orchid-lime'));
        $this->assertTrue(IconPacks::exists('arctic-teal'));
        $this->assertFalse(IconPacks::exists('naara-official'), 'the Naara Official colourway must not ship in a white label');
        $this->assertSame('orchid-lime', IconPacks::defaultSlug());
        foreach (IconPacks::all() as $slug => $p) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $p['ic'], $slug);
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $p['ac'], $slug);
        }
    }

    public function test_recolouring_changes_only_the_two_palette_colours(): void
    {
        $base = $this->canonical();
        $out = IconPacks::recolor($base, 'arctic-teal');
        $strip = fn (string $s) => preg_replace('/--nic-ic:#[0-9A-Fa-f]+;--nic-ac:#[0-9A-Fa-f]+/', 'X', $s);
        $this->assertSame($strip($base), $strip($out), 'shapes are identical across packs');
        $this->assertStringContainsString('--nic-ic:#146E78;', $out);
    }

    public function test_a_near_black_pack_is_lifted_on_dark_surfaces_and_a_pale_one_darkened_on_light(): void
    {
        $dark = IconPacks::colors('aries-contrast', 'dark');
        $this->assertNotSame('#0A0A0A', $dark['ic'], 'near-black line colour must not sit on a dark surface unchanged');
        $this->assertSame('#0A0A0A', IconPacks::colors('aries-contrast', 'light')['ic'], 'it already reads on white');
        foreach (IconPacks::all() as $slug => $_) {
            foreach (['light' => '#FFFFFF', 'dark' => '#0B1624'] as $mode => $surface) {
                $c = IconPacks::colors($slug, $mode)['ic'];
                $this->assertGreaterThanOrEqual(3.0, $this->ratio($c, $surface), "$slug $mode line colour $c is too faint");
            }
        }
    }

    public function test_the_route_serves_the_recoloured_file_with_a_long_cache_and_rejects_junk(): void
    {
        $r = $this->get('/icon-pack/arctic-teal/more/app-more-numbers.svg');
        $r->assertOk()->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');
        $this->assertStringContainsString('immutable', (string) $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('--nic-ic:#146E78', $r->getContent());
        $this->get('/icon-pack/arctic-teal/admin/admin-more-users-dark.svg')->assertOk();
        $this->get('/icon-pack/not-a-pack/more/app-more-numbers.svg')->assertNotFound();
        $this->get('/icon-pack/arctic-teal/nowhere/app-more-numbers.svg')->assertNotFound();
        $this->get('/icon-pack/arctic-teal/more/does-not-exist.svg')->assertNotFound();
        $this->get('/icon-pack/arctic-teal/more/..%2F..%2F.env.svg')->assertNotFound();
    }

    public function test_menu_icons_follow_the_active_pack_and_the_default_keeps_the_bundled_files(): void
    {
        $this->assertSame('follow-theme', IconPacks::choice());
        $d = SlotIcons::resolve('numbers', 'app');
        $this->assertStringContainsString('/icon-pack/orchid-lime/more/app-more-numbers.svg', $d['light']);

        IconPacks::setChoice('arctic-teal');
        $a = SlotIcons::resolve('numbers', 'app');
        $this->assertStringContainsString('/icon-pack/arctic-teal/more/app-more-numbers.svg', $a['light']);
        $this->assertStringContainsString('app-more-numbers-dark.svg', $a['dark']);
    }

    public function test_the_set_ships_only_what_a_white_label_has_and_nothing_master_only(): void
    {
        foreach (array_merge(array_values(SlotIcons::MORE), array_values(SlotIcons::ADMIN)) as $slot) {
            foreach (['business', 'brain', 'n2n', 'handbook', 'merchant-widget', 'white-label', 'naara-id'] as $banned) {
                $this->assertStringNotContainsString($banned, $slot);
            }
        }
        foreach (glob(public_path('images/icon-packs/*/svg/*.svg')) as $f) {
            $this->assertDoesNotMatchRegularExpression('/business|brain|n2n|handbook|merchant-widget|white-label|naara-id/', basename($f), basename($f));
        }
        foreach (array_keys(SlotIcons::ADMIN) as $route) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($route), "$route is listed but this install has no such page");
        }
    }

    public function test_an_admin_can_choose_a_pack_and_a_stranger_cannot(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $admin->assignRole('super_admin');
        Livewire::actingAs($admin)->test(IconPacksPage::class)->set('pack', 'copper-line')->call('save');
        $this->assertSame('copper-line', IconPacks::choice());
        $this->assertSame('copper-line', IconPacks::activeSlug());

        Livewire::actingAs($admin)->test(IconPacksPage::class)->set('pack', 'bogus')->call('save')->assertStatus(422);
        $this->assertSame('copper-line', IconPacks::choice());
    }

    public function test_following_the_theme_uses_the_pack_named_like_the_theme(): void
    {
        $this->seed(\Database\Seeders\ThemePresetSeeder::class);
        \App\Models\Setting::setValue('theme.active', 'cobalt-frost');
        \App\Support\ThemePreset::bust();
        $this->assertSame('follow-theme', IconPacks::choice());
        $slug = \App\Support\ThemePreset::slug();
        $this->assertSame(IconPacks::exists($slug) ? $slug : 'orchid-lime', IconPacks::activeSlug());
    }

    private function ratio(string $a, string $b): float
    {
        $l = function (string $h) {
            $h = ltrim($h, '#');
            $c = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
            $f = fn ($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $f($c[0]) + 0.7152 * $f($c[1]) + 0.0722 * $f($c[2]);
        };
        [$x, $y] = [$l($a), $l($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }
}
