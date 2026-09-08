<?php

namespace Tests\Feature;

use App\Livewire\SupportChat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NaaraCare warm hand-off (roadmap §10) — tapping "Talk to NaaraCare" in the
 * Wizard opens support with a friendly, editable starter so the agent begins
 * with context. Only the public Model is passed, never a supplier.
 */
class WizardHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wizard_handoff_prefills_a_warm_topic_starter(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams(['from' => 'wizard', 'topic' => 'naara_line'])
            ->test(SupportChat::class)
            ->assertSet('draft', 'I was setting up a permanent number (Naara Line) in the Helper and need a hand.');
    }

    public function test_an_unknown_topic_falls_back_to_a_generic_starter(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams(['from' => 'wizard'])
            ->test(SupportChat::class)
            ->assertSet('draft', 'I was using the NaaraSim Helper and need a hand with my order.');
    }

    public function test_opening_support_normally_does_not_prefill(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportChat::class)
            ->assertSet('draft', '');
    }
}
