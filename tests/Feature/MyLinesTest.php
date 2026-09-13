<?php

namespace Tests\Feature;

use App\Livewire\MyLines;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use App\Models\VirtualNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    // ---- Prompt 10: Naara Line billing surfaced + auto-renew toggle --------

    private function line(User $user, array $extra = []): VirtualNumber
    {
        return VirtualNumber::create(array_merge([
            'user_id' => $user->id, 'provider' => 'twilio', 'phone_number' => '+1555000'.rand(1000, 9999),
            'sid' => 'SID-'.uniqid(), 'monthly_cost' => 1.00, 'monthly_retail' => 2.50,
            'status' => 'active', 'next_billing_date' => today()->addDays(10)->toDateString(),
            'provisioned_at' => now(),
        ], $extra));
    }

    /**
     * PermanentNumberRouter never writes an SmsOrder for a Naara Line — this
     * proves the ConnectivityHub merge actually surfaces it on My Lines,
     * confirming the real bug the fix addresses (a purchased line was
     * previously invisible here).
     */
    public function test_a_naara_line_number_is_visible_on_my_lines(): void
    {
        $user = $this->verified();
        $vn = $this->line($user);

        $res = $this->actingAs($user)->get('/numbers/lines')->assertOk();
        $res->assertSee($vn->phone_number)->assertSee('Naara Line')->assertDontSee('twilio');
    }

    public function test_a_renewing_line_shows_its_price_and_next_billing_date(): void
    {
        $user = $this->verified();
        $vn = $this->line($user, ['next_billing_date' => today()->addDays(10)->toDateString()]);

        $res = $this->actingAs($user)->get('/numbers/lines')->assertOk();
        $res->assertSee('$2.50/mo')->assertSee($vn->next_billing_date->format('M j'));
        $res->assertSee('Turn off auto-renew');
    }

    public function test_an_opted_out_line_shows_its_end_date_and_the_re_enable_action(): void
    {
        $user = $this->verified();
        $vn = $this->line($user, ['auto_renew' => false]);

        $res = $this->actingAs($user)->get('/numbers/lines')->assertOk();
        $res->assertSee('ends '.$vn->next_billing_date->format('M j'));
        $res->assertSee('Turn auto-renew back on');
    }

    public function test_toggling_auto_renew_flips_the_flag_and_is_owner_scoped(): void
    {
        $owner = $this->verified();
        $stranger = $this->verified();
        $vn = $this->line($owner);

        // A stranger's attempt is a silent no-op — never another user's line.
        Livewire::actingAs($stranger)->test(MyLines::class)->call('toggleAutoRenew', $vn->id);
        $this->assertTrue($vn->refresh()->auto_renew);

        Livewire::actingAs($owner)->test(MyLines::class)->call('toggleAutoRenew', $vn->id);
        $this->assertFalse($vn->refresh()->auto_renew);

        // Idempotent both ways — toggling again turns it back on.
        Livewire::actingAs($owner)->test(MyLines::class)->call('toggleAutoRenew', $vn->id);
        $this->assertTrue($vn->refresh()->auto_renew);
    }

    public function test_an_expired_line_appears_in_the_archive_not_the_active_list(): void
    {
        $user = $this->verified();
        $vn = $this->line($user, ['status' => 'expired']);

        $res = $this->actingAs($user)->get('/numbers/lines')->assertOk();
        // Archived, not active: no billing/auto-renew controls for a dead line.
        $res->assertDontSee('Turn off auto-renew')->assertDontSee('Turn auto-renew back on');
        $res->assertSee('Archive')->assertSee($vn->phone_number);
    }

    // ---- Prompt 11: port-out / right-to-leave -----------------------------

    public function test_a_us_canada_line_offers_a_port_out_action(): void
    {
        $user = $this->verified();
        $this->line($user); // +1 by default

        $this->actingAs($user)->get('/numbers/lines')->assertOk()
            ->assertSee('Take this number to another carrier');
    }

    public function test_a_non_us_canada_line_never_offers_a_port_out_action(): void
    {
        $user = $this->verified();
        $this->line($user, ['phone_number' => '+2348012345678']); // Nigerian number

        $this->actingAs($user)->get('/numbers/lines')->assertOk()
            ->assertDontSee('Take this number to another carrier');
    }

    public function test_requesting_a_port_out_records_it_and_is_owner_scoped(): void
    {
        $owner = $this->verified();
        $stranger = $this->verified();
        $vn = $this->line($owner);

        // A stranger cannot flag another user's line.
        Livewire::actingAs($stranger)->test(MyLines::class)->call('requestPortOut', $vn->id);
        $this->assertNull($vn->refresh()->port_out_requested_at);

        Livewire::actingAs($owner)->test(MyLines::class)->call('requestPortOut', $vn->id);
        $this->assertNotNull($vn->refresh()->port_out_requested_at);
    }

    public function test_a_non_us_canada_line_cannot_be_ported_out(): void
    {
        $user = $this->verified();
        $vn = $this->line($user, ['phone_number' => '+2348012345678']);

        Livewire::actingAs($user)->test(MyLines::class)->call('requestPortOut', $vn->id);
        $this->assertNull($vn->refresh()->port_out_requested_at);
    }

    public function test_a_requested_line_shows_the_pending_state_not_the_action(): void
    {
        $user = $this->verified();
        $this->line($user, ['port_out_requested_at' => now()]);

        $res = $this->actingAs($user)->get('/numbers/lines')->assertOk();
        $res->assertSee('Port-out requested')->assertDontSee('Take this number to another carrier');
    }
}
