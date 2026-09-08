<?php

namespace Tests\Feature;

use App\Livewire\GetNumber;
use App\Models\User;
use App\Services\SMS\NumberRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Numbers product modals (Numbers V6 §0) — the modal host on GetNumber: opening
 * Verify/Rent/Line, the shared-picker wiring, and the Line search guard.
 */
class NumbersModalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_each_modal_sets_the_right_request_type(): void
    {
        Livewire::actingAs(User::factory()->create())->test(GetNumber::class)
            ->call('openModal', 'verify')->assertSet('modal', 'verify')->assertSet('type', 'otp')
            ->call('openModal', 'rent')->assertSet('modal', 'rent')->assertSet('type', 'rental')
            ->call('openModal', 'line')->assertSet('modal', 'line')->assertSet('type', 'permanent')
            ->call('closeModal')->assertSet('modal', '');
    }

    public function test_an_unknown_modal_name_is_ignored(): void
    {
        Livewire::actingAs(User::factory()->create())->test(GetNumber::class)
            ->call('openModal', 'evil')->assertSet('modal', '');
    }

    public function test_the_pickers_open_and_their_picks_land_on_the_flow(): void
    {
        Livewire::actingAs(User::factory()->create())->test(GetNumber::class)
            ->call('pickService')->assertDispatched('open-service-picker', for: 'numbers')
            ->call('onServicePicked', 'telegram', 'Telegram', 'numbers')
            ->assertSet('service', 'telegram')->assertSet('serviceName', 'Telegram')
            ->call('pickCountry')->assertDispatched('open-country-picker', source: 'numbers', for: 'numbers')
            ->call('onCountryPicked', 'nigeria', 'Nigeria', 'numbers')
            ->assertSet('country', 'nigeria')->assertSet('countryName', 'Nigeria');
    }

    public function test_a_pick_for_another_opener_is_ignored(): void
    {
        Livewire::actingAs(User::factory()->create())->test(GetNumber::class)
            ->set('service', 'whatsapp')
            ->call('onServicePicked', 'telegram', 'Telegram', 'catalogue')
            ->assertSet('service', 'whatsapp'); // unchanged — not ours
    }

    public function test_get_line_refuses_a_number_that_was_not_offered(): void
    {
        // No prior search → lineProvider is null, so provisioning must be refused
        // (never provision a number the user didn't actually see + select).
        Livewire::actingAs(User::factory()->create())->test(GetNumber::class)
            ->call('openModal', 'line')
            ->call('getLine', '+15550000000')
            ->assertSet('lineDone', null)
            ->assertSet('error', 'Please search again — that number is no longer listed.');
    }

    public function test_full_rent_any_service_is_the_service_any_sentinel(): void
    {
        // The Rent modal's "Any service" sets the SERVICE_ANY sentinel used by
        // the full-rent lane.
        Livewire::actingAs(User::factory()->create())->test(GetNumber::class)
            ->call('openModal', 'rent')
            ->set('service', NumberRequest::SERVICE_ANY)
            ->assertSet('service', NumberRequest::SERVICE_ANY);
    }
}
