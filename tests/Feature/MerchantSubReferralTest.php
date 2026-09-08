<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Merchants\MerchantReferralService;
use App\Support\MerchantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUILD-7 §4: merchant-to-merchant sub-referral — a one-time flat bonus, single
 * hop, never a recurring downline. Reuses the Referral table (type column).
 */
class MerchantSubReferralTest extends TestCase
{
    use RefreshDatabase;

    private function makeMerchant(?int $referredBy = null): User
    {
        $u = User::factory()->create(['referred_by' => $referredBy]);
        Merchant::create(['owner_user_id' => $u->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$u->id, 'status' => Merchant::ACTIVE]);

        return $u;
    }

    public function test_referrer_merchant_earns_a_one_time_bonus_when_their_invitee_becomes_a_merchant(): void
    {
        Setting::setValue(MerchantSettings::MERCHANT_REFERRAL_BONUS, 15, 'merchants');

        $referrer = $this->makeMerchant();
        $newMerchant = $this->makeMerchant(referredBy: $referrer->id);

        app(MerchantReferralService::class)->rewardReferrerIfEligible($newMerchant);

        $this->assertSame(15.0, app(CreditService::class)->balance($referrer->fresh()));
        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id, 'referred_id' => $newMerchant->id,
            'type' => Referral::TYPE_MERCHANT, 'rewarded' => true,
        ]);

        // Idempotent — a repeat call never double-pays.
        app(MerchantReferralService::class)->rewardReferrerIfEligible($newMerchant);
        $this->assertSame(15.0, app(CreditService::class)->balance($referrer->fresh()));
    }

    public function test_no_bonus_when_it_is_switched_off(): void
    {
        Setting::setValue(MerchantSettings::MERCHANT_REFERRAL_BONUS, 0, 'merchants');
        $referrer = $this->makeMerchant();
        $newMerchant = $this->makeMerchant(referredBy: $referrer->id);

        app(MerchantReferralService::class)->rewardReferrerIfEligible($newMerchant);
        $this->assertSame(0.0, app(CreditService::class)->balance($referrer->fresh()));
    }

    public function test_no_bonus_when_the_referrer_is_not_a_merchant(): void
    {
        Setting::setValue(MerchantSettings::MERCHANT_REFERRAL_BONUS, 20, 'merchants');
        $plainReferrer = User::factory()->create();
        $newMerchant = $this->makeMerchant(referredBy: $plainReferrer->id);

        app(MerchantReferralService::class)->rewardReferrerIfEligible($newMerchant);
        $this->assertSame(0.0, app(CreditService::class)->balance($plainReferrer->fresh()));
    }
}
