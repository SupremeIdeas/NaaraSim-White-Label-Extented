<?php

namespace Tests\Feature;

use App\Livewire\PayoutDashboard;
use App\Models\Setting;
use App\Models\User;
use App\Services\Referrals\ReferralEarningsService;
use App\Support\PayoutSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA-BUILD-22 §4 — the ONE shared payout dashboard, parameterised by earner
 * type. Visible with zero verification; shows balance + the free-payout threshold.
 */
class PayoutDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        Setting::setValue(PayoutSettings::FREE_COUNT, 3, 'payouts');
    }

    public function test_referral_earner_sees_their_balance_and_free_payout_state(): void
    {
        $user = User::factory()->create();
        app(ReferralEarningsService::class)->accrue($user, null, 'esim', 4.50, 'seed:1');

        Livewire::actingAs($user)
            ->test(PayoutDashboard::class, ['earnerType' => 'referral'])
            ->assertSee('4.50')
            ->assertSee('3 of 3 free payouts left'); // no payouts taken yet
    }

    public function test_dashboard_is_visible_without_any_verification(): void
    {
        // No KYC, no payout account — the balance surface still renders (§3.4).
        $user = User::factory()->create();
        Livewire::actingAs($user)
            ->test(PayoutDashboard::class, ['earnerType' => 'referral'])
            ->assertOk()
            ->assertSee('Add a payout account');
    }

    public function test_each_earner_type_renders_on_the_same_component(): void
    {
        $user = User::factory()->create();
        foreach (['partner', 'merchant', 'referral'] as $type) {
            Livewire::actingAs($user)
                ->test(PayoutDashboard::class, ['earnerType' => $type])
                ->assertOk()
                ->assertSee('earnings balance');
        }
    }

    public function test_an_unknown_earner_type_404s(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)
            ->test(PayoutDashboard::class, ['earnerType' => 'hacker'])
            ->assertStatus(404);
    }
}
