<?php

namespace Tests\Feature;

use App\Livewire\EsimCompatibility;
use App\Models\User;
use Database\Seeders\EsimCompatibleDeviceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * eSIM compatibility modal (esim_upgrade Part 2). Search, Apple/Android/Others
 * pill tabs, and accordions all read the seeded esim_compatible_devices table.
 */
class EsimCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EsimCompatibleDeviceSeeder::class);
    }

    public function test_the_device_catalogue_seeds_all_three_os_groups(): void
    {
        $this->assertGreaterThan(200, \App\Models\EsimCompatibleDevice::count());
        foreach (['apple', 'android', 'others'] as $os) {
            $this->assertGreaterThan(0, \App\Models\EsimCompatibleDevice::where('os_group', $os)->count());
        }
    }

    public function test_the_modal_opens_and_lists_apple_devices_by_default(): void
    {
        Livewire::actingAs(User::factory()->create())->test(EsimCompatibility::class)
            ->call('openModal')
            ->assertSet('open', true)
            ->assertSee('iPhone 15 Pro')
            ->assertSee('*#06#'); // the EID guidance banner
    }

    public function test_switching_to_android_shows_android_devices(): void
    {
        Livewire::actingAs(User::factory()->create())->test(EsimCompatibility::class)
            ->call('openModal')
            ->call('setOs', 'android')
            ->assertSet('os', 'android')
            ->assertSee('Galaxy S24 Ultra')
            ->assertDontSee('iPhone 15 Pro');
    }

    public function test_search_filters_across_the_active_tab(): void
    {
        Livewire::actingAs(User::factory()->create())->test(EsimCompatibility::class)
            ->call('openModal')
            ->set('search', 'iPhone 17')
            ->assertSee('iPhone 17 Pro Max')
            ->assertDontSee('iPhone 11');
    }
}
