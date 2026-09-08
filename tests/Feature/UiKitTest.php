<?php

namespace Tests\Feature;

use App\Livewire\Admin\ApiGuideModal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 20 — Reusable UI Kit (blueprint Section 31). Renders each component and
 * asserts the accessibility contract; the modal engine is exercised through the
 * refactored API-guide modal.
 */
class UiKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_star_rating_display_exposes_an_accessible_label(): void
    {
        $this->blade('<x-ui.star-rating :value="4" />')
            ->assertSee('role="img"', false)
            ->assertSee('aria-label="Rating: 4 out of 5"', false)
            ->assertSee('i-star', false);
    }

    public function test_interactive_star_rating_is_a_keyboard_radiogroup(): void
    {
        $html = $this->blade('<x-ui.star-rating :value="3" :readonly="false" />');

        $html->assertSee('role="radiogroup"', false)
            ->assertSee('role="radio"', false)
            ->assertSee('arrow-right', false)   // arrow-key navigation
            ->assertSee('arrow-left', false)
            ->assertSee(':aria-checked', false);
    }

    public function test_the_modal_engine_ships_focus_trap_esc_and_dialog_aria(): void
    {
        $html = $this->blade('<x-ui.modal name="demo" title="Hi">body</x-ui.modal>');

        $html->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('aria-labelledby=', false)
            ->assertSee('trapTab', false)                       // focus trap
            ->assertSee('keydown.escape.window', false)         // ESC closes
            ->assertSee('open-modal.window', false);            // event-driven open
    }

    public function test_countdown_is_anchored_to_server_time(): void
    {
        $html = $this->blade('<x-ui.countdown until="2999-01-01T00:00:00+00:00" />');

        // It sends the server "now" and corrects the client clock by the skew —
        // so a wrong device clock can't game it.
        $html->assertSee('skew', false)
            ->assertSee('Date.now()', false)
            ->assertSee('role="timer"', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_search_is_debounced_and_labelled(): void
    {
        $this->blade('<x-ui.search wire="q" />')
            ->assertSee('wire:model.live.debounce.300ms="q"', false)
            ->assertSee('role="search"', false)
            ->assertSee('aria-label="Search"', false);
    }

    public function test_the_api_guide_dialog_uses_the_shared_modal_engine(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ApiGuideModal::class)
            ->assertSee('aria-modal="true"', false)         // engine markup present
            ->assertSee('trapTab', false)
            ->dispatch('open-api-guide', provider: 'esimgo', field: 'api_key')
            ->assertSet('open', true)
            ->assertSee('ESIMGO_API_KEY');
    }
}
