<?php

namespace Tests\Feature;

use App\Models\GiftCardOrder;
use App\Models\GiftCardProduct;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGiftCardProvider;
use Tests\TestCase;

/**
 * giftcards:reconcile-processing — recovery path for an order stuck
 * 'processing' because its provider's webhook never arrived. Only polls
 * providers that implement GiftCardStatusCheckable, never re-charges, and
 * never touches an order that isn't both processing AND past the threshold.
 */
class GiftCardReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
    }

    private function stuckOrder(User $user, array $attrs = []): GiftCardOrder
    {
        $createdAt = $attrs['created_at'] ?? now()->subMinutes(30);
        unset($attrs['created_at']);

        $order = GiftCardOrder::create(array_merge([
            'user_id' => $user->id,
            'gift_card_product_id' => GiftCardProduct::create([
                'provider' => 'reloadly', 'provider_product_id' => 'p1', 'brand_key' => 'amazon',
                'brand_name' => 'Amazon', 'currency' => 'USD', 'denomination_type' => 'FIXED',
                'fixed_denominations' => [25], 'is_primary' => true, 'admin_enabled' => true,
            ])->id,
            'provider' => 'reloadly', 'provider_product_id' => 'p1', 'brand_name' => 'Amazon',
            'face_value' => 25, 'currency' => 'USD', 'price_charged' => 27,
            'status' => GiftCardOrder::STATUS_PROCESSING, 'transaction_ref' => 'ref-'.uniqid(),
            'provider_tx_id' => 'tx-123', 'receipt' => [], 'fields' => [],
        ], $attrs));

        // created_at isn't mass-assignable — force it after the fact so the
        // reconcile command's "past the threshold" window is testable.
        $order->forceFill(['created_at' => $createdAt])->save();

        return $order->fresh();
    }

    private function user(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        app(WalletService::class)->credit($user, 100, 'USD', ['reference' => 'seed:'.$user->id]);

        return $user;
    }

    public function test_a_stuck_order_that_is_now_delivered_gets_finalized(): void
    {
        $fake = new FakeGiftCardProvider;
        $fake->statusResponse = ['status' => 'delivered', 'receipt' => ['code' => 'GC-999']];
        $this->app->instance('giftcard.reloadly', $fake);

        $order = $this->stuckOrder($this->user());

        $this->artisan('giftcards:reconcile-processing')->assertSuccessful();

        $order->refresh();
        $this->assertSame(GiftCardOrder::STATUS_DELIVERED, $order->status);
        $this->assertSame('GC-999', $order->receipt['code']);
    }

    public function test_a_stuck_order_that_actually_failed_is_refunded(): void
    {
        $fake = new FakeGiftCardProvider;
        $fake->statusResponse = ['status' => 'failed', 'receipt' => []];
        $this->app->instance('giftcard.reloadly', $fake);

        $user = $this->user();
        $order = $this->stuckOrder($user);
        $before = (float) $user->wallet->fresh()->usd_balance;

        $this->artisan('giftcards:reconcile-processing')->assertSuccessful();

        $order->refresh();
        $this->assertSame(GiftCardOrder::STATUS_FAILED, $order->status);
        $this->assertEqualsWithDelta($before + 27, (float) $user->wallet->fresh()->usd_balance, 0.0001);
    }

    public function test_a_still_processing_order_is_left_untouched(): void
    {
        $fake = new FakeGiftCardProvider;
        $fake->statusResponse = ['status' => 'processing', 'receipt' => []];
        $this->app->instance('giftcard.reloadly', $fake);

        $order = $this->stuckOrder($this->user());

        $this->artisan('giftcards:reconcile-processing')->assertSuccessful();

        $this->assertSame(GiftCardOrder::STATUS_PROCESSING, $order->fresh()->status);
    }

    public function test_an_order_still_within_the_threshold_is_not_checked(): void
    {
        $fake = new FakeGiftCardProvider;
        $fake->statusResponse = ['status' => 'delivered', 'receipt' => ['code' => 'SHOULD-NOT-APPLY']];
        $this->app->instance('giftcard.reloadly', $fake);

        $order = $this->stuckOrder($this->user(), ['created_at' => now()->subMinutes(2)]);

        $this->artisan('giftcards:reconcile-processing')->assertSuccessful();

        $this->assertSame(GiftCardOrder::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertEmpty($order->fresh()->receipt);
    }

    public function test_a_provider_without_status_checking_is_skipped_not_errored(): void
    {
        // Zendit implements GiftCardProviderInterface only — no GiftCardStatusCheckable.
        $order = $this->stuckOrder($this->user(), ['provider' => 'zendit']);

        $this->artisan('giftcards:reconcile-processing')->assertSuccessful();

        $this->assertSame(GiftCardOrder::STATUS_PROCESSING, $order->fresh()->status);
    }

    public function test_a_terminal_order_is_never_touched_even_if_past_threshold(): void
    {
        $fake = new FakeGiftCardProvider;
        $fake->statusResponse = ['status' => 'failed', 'receipt' => []];
        $this->app->instance('giftcard.reloadly', $fake);

        $order = $this->stuckOrder($this->user(), ['status' => GiftCardOrder::STATUS_DELIVERED]);

        $this->artisan('giftcards:reconcile-processing')->assertSuccessful();

        $this->assertSame(GiftCardOrder::STATUS_DELIVERED, $order->fresh()->status);
    }
}
