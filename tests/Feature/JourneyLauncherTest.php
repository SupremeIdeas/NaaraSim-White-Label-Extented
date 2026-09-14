<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Credits\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Floating "My Journey" launcher (owner request) — a Wizard-sized, same-
 * minimize-behavior companion widget for fast navigation to the activity-
 * progress/rewards page. Mirrors WizardSupportOverlapTest's exclusion checks.
 */
class JourneyLauncherTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_it_renders_on_a_normal_customer_page(): void
    {
        $this->actingAs($this->activeUser())
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Open My Journey — activity progress and rewards');
    }

    public function test_it_is_hidden_on_the_support_route(): void
    {
        $this->actingAs($this->activeUser())
            ->get('/support')
            ->assertOk()
            ->assertDontSee('Open My Journey — activity progress and rewards');
    }

    public function test_it_is_hidden_on_the_journey_route_itself(): void
    {
        $this->actingAs($this->activeUser())
            ->get(route('journey'))
            ->assertOk()
            ->assertDontSee('Open My Journey — activity progress and rewards');
    }

    public function test_it_links_straight_to_the_journey_route(): void
    {
        $this->actingAs($this->activeUser())
            ->get('/notifications')
            ->assertSee('href="'.route('journey').'"', false);
    }

    public function test_the_badge_shows_the_users_real_credits_balance(): void
    {
        $user = $this->activeUser();
        app(CreditService::class)->earn($user, 42, 'signup');

        Livewire::actingAs($user)->test(\App\Livewire\JourneyLauncher::class)
            ->assertSee('42');
    }
}
