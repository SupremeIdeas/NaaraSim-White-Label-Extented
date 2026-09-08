<?php

namespace Tests\Feature;

use App\Jobs\ProcessReferralRewardJob;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Models\User;
use App\Services\Referrals\ReferralEarningsService;
use App\Services\Referrals\ReferralRewardService;
use App\Support\ReferralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NAARA-BUILD-22 §1 — referral-share-on-first-transaction. The referrer earns a
 * share of Naara's OWN margin on the referred user's first successful order,
 * booked to the real (withdrawable) ReferralEarning ledger, exactly once.
 */
class ReferralRewardTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReferralRewardService
    {
        return app(ReferralRewardService::class);
    }

    public function test_first_transaction_rewards_the_referrer_a_share_of_margin(): void
    {
        ReferralSettings::setMarginSharePct(10);
        $referrer = User::factory()->create();
        $buyer = User::factory()->create(['referred_by' => $referrer->id]);

        // Naara's margin on the order is $8.00 → 10% = $0.80 to the referrer.
        $earning = $this->service()->rewardFirstTransaction($buyer, 'esim', 8.00);

        $this->assertNotNull($earning);
        $this->assertEqualsWithDelta(0.80, (float) $earning->amount, 0.0001);
        $this->assertEqualsWithDelta(0.80, app(ReferralEarningsService::class)->balance($referrer), 0.0001);

        $referral = Referral::where('referred_id', $buyer->id)->first();
        $this->assertTrue((bool) $referral->rewarded);
        $this->assertNotNull($referral->rewarded_at);
    }

    public function test_reward_is_once_ever_even_across_multiple_orders(): void
    {
        ReferralSettings::setMarginSharePct(10);
        $referrer = User::factory()->create();
        $buyer = User::factory()->create(['referred_by' => $referrer->id]);

        $this->service()->rewardFirstTransaction($buyer, 'esim', 8.00);
        // A second (and third) order must never reward again.
        $this->assertNull($this->service()->rewardFirstTransaction($buyer, 'number', 20.00));
        $this->assertNull($this->service()->rewardFirstTransaction($buyer, 'esim', 5.00));

        $this->assertSame(1, ReferralEarning::where('user_id', $referrer->id)->count());
        $this->assertEqualsWithDelta(0.80, app(ReferralEarningsService::class)->balance($referrer), 0.0001);
    }

    public function test_non_referred_buyer_earns_nobody_anything(): void
    {
        $buyer = User::factory()->create(['referred_by' => null]);
        $this->assertNull($this->service()->rewardFirstTransaction($buyer, 'esim', 8.00));
        $this->assertSame(0, ReferralEarning::count());
    }

    public function test_zero_or_negative_margin_does_not_burn_the_one_shot(): void
    {
        ReferralSettings::setMarginSharePct(10);
        $referrer = User::factory()->create();
        $buyer = User::factory()->create(['referred_by' => $referrer->id]);

        // A break-even first order rewards nothing and leaves the referral open…
        $this->assertNull($this->service()->rewardFirstTransaction($buyer, 'esim', 0.0));
        $referral = Referral::where('referred_id', $buyer->id)->first();
        $this->assertFalse((bool) $referral->rewarded);

        // …so the next profitable order still pays.
        $this->service()->rewardFirstTransaction($buyer, 'number', 12.50);
        $this->assertEqualsWithDelta(1.25, app(ReferralEarningsService::class)->balance($referrer), 0.0001);
    }

    public function test_rate_is_locked_at_referral_creation_not_retroactive(): void
    {
        ReferralSettings::setMarginSharePct(10);
        $referrer = User::factory()->create();
        $buyer = User::factory()->create(['referred_by' => $referrer->id]);

        // First break-even order creates the referral row at 10%.
        $this->service()->rewardFirstTransaction($buyer, 'esim', 0.0);
        // Admin later raises the global rate — must not affect this referral.
        ReferralSettings::setMarginSharePct(50);

        $this->service()->rewardFirstTransaction($buyer, 'esim', 10.00);
        // Still 10% (locked), not 50%.
        $this->assertEqualsWithDelta(1.00, app(ReferralEarningsService::class)->balance($referrer), 0.0001);
    }

    public function test_job_is_idempotent_on_retry(): void
    {
        ReferralSettings::setMarginSharePct(10);
        $referrer = User::factory()->create();
        $buyer = User::factory()->create(['referred_by' => $referrer->id]);

        (new ProcessReferralRewardJob($buyer->id, 'esim', 8.00))->handle(app(ReferralRewardService::class));
        (new ProcessReferralRewardJob($buyer->id, 'esim', 8.00))->handle(app(ReferralRewardService::class));

        $this->assertSame(1, ReferralEarning::where('user_id', $referrer->id)->count());
    }
}
