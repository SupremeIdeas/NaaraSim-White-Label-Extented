<?php

namespace Tests\Feature;

use App\Livewire\Admin\AppBuilder;
use App\Support\AppStudio;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA-BUILD-21 — App Studio native config surface. Grounded on the real Median
 * appConfig.json shape. Focus: everything auto-populates so a build works with
 * zero manual input, and overrides persist.
 */
class AppStudioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_every_field_auto_populates_so_a_build_works_with_zero_input(): void
    {
        // Nothing saved — resolved getters must still return sane values.
        $this->assertNotSame('', AppStudio::initialUrl());
        $this->assertNotSame('', AppStudio::displayName());
        $this->assertStringStartsWith('com.supremeideas.', AppStudio::androidPackage());
        $this->assertTrue(AppStudio::canGenerateWithDefaults());

        // Default link rules: own domain internal, social app_browser, catch-all external.
        $rules = AppStudio::linkRules();
        $actions = collect($rules)->pluck('action')->all();
        $this->assertContains('internal', $actions);
        $this->assertContains('app_browser', $actions);
        $this->assertSame('external', end($rules)['action']); // catch-all last
        $this->assertSame('*', end($rules)['pattern']);
    }

    public function test_permission_descriptions_are_prefilled_and_never_blank_when_enabled(): void
    {
        $perms = AppStudio::permissions();
        $this->assertTrue($perms['camera']['enabled']);         // KYC capture is real
        $this->assertNotSame('', $perms['camera']['description']);
        $this->assertFalse($perms['geolocation']['enabled']);   // off until a location feature is on
        $this->assertStringContainsString(AppStudio::displayName(), $perms['camera']['description']);
    }

    public function test_median_config_matches_the_real_shape(): void
    {
        $cfg = AppStudio::medianConfig();
        // The real Median top-level groups.
        foreach (['general', 'navigation', 'styling', 'permissions', 'services', 'security'] as $k) {
            $this->assertArrayHasKey($k, $cfg);
        }
        $this->assertArrayHasKey('initialUrl', $cfg['general']);
        $this->assertArrayHasKey('androidPackageName', $cfg['general']);
        $this->assertArrayHasKey('regexInternalExternal', $cfg['navigation']);
        $this->assertTrue($cfg['navigation']['regexInternalExternal']['active']);
        // disallow_insecure_http default → allowInsecure false.
        $this->assertFalse($cfg['permissions']['usesGeolocation']);
        $this->assertFalse($cfg['security']['network']['allowInsecure']);
        // First link rule is always non-web → external (matches Median).
        $first = $cfg['navigation']['regexInternalExternal']['rules'][0];
        $this->assertSame('external', $first['mode']);
    }

    public function test_offline_html_defaults_to_branded_then_honours_custom(): void
    {
        $default = AppStudio::offlineHtml();
        $this->assertStringContainsString('offline', strtolower($default));
        $this->assertFalse(AppStudio::offlineIsCustom());

        AppStudio::save(['offline_style' => 'custom', 'offline_html' => '<h1>Nope</h1>']);
        $this->assertTrue(AppStudio::offlineIsCustom());
        $this->assertStringContainsString('Nope', AppStudio::offlineHtml());
    }

    public function test_admin_can_save_studio_and_overrides_reach_the_median_config(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AppBuilder::class)
            ->set('studio.app_display_name', 'NaaraGo')
            ->set('permissions.geolocation.enabled', true)
            ->set('studio.disallow_insecure_http', false)
            ->call('saveStudio')
            ->assertHasNoErrors();

        $this->assertSame('NaaraGo', AppStudio::displayName());
        $cfg = AppStudio::medianConfig();
        $this->assertSame('NaaraGo', $cfg['general']['appName']);
        $this->assertTrue($cfg['permissions']['usesGeolocation']);
        $this->assertTrue($cfg['security']['network']['allowInsecure']); // disallow off → insecure allowed
    }

    public function test_invalid_package_name_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AppBuilder::class)
            ->set('studio.android_package_name', 'notavalidpackage')
            ->call('saveStudio')
            ->assertHasErrors('studio.android_package_name');
    }
}
