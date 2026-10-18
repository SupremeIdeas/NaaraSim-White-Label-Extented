<?php

namespace Tests\Feature;

use App\Livewire\Admin\EsimControlCenter;
use App\Models\EsimCountryImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Admin eSIM image manager: countries are shown and picked by NAME, never by a bare ISO code. */
class EsimImageAdminNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_image_grid_shows_country_names_and_the_picker_lists_names(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        EsimCountryImage::create(['country_code' => 'KZ', 'icon_path' => '/images/esim/countries/kz.webp', 'detail_image_path' => '/images/esim/countries/kz.webp']);

        $c = Livewire::actingAs($admin)->test(EsimControlCenter::class)->call('setSection', 'images')->call('setImageKind', 'country');
        $c->assertSee('Kazakhstan')->assertSee('Choose a country');

        $options = $c->instance()->countryOptions();
        $this->assertSame('Kazakhstan', $options['KZ']);
        $this->assertSame('Nigeria', $options['NG']);
        $this->assertGreaterThan(200, count($options));
        $this->assertArrayNotHasKey('XX', $options);
    }
}
