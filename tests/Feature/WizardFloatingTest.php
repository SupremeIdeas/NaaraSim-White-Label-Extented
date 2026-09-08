<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * BUILD-3 §4: the floating Wizard trigger is section-aware (CSS/Alpine only).
 * eSIM / Number → swells into "Confused? Use The Wizard" with the electric edge;
 * Gift → fades out; Home / elsewhere → resting "Ask NaaraSim".
 */
class WizardFloatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        Queue::fake();
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_esim_section_swells_into_the_confused_wizard_button(): void
    {
        $res = $this->actingAs($this->user())->get('/catalogue')->assertOk();
        $res->assertSee('Confused? Use The Wizard');
        $res->assertSee('nx-wiz-swell', false);
    }

    public function test_number_section_also_swells(): void
    {
        $this->actingAs($this->user())->get('/numbers')->assertOk()
            ->assertSee('Confused? Use The Wizard');
    }

    public function test_gift_section_fades_the_wizard_out(): void
    {
        $res = $this->actingAs($this->user())->get('/gift-cards')->assertOk();
        // Faded + non-interactive, and NOT swollen into the attention button.
        $res->assertSee('pointer-events-none opacity-0', false);
        $res->assertDontSee('Confused? Use The Wizard');
    }

    public function test_home_shows_the_resting_launcher(): void
    {
        $res = $this->actingAs($this->user())->get('/dashboard')->assertOk();
        $res->assertSee('Ask NaaraSim');
        $res->assertDontSee('Confused? Use The Wizard');
        $res->assertDontSee('nx-wiz-swell', false);
    }
}
