<?php

namespace Tests\Feature;

use App\Livewire\Admin\GiftCards as AdminGiftCards;
use App\Livewire\GiftCards as Storefront;
use App\Models\GiftCardOrder;
use App\Models\GiftCardProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\GiftCards\GiftCardException;
use App\Services\GiftCards\GiftCardOrderService;
use App\Services\GiftCards\GiftCardProviderException;
use App\Services\GiftCards\GiftCardProviderInterface;
use App\Services\Wallet\WalletService;
use App\Support\GiftCardPricing;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Support\FakeGiftCardProvider;
use Tests\TestCase;

/**
 * Naara Gift — Phase 3: the purchase money path. Retail through PricingEngine,
 * ATOMIC + idempotent wallet debit, order row created BEFORE the provider call
 * (no orphan charge), refund-on-provider-failure, the fraud gate (velocity +
 * cooling-off), the manual-review hold (approve → deliver, reject → refund), and
 * the async delivery webhook. Provider identity + cost never leak to the buyer.
 */
class NaaraGiftOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        config(['services.reloadly.client_id' => 'id', 'services.reloadly.client_secret' => 'secret']);
        Setting::setValue('features.naara_gift.enabled', true);
        Cache::forget('features.enabled.naara_gift');
    }

    private function product(array $attrs = []): GiftCardProduct
    {
        return GiftCardProduct::create(array_merge([
            'provider' => 'reloadly', 'provider_product_id' => 'p'.uniqid(),
            'brand_key' => 'amazon', 'brand_name' => 'Amazon', 'country' => 'US', 'currency' => 'USD',
            'denomination_type' => 'FIXED', 'fixed_denominations' => [25, 50, 100],
            'cost_meta' => ['discountPercentage' => 8, 'senderFee' => 1],
            'required_fields' => [['key' => 'email', 'label' => 'Recipient email', 'required' => true]],
            'provider_enabled' => true, 'admin_enabled' => true, 'is_primary' => true,
        ], $attrs));
    }

    /** Older, funded account so the cooling-off gate doesn't fire. */
    private function funded(float $usd = 500): User
    {
        $user = User::factory()->create(['is_active' => true, 'created_at' => now()->subMonths(2)]);
        app(WalletService::class)->credit($user, $usd, 'USD', ['reference' => 'seed:'.$user->id]);

        return $user;
    }

    /** Bind a fake provider that delivers (or fails/holds) deterministically. */
    private function fakeProvider(string $status = 'delivered', array $receipt = ['code' => 'GIFT-1234', 'epin' => 'PIN-9']): void
    {
        $this->app->bind('giftcard.reloadly', fn () => new class($status, $receipt) implements GiftCardProviderInterface
        {
            public function __construct(private string $status, private array $receipt) {}

            public function key(): string
            {
                return 'reloadly';
            }

            public function available(): bool
            {
                return true;
            }

            public function getCatalogue(): array
            {
                return [];
            }

            public function getBalance(): float
            {
                return 0.0;
            }

            public function preflight(): array
            {
                return ['ok' => true, 'balance' => 100.0, 'currency' => 'USD', 'products' => 5, 'error' => null];
            }

            public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
            {
                if ($this->status === 'throw') {
                    throw new GiftCardProviderException('provider down');
                }

                return ['provider_tx_id' => 'tx-'.$reference, 'status' => $this->status, 'receipt' => $this->receipt];
            }
        });
    }

    public function test_happy_path_debits_retail_and_delivers_the_card(): void
    {
        $this->fakeProvider();
        $p = $this->product();
        $user = $this->funded();
        $before = (float) $user->wallet->fresh()->usd_balance;
        $retail = round(app(GiftCardPricing::class)->retail($p, 50.0, log: false), 4);

        $order = app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);

        $this->assertSame(GiftCardOrder::STATUS_DELIVERED, $order->status);
        $this->assertSame('code', $order->redemptionMode());
        // Exactly the retail was debited (cost never charged to the user).
        $this->assertEqualsWithDelta($before - $retail, (float) $user->wallet->fresh()->usd_balance, 0.0001);
    }

    public function test_provider_failure_refunds_the_wallet_in_full(): void
    {
        $this->fakeProvider('throw');
        $p = $this->product();
        $user = $this->funded();
        $before = (float) $user->wallet->fresh()->usd_balance;

        try {
            app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);
            $this->fail('expected a GiftCardException');
        } catch (GiftCardException) {
            // expected
        }

        $order = GiftCardOrder::latest()->first();
        $this->assertSame(GiftCardOrder::STATUS_FAILED, $order->status);
        // Charged then refunded → net zero.
        $this->assertEqualsWithDelta($before, (float) $user->wallet->fresh()->usd_balance, 0.0001);
    }

    public function test_insufficient_balance_never_creates_an_order(): void
    {
        $this->fakeProvider();
        $p = $this->product();
        $user = User::factory()->create(['is_active' => true, 'created_at' => now()->subMonths(2)]);
        app(WalletService::class)->credit($user, 5, 'USD', ['reference' => 'seed:'.$user->id]);

        $this->expectException(GiftCardException::class);
        try {
            app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);
        } finally {
            $this->assertSame(0, GiftCardOrder::count());
        }
    }

    public function test_high_value_purchase_holds_in_review_then_delivers_on_approval(): void
    {
        $this->fakeProvider();
        Setting::setValue('giftcards.review_threshold', 40, 'giftcards'); // force a hold
        $p = $this->product();
        $user = $this->funded();
        $before = (float) $user->wallet->fresh()->usd_balance;

        $order = app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);

        $this->assertSame(GiftCardOrder::STATUS_REVIEW, $order->status);
        $this->assertLessThan($before, (float) $user->wallet->fresh()->usd_balance); // funds already committed

        app(GiftCardOrderService::class)->approveReview($order->fresh());
        $this->assertSame(GiftCardOrder::STATUS_DELIVERED, $order->fresh()->status);
    }

    public function test_rejecting_a_held_order_refunds_the_buyer(): void
    {
        $this->fakeProvider();
        Setting::setValue('giftcards.review_threshold', 40, 'giftcards');
        $p = $this->product();
        $user = $this->funded();
        $before = (float) $user->wallet->fresh()->usd_balance;

        $order = app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);
        app(GiftCardOrderService::class)->rejectReview($order->fresh(), 'looks fraudulent');

        $this->assertSame(GiftCardOrder::STATUS_REFUNDED, $order->fresh()->status);
        $this->assertEqualsWithDelta($before, (float) $user->wallet->fresh()->usd_balance, 0.0001);
    }

    public function test_new_account_cooling_off_blocks_a_high_value_purchase(): void
    {
        $this->fakeProvider();
        Setting::setValue('giftcards.cooloff_value', 30, 'giftcards');
        Setting::setValue('giftcards.cooloff_hours', 24, 'giftcards');
        $p = $this->product();
        $user = User::factory()->create(['is_active' => true, 'created_at' => now()->subHour()]); // brand new
        app(WalletService::class)->credit($user, 500, 'USD', ['reference' => 'seed:'.$user->id]);

        $this->expectException(GiftCardException::class);
        app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);
    }

    public function test_daily_count_limit_blocks_further_purchases(): void
    {
        $this->fakeProvider();
        Setting::setValue('giftcards.max_count_24h', 1, 'giftcards');
        $p = $this->product();
        $user = $this->funded();

        app(GiftCardOrderService::class)->purchase($user, $p, 25.0, ['email' => 'r@example.com']);

        $this->expectException(GiftCardException::class);
        app(GiftCardOrderService::class)->purchase($user, $p, 25.0, ['email' => 'r@example.com']);
    }

    public function test_the_receipt_screen_is_owner_scoped_and_hides_the_provider(): void
    {
        $this->fakeProvider();
        $p = $this->product();
        $user = $this->funded();
        $order = app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);

        $this->actingAs($user)->get(route('gift-cards.order', $order))
            ->assertOk()->assertSee('GIFT-1234')->assertDontSee('reloadly');

        $stranger = User::factory()->create(['is_active' => true]);
        $this->actingAs($stranger)->get(route('gift-cards.order', $order))->assertNotFound();
    }

    public function test_storefront_buy_redirects_to_the_receipt(): void
    {
        $this->fakeProvider();
        $p = $this->product();
        $user = $this->funded();

        Livewire::actingAs($user)->test(Storefront::class)
            ->call('select', $p->id)
            ->set('amount', 50)
            ->set('fields.email', 'r@example.com')
            ->call('buy')
            ->assertRedirect(route('gift-cards.order', GiftCardOrder::latest()->first()));
    }

    public function test_webhook_verifies_hmac_and_fills_the_receipt(): void
    {
        $this->fakeProvider('processing', []); // delivered later by webhook
        config(['services.reloadly.webhook_secret' => 'shh']);
        $p = $this->product();
        $user = $this->funded();
        $order = app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);
        $this->assertSame(GiftCardOrder::STATUS_PROCESSING, $order->status);

        $payload = json_encode([
            'customIdentifier' => $order->transaction_ref,
            'status' => 'SUCCESSFUL',
            'transactionId' => 'RL-777',
            'receipt' => ['cardNumber' => 'CARD-55', 'epin' => 'EPIN-55'],
        ]);

        // Bad signature is rejected.
        $this->call('POST', route('webhooks.giftcards', 'reloadly'), [], [], [],
            ['HTTP_X-Naara-Signature' => 'wrong', 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertStatus(401);

        // Correct signature delivers + fills the receipt.
        $sig = hash_hmac('sha256', $payload, 'shh');
        $this->call('POST', route('webhooks.giftcards', 'reloadly'), [], [], [],
            ['HTTP_X-Naara-Signature' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertOk();

        $order->refresh();
        $this->assertSame(GiftCardOrder::STATUS_DELIVERED, $order->status);
        $this->assertSame('EPIN-55', $order->receipt['epin']);
    }

    public function test_admin_can_approve_a_held_order(): void
    {
        $this->fakeProvider();
        Setting::setValue('giftcards.review_threshold', 40, 'giftcards');
        $p = $this->product();
        $user = $this->funded();
        $order = app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(AdminGiftCards::class)
            ->call('approve', $order->id);

        $this->assertSame(GiftCardOrder::STATUS_DELIVERED, $order->fresh()->status);
    }

    public function test_a_same_second_double_submit_charges_and_orders_exactly_once(): void
    {
        // Freeze time so both submits share the same time-bucketed reference.
        Carbon::setTestNow(Carbon::create(2026, 7, 28, 12, 0, 0));

        $fake = new FakeGiftCardProvider;
        $this->app->instance('giftcard.reloadly', $fake);
        $p = $this->product();
        $user = $this->funded();
        $before = (float) $user->wallet->fresh()->usd_balance;
        $retail = round(app(GiftCardPricing::class)->retail($p, 50.0, log: false), 4);

        $svc = app(GiftCardOrderService::class);
        $a = $svc->purchase($user, $p, 50.0, ['email' => 'r@example.com']);
        $b = $svc->purchase($user, $p, 50.0, ['email' => 'r@example.com']); // racing re-submit

        $this->assertSame(1, $fake->orderCalls, 'provider must be ordered once');
        $this->assertSame(1, GiftCardOrder::where('user_id', $user->id)->count());
        $this->assertSame($a->id, $b->id); // both land on the same order
        $this->assertEqualsWithDelta($before - $retail, (float) $user->wallet->fresh()->usd_balance, 0.0001);

        Carbon::setTestNow();
    }

    public function test_a_provider_that_returns_failed_without_throwing_still_refunds(): void
    {
        // Some providers answer 200 with status FAILED instead of an error.
        $this->app->instance('giftcard.reloadly', new FakeGiftCardProvider(status: 'failed'));
        $p = $this->product();
        $user = $this->funded();
        $before = (float) $user->wallet->fresh()->usd_balance;

        try {
            app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);
        } catch (GiftCardException) {
            // acceptable — the buyer sees a failure
        }

        $order = GiftCardOrder::latest()->first();
        $this->assertSame(GiftCardOrder::STATUS_FAILED, $order->status);
        $this->assertEqualsWithDelta($before, (float) $user->wallet->fresh()->usd_balance, 0.0001); // refunded
    }

    public function test_a_failed_webhook_on_a_processing_order_refunds_the_buyer(): void
    {
        $this->app->instance('giftcard.reloadly', new FakeGiftCardProvider(status: 'processing', receipt: []));
        config(['services.reloadly.webhook_secret' => 'shh']);
        $p = $this->product();
        $user = $this->funded();
        $before = (float) $user->wallet->fresh()->usd_balance;
        $order = app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);
        $this->assertSame(GiftCardOrder::STATUS_PROCESSING, $order->status);
        $this->assertLessThan($before, (float) $user->wallet->fresh()->usd_balance); // charged

        $payload = json_encode(['customIdentifier' => $order->transaction_ref, 'status' => 'FAILED']);
        $sig = hash_hmac('sha256', $payload, 'shh');
        $this->call('POST', route('webhooks.giftcards', 'reloadly'), [], [], [],
            ['HTTP_X-Naara-Signature' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

        $order->refresh();
        $this->assertContains($order->status, [GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED]);
        $this->assertEqualsWithDelta($before, (float) $user->wallet->fresh()->usd_balance, 0.0001); // refunded in full
    }

    public function test_admin_preflight_shows_a_live_connection_result(): void
    {
        $this->fakeProvider(); // fake preflight returns ok + a balance
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(AdminGiftCards::class)
            ->call('preflight', 'reloadly')
            ->assertSet('probe.reloadly.ok', true)
            ->assertSee('Connected');
    }

    public function test_admin_csv_export_lists_orders_but_never_cost(): void
    {
        $this->fakeProvider();
        $p = $this->product();
        $user = $this->funded();
        app(GiftCardOrderService::class)->purchase($user, $p, 50.0, ['email' => 'r@example.com']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        // The action is admin-gated and downloads a CSV.
        Livewire::actingAs($admin)->test(AdminGiftCards::class)
            ->call('exportCsv')
            ->assertFileDownloaded('naara-gift-orders-'.now()->format('Y-m-d').'.csv');

        // Capture the streamed body to prove it carries retail, not cost.
        $this->actingAs($admin);
        ob_start();
        (new AdminGiftCards)->exportCsv()->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('price_charged_usd', $content);
        $this->assertStringContainsString('Amazon', $content);
        $this->assertStringNotContainsString('cost_meta', $content);
        $this->assertStringNotContainsString('discountPercentage', $content);
    }
}
