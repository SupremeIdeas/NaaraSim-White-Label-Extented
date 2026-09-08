<?php

namespace Tests\Feature;

use App\Livewire\ServicePicker;
use App\Models\User;
use App\Support\CountryPickerSources;
use App\Support\ServicePickerSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shared ServicePicker + the Numbers country source (Numbers V6 §0/§3):
 * both feed the same reusable modals used by Naara Verify and Naara Rent.
 */
class ServicePickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_with_icons_sort_before_icon_less_ones(): void
    {
        $options = ServicePickerSources::options();

        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('has_icon', $options[0]);

        // Every service WITH an icon appears before every service WITHOUT one.
        $firstMissing = null;
        foreach ($options as $i => $o) {
            if (! $o['has_icon']) {
                $firstMissing = $i;
                break;
            }
        }
        if ($firstMissing !== null) {
            foreach (array_slice($options, $firstMissing) as $o) {
                $this->assertFalse($o['has_icon'], 'an icon service must not appear after an icon-less one');
            }
        }

        // Within the icon group, order is alphabetical by name.
        $withIcon = array_values(array_filter($options, fn ($o) => $o['has_icon']));
        $names = array_column($withIcon, 'name');
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);
    }

    public function test_the_service_picker_emits_the_pick_with_its_opener_token(): void
    {
        Livewire::actingAs(User::factory()->create())->test(ServicePicker::class)
            ->call('openModal', 'verify', 'Choose a service')
            ->assertSet('open', true)
            ->call('pick', 'whatsapp', 'WhatsApp')
            ->assertDispatched('service-picked', slug: 'whatsapp', name: 'WhatsApp', for: 'verify')
            ->assertSet('open', false);
    }

    public function test_the_numbers_country_source_carries_dial_codes(): void
    {
        $options = CountryPickerSources::options('numbers');

        $this->assertNotEmpty($options);
        $byName = collect($options)->keyBy('code');

        // Nigeria's slug carries its dial code; every row has a name.
        $ng = collect($options)->firstWhere('code', 'nigeria');
        $this->assertNotNull($ng);
        $this->assertSame('+234', $ng['dial']);
        $this->assertArrayHasKey('name', $options[0]);
    }
}
