<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\SocialFollowClaim;
use App\Models\User;
use App\Notifications\BrandBillingReminderNotification;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** BUILD-9 §5 + §6: subscription billing lifecycle and the priority engine. */
class BrandBillingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(float $price = 19, int $guaranteed = 50): BrandSubscriptionPlan
    {
        return BrandSubscriptionPlan::create([
            'name' => 'Starter', 'price_usd_per_month' => $price, 'handles_included' => 1,
            'guaranteed_followers_per_handle_per_month' => $guaranteed, 'video_previews_allowed' => 0,
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    public function test_subscribe_charges_first_month_and_creates_a_pending_listing(): void
    {
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 50, 'USD');
        $plan = $this->plan(19);

        $sub = app(BrandSubscriptionService::class)->subscribe($owner, $plan);

        $this->assertSame(31.0, (float) $owner->fresh()->wallet->usd_balance); // 50 - 19
        $this->assertSame(BrandSubscription::ACTIVE, $sub->status);
        $brand = $sub->brandPartner;
        $this->assertSame(BrandPartner::STATUS_PENDING, $brand->listing_status);
        $this->assertSame($owner->id, $brand->owner_user_id);
    }

    public function test_subscribe_with_insufficient_balance_creates_nothing(): void
    {
        $owner = User::factory()->create(); // no funds
        $plan = $this->plan(19);

        $this->expectException(InsufficientBalanceException::class);
        try {
            app(BrandSubscriptionService::class)->subscribe($owner, $plan);
        } finally {
            $this->assertSame(0, BrandPartner::count());
            $this->assertSame(0, BrandSubscription::count());
        }
    }

    public function test_monthly_sweep_charges_due_pauses_short_and_reminds(): void
    {
        Notification::fake();
        $plan = $this->plan(19);

        // A funded due subscription is charged and advanced.
        $paid = User::factory()->create();
        app(WalletService::class)->credit($paid, 40, 'USD');
        $subPaid = app(BrandSubscriptionService::class)->subscribe($paid, $plan);
        $subPaid->forceFill(['next_billing_at' => now()->subDay()])->save();

        // A broke due subscription is paused + reminded (fund the first month, then drain).
        $broke = User::factory()->create();
        app(WalletService::class)->credit($broke, 19, 'USD');
        $subBroke = app(BrandSubscriptionService::class)->subscribe($broke, $plan); // spends the 19
        $subBroke->forceFill(['next_billing_at' => now()->subDay()])->save();

        $this->artisan('brand-subscriptions:bill')->assertSuccessful();

        $subPaid->refresh();
        $this->assertSame(BrandSubscription::ACTIVE, $subPaid->status);
        $this->assertTrue($subPaid->next_billing_at->isFuture());

        $subBroke->refresh();
        $this->assertSame(BrandSubscription::PAST_DUE, $subBroke->status);
        $this->assertSame(BrandPartner::STATUS_PAUSED, $subBroke->brandPartner->listing_status);
        Notification::assertSentTo($broke, BrandBillingReminderNotification::class);
    }

    public function test_running_the_sweep_twice_in_the_same_month_charges_each_overdue_period_once(): void
    {
        // A subscription 2 months behind (e.g. the scheduler missed a run).
        // Money-safety: catching up must charge BOTH overdue periods, never
        // silently advance next_billing_at on the second run without a
        // matching debit just because it's still the same calendar month.
        $plan = $this->plan(19);
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 40, 'USD');
        $sub = app(BrandSubscriptionService::class)->subscribe($owner, $plan); // spends 19, leaves 21
        $sub->forceFill(['next_billing_at' => now()->subMonths(2)])->save();

        $this->artisan('brand-subscriptions:bill')->assertSuccessful();
        $this->assertSame(2.0, (float) $owner->fresh()->wallet->usd_balance); // 21 - 19
        $sub->refresh();
        $this->assertTrue($sub->next_billing_at->lte(now()), 'still overdue after only one month advanced');

        $this->artisan('brand-subscriptions:bill')->assertSuccessful();

        // Second overdue period can't be charged (balance is now 2) — must
        // pause, never silently advance the date as if it were charged.
        $sub->refresh();
        $this->assertSame(BrandSubscription::PAST_DUE, $sub->status);
        $this->assertTrue($sub->next_billing_at->lte(now()), 'unpaid period must not advance the billing date');
    }

    public function test_priority_score_boosts_on_shortfall_and_logs(): void
    {
        $plan = $this->plan(19, guaranteed: 50);
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 40, 'USD');
        $sub = app(BrandSubscriptionService::class)->subscribe($owner, $plan);
        $brand = $sub->brandPartner;
        $handle = BrandPartnerHandle::create(['brand_partner_id' => $brand->id, 'platform' => 'x', 'handle_label' => 'H', 'handle_url' => 'https://x.com/h', 'credit_reward' => 1, 'verification' => 'self', 'is_active' => true, 'sort_order' => 1]);

        // Only 10 of 50 guaranteed follows delivered this month → shortfall 40.
        for ($i = 0; $i < 10; $i++) {
            SocialFollowClaim::create(['user_id' => User::factory()->create()->id, 'brand_partner_handle_id' => $handle->id, 'claimed_at' => now()->subDays(2)]);
        }
        $subBroke = $sub->forceFill(['next_billing_at' => now()->subDay(), 'last_charged_at' => now()->subMonth()]);
        $subBroke->save();

        $this->artisan('brand-subscriptions:bill')->assertSuccessful();

        $this->assertSame(40, (int) $brand->fresh()->priority_score); // 50 - 10
        $this->assertDatabaseHas('brand_priority_log', ['brand_partner_id' => $brand->id, 'actual' => 10, 'guaranteed' => 50, 'adjustment' => 40]);
    }

    public function test_cancel_disables_the_listing_distinct_from_a_pause(): void
    {
        $owner = User::factory()->create();
        app(WalletService::class)->credit($owner, 40, 'USD');
        $plan = $this->plan(19);
        $sub = app(BrandSubscriptionService::class)->subscribe($owner, $plan);

        app(BrandSubscriptionService::class)->cancel($sub->brandPartner);

        $this->assertSame(BrandSubscription::CANCELLED, $sub->fresh()->status);
        $this->assertSame(BrandPartner::STATUS_DISABLED, $sub->brandPartner->fresh()->listing_status);
    }
}
