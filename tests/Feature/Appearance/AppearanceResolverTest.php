<?php

namespace Tests\Feature\Appearance;

use App\Models\AppearancePreset;
use App\Models\UserAppearance;
use App\Support\Appearance\AppearanceResolver;
use App\Support\Appearance\UpdateUserAppearance;

class AppearanceResolverTest extends AppearanceTestCase
{
    public function test_a_fresh_install_renders_surface_and_naara_teal_with_no_rows(): void
    {
        $this->assertSame(0, UserAppearance::count());
        $r = AppearanceResolver::for($this->member());

        $this->assertSame('surface', $r['skin']);
        $this->assertSame('teal', $r['accent']);
        $this->assertNull($r['mode']);
        $this->assertSame(['round' => 'def', 'dens' => 'comf', 'ts' => 'def', 'depth' => 'soft', 'font' => 'naara', 'motion' => 'full'], $r['dials']);
    }

    public function test_guests_always_get_the_platform_defaults(): void
    {
        $r = AppearanceResolver::for(null);
        $this->assertSame(['surface', 'teal', false], [$r['skin'], $r['accent'], $r['user']]);
    }

    public function test_a_members_choice_applies_and_only_to_them(): void
    {
        $a = $this->member();
        $b = $this->member();
        (new UpdateUserAppearance)($a, ['accent' => 'ocean', 'mode' => 'light', 'ts' => 'l']);

        $ra = AppearanceResolver::for($a);
        $rb = AppearanceResolver::for($b);
        $this->assertSame(['ocean', 'light', 'l'], [$ra['accent'], $ra['mode'], $ra['dials']['ts']]);
        $this->assertSame(['teal', null, 'def'], [$rb['accent'], $rb['mode'], $rb['dials']['ts']]);
    }

    public function test_a_disabled_preset_falls_back_silently_and_re_enabling_restores_it_without_losing_the_row(): void
    {
        $u = $this->member();
        (new UpdateUserAppearance)($u, ['accent' => 'rose']);

        AppearancePreset::where('kind', 'accent')->where('key', 'rose')->update(['enabled' => false]);
        AppearanceResolver::forgetPlatform();
        $this->assertSame('teal', AppearanceResolver::for($u)['accent']);
        $this->assertSame('rose', UserAppearance::where('user_id', $u->id)->value('accent_key'), 'the member row is kept');

        AppearancePreset::where('kind', 'accent')->where('key', 'rose')->update(['enabled' => true]);
        AppearanceResolver::forgetPlatform();
        $this->assertSame('rose', AppearanceResolver::for($u)['accent']);
    }

    public function test_a_skin_whose_stylesheet_has_not_shipped_is_never_applied_even_if_stored(): void
    {
        $u = $this->member();
        UserAppearance::create(['user_id' => $u->id, 'skin_key' => 'calm']);
        AppearancePreset::where('kind', 'skin')->where('key', 'calm')->update(['enabled' => true]);   // an admin cannot make it real
        AppearanceResolver::forgetPlatform();

        $this->assertSame('surface', AppearanceResolver::for($u)['skin']);

        $this->builtSkins(['calm']);
        $this->assertSame('calm', AppearanceResolver::for($u)['skin']);
    }

    public function test_the_operator_lock_forces_defaults_for_members_but_not_admins(): void
    {
        $this->builtSkins(['calm']);
        $m = $this->member();
        $adm = $this->admin();
        (new UpdateUserAppearance)($m, ['skin' => 'calm', 'accent' => 'ocean', 'ts' => 'l']);
        (new UpdateUserAppearance)($adm, ['accent' => 'violet']);

        $this->setting(AppearanceResolver::LOCK, true);

        $rm = AppearanceResolver::for($m);
        $this->assertSame(['surface', 'teal', 'def', true], [$rm['skin'], $rm['accent'], $rm['dials']['ts'], $rm['locked']]);
        $this->assertSame('violet', AppearanceResolver::for($adm)['accent'], 'admins are exempt from the lock');
        $this->assertSame('ocean', UserAppearance::where('user_id', $m->id)->value('accent_key'), 'the lock never deletes a choice');
    }

    public function test_a_custom_accent_needs_a_valid_hex_and_the_switch_on(): void
    {
        $u = $this->member();
        (new UpdateUserAppearance)($u, ['accent' => 'custom', 'accent_hex' => '#8B5CF6']);

        $r = AppearanceResolver::for($u);
        $this->assertSame(['custom', '#8b5cf6'], [$r['accent'], $r['accent_hex']], 'stored lower-case');

        $this->setting(AppearanceResolver::ALLOW_CUSTOM, false);
        $r = AppearanceResolver::for($u);
        $this->assertSame(['teal', null], [$r['accent'], $r['accent_hex']]);
        $this->assertSame('#8b5cf6', UserAppearance::where('user_id', $u->id)->value('accent_hex'), 'the saved colour is not deleted');
    }

    public function test_the_default_can_be_changed_by_the_platform_and_is_what_unset_members_get(): void
    {
        AppearancePreset::where('kind', 'accent')->update(['is_default' => false]);
        AppearancePreset::where('kind', 'accent')->where('key', 'ocean')->update(['is_default' => true]);
        AppearanceResolver::forgetPlatform();

        $this->assertSame('ocean', AppearanceResolver::for($this->member())['accent']);
    }
}
