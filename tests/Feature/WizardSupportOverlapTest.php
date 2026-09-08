<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUILD-3 §2: the floating Wizard widget must not render on the NaaraCare /
 * support-chat route, where it would overlap the Nia conversation. It still
 * renders on other customer pages.
 */
class WizardSupportOverlapTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_the_wizard_is_hidden_on_the_support_route(): void
    {
        $this->actingAs($this->activeUser())
            ->get('/support')
            ->assertOk()
            ->assertDontSee('Open the NaaraSim helper'); // the wizard launcher aria-label
    }

    public function test_the_wizard_still_renders_on_other_customer_pages(): void
    {
        $this->actingAs($this->activeUser())
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Open the NaaraSim helper');
    }
}
