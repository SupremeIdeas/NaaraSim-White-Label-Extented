<?php

namespace Tests\Feature\Appearance;

use App\Livewire\Admin\UserAppearance;
use App\Models\AppearancePreset;
use App\Models\AuditLog;
use App\Support\Appearance\AppearanceResolver;
use Livewire\Livewire;

class AppearanceAdminTest extends AppearanceTestCase
{
    public function test_only_admins_and_super_admins_may_open_it(): void
    {
        Livewire::actingAs($this->member())->test(UserAppearance::class)->assertForbidden();
        $staff = $this->admin('staff');
        Livewire::actingAs($staff)->test(UserAppearance::class)->assertForbidden();
        Livewire::actingAs($this->admin('admin'))->test(UserAppearance::class)->assertOk();
        Livewire::actingAs($this->admin('super_admin'))->test(UserAppearance::class)->assertOk();
    }

    public function test_the_default_skin_and_accent_must_stay_enabled(): void
    {
        Livewire::actingAs($this->admin())->test(UserAppearance::class)
            // Skins are licence-controlled in a fork (Prompt 22): the platform screen cannot toggle them at all; accents keep their own guard.
            ->call('toggle', 'skin', 'surface')->assertSet('error', 'Your skins are set by your licence. Choose them on the Your skins screen.')
            ->call('toggle', 'accent', 'teal')->assertSet('error', 'The default accent must stay enabled.');
        $this->assertTrue(AppearancePreset::where('key', 'surface')->value('enabled'));
    }

    public function test_a_skin_that_has_not_shipped_cannot_be_enabled_or_made_default(): void
    {
        Livewire::actingAs($this->admin())->test(UserAppearance::class)
            ->call('toggle', 'skin', 'calm')->assertSet('error', 'Your skins are set by your licence. Choose them on the Your skins screen.')
            ->call('setDefaultSkin', 'calm')->assertSet('error', 'Your skins are set by your licence. Choose them on the Your skins screen.');
        $this->assertFalse((bool) AppearancePreset::where('key', 'calm')->value('enabled'));
    }

    public function test_making_an_accent_default_enables_it_keeps_exactly_one_default_and_audits(): void
    {
        $adm = $this->admin();
        AppearancePreset::where('key', 'ocean')->update(['enabled' => false]);
        Livewire::actingAs($adm)->test(UserAppearance::class)->call('setDefaultAccent', 'ocean')->assertSet('message', 'Saved. Audit log written.');

        $this->assertSame(1, AppearancePreset::where('kind', 'accent')->where('is_default', true)->count());
        $this->assertTrue((bool) AppearancePreset::where('key', 'ocean')->value('enabled'));
        $this->assertSame('ocean', AppearanceResolver::for($this->member())['accent']);
        $this->assertTrue(AuditLog::where('action', 'appearance.admin_updated')->exists());
    }

    public function test_lock_and_custom_switches_flip_and_take_effect_immediately(): void
    {
        $c = Livewire::actingAs($this->admin())->test(UserAppearance::class);
        $c->call('toggleLock');
        $this->assertTrue(AppearanceResolver::platform()['locked']);
        $c->call('toggleCustom');
        $this->assertFalse(AppearanceResolver::platform()['allow_custom']);
        $c->call('toggleLock')->call('toggleCustom');
        $this->assertFalse(AppearanceResolver::platform()['locked']);
        $this->assertTrue(AppearanceResolver::platform()['allow_custom']);
    }

    public function test_disabling_an_accent_removes_it_from_what_members_can_choose(): void
    {
        Livewire::actingAs($this->admin())->test(UserAppearance::class)->call('toggle', 'accent', 'graphite');
        $this->assertNotContains('graphite', AppearanceResolver::platform()['accents']);
    }
}
