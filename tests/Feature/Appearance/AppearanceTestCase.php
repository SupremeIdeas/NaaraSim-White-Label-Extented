<?php

namespace Tests\Feature\Appearance;

use App\Models\Setting;
use App\Models\User;
use App\Support\Appearance\AppearanceResolver;
use App\Support\Appearance\LicensedSkins;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

abstract class AppearanceTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        // Baseline: only Surface counts as shipped/enabled, so each test opts other skins in via builtSkins().
        config(['appearance.built' => ['surface']]);
        \App\Models\AppearancePreset::where('kind', 'skin')->where('key', '!=', 'surface')->update(['enabled' => false]);
        Cache::flush();
        AppearanceResolver::forgetPlatform();
    }

    protected function member(array $attrs = []): User
    {
        return User::factory()->create(['is_active' => true] + $attrs);
    }

    protected function admin(string $role = 'admin'): User
    {
        $u = $this->member();
        $u->assignRole($role);

        return $u;
    }

    /** Pretend a skin's stylesheet has shipped (so it can be enabled/picked) for the duration of one test. */
    protected function builtSkins(array $skins): void
    {
        config(['appearance.built' => array_values(array_unique(array_merge(['surface'], $skins)))]);
        \App\Models\AppearancePreset::where('kind', 'skin')->whereIn('key', $skins)->update(['enabled' => true]);
        // A fork only ever offers the skins its licence unlocks (Prompt 22); these fixtures stand in for a licence that unlocks them all.
        $all = array_values(array_unique(array_merge(['surface'], $skins)));
        LicensedSkins::storeAllowance(count($all));
        LicensedSkins::saveSelection($all);
        AppearanceResolver::forgetPlatform();
    }

    protected function setting(string $key, mixed $value): void
    {
        Setting::setValue($key, $value, 'ui');
        AppearanceResolver::forgetPlatform();
    }
}
