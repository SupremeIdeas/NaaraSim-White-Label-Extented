<?php

namespace Tests\Feature;

use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dedicated My Lines page: reachable at /numbers/lines with the Numbers
 * section chrome, showing owned eSIMs/numbers or a clean empty state.
 */
class MyLinesTest extends TestCase
{
    use RefreshDatabase;

    private function verified(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_my_lines_renders_inside_the_numbers_section(): void
    {
        $res = $this->actingAs($this->verified())->get('/numbers/lines')->assertOk();
        $res->assertSee('My Lines');
        // Numbers section chrome (the section nav) is present, not the global one.
        $res->assertSee('Dialer')->assertSee('/numbers/messages');
    }

    public function test_empty_state_prompts_a_first_purchase(): void
    {
        $res = $this->actingAs($this->verified())->get('/numbers/lines')->assertOk();
        $res->assertSee('Nothing here yet');
    }

    public function test_an_active_esim_shows_with_its_model_not_the_supplier(): void
    {
        $user = $this->verified();
        $plan = EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'd-'.uniqid(), 'name' => 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4, 'computed_retail_usd' => 10,
        ]);
        EsimOrder::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'esimgo',
            'status' => 'active', 'price_charged' => 10, 'wholesale_cost' => 4, 'currency' => 'USD',
        ]);

        $res = $this->actingAs($user)->get('/numbers/lines')->assertOk();
        $res->assertSee('USA 3GB')->assertSee('Naara Data')->assertDontSee('esimgo');
    }
}
