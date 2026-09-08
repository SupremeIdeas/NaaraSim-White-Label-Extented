<?php

namespace Tests\Feature;

use App\Livewire\Wizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Wizard picker refresh (wizard+preloader blueprint §1). Each entry into the
 * country/service step gets a fresh Alpine container (via a bumped wire:key) so
 * the search field never retains a stale value that hides every item after a
 * forward→back→forward. The refresh buttons force the same clean re-init.
 */
class WizardPickerRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_re_entering_a_step_bumps_its_visit_key(): void
    {
        $c = Livewire::actingAs(User::factory()->create())->test(Wizard::class);

        $c->set('step', 'country')->assertSet('stepVisits.country', 1);
        $c->set('step', 'service')->assertSet('stepVisits.service', 1);
        // Going back to country is a NEW visit → a new key → Alpine re-inits.
        $c->set('step', 'country')->assertSet('stepVisits.country', 2);
        // A same-step re-render does NOT bump (stable key, keeps the search).
        $c->call('$refresh')->assertSet('stepVisits.country', 2);
    }

    public function test_refresh_buttons_force_a_clean_reinit(): void
    {
        $c = Livewire::actingAs(User::factory()->create())->test(Wizard::class)
            ->set('step', 'country');

        $before = $c->get('stepVisits')['country'];
        $c->call('refreshCountries')->assertSet('stepVisits.country', $before + 1);
        $c->call('refreshServices');
        $this->assertGreaterThanOrEqual(1, $c->get('stepVisits')['service']);
    }
}
