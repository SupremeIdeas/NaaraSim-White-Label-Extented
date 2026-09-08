<?php

namespace Tests\Feature;

use App\Models\GiftCardOrder;
use App\Models\GiftCardProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\GiftCards\GiftCardBalanceCheckable;
use App\Services\GiftCards\GiftCardProviderInterface;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * "Check balance" — a REAL capability only where a provider actually exposes
 * an issued-card balance lookup (Tillo, confirmed documented). Never shown or
 * reachable for a provider that doesn't (Reloadly/Zendit/Bitrefill single-use
 * redemption codes have no such endpoint).
 */
class GiftCardBalanceCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        // naara_gift is "live" only when both admin-enabled AND its required
        // keys are configured (FeatureFlags::enabled() checks both) — a blank
        // CI env has neither by default, so both must be set explicitly here
        // rather than relying on ambient .env state.
        config(['services.reloadly.client_id' => 'id', 'services.reloadly.client_secret' => 'secret']);
        Setting::setValue('features.naara_gift.enabled', true);
        Cache::forget('features.enabled.naara_gift');
    }

    private function order(User $user, string $provider, string $status = GiftCardOrder::STATUS_DELIVERED): GiftCardOrder
    {
        $product = GiftCardProduct::create([
            'provider' => $provider, 'provider_product_id' => 'p1', 'brand_key' => 'amazon',
            'brand_name' => 'Amazon', 'currency' => 'USD', 'denomination_type' => 'FIXED',
            'fixed_denominations' => [25], 'is_primary' => true, 'admin_enabled' => true,
        ]);

        return GiftCardOrder::create([
            'user_id' => $user->id, 'gift_card_product_id' => $product->id,
            'provider' => $provider, 'provider_product_id' => 'p1', 'brand_name' => 'Amazon',
            'face_value' => 25, 'currency' => 'USD', 'price_charged' => 27,
            'status' => $status, 'transaction_ref' => 'ref-'.uniqid(),
            'provider_tx_id' => 'tx-123', 'receipt' => ['code' => 'GC-1'], 'fields' => [],
        ]);
    }

    private function bindBalanceCheckableFake(): void
    {
        $this->app->instance('giftcard.reloadly', new class implements GiftCardBalanceCheckable, GiftCardProviderInterface
        {
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
                return 0;
            }

            public function preflight(): array
            {
                return ['ok' => true, 'balance' => null, 'currency' => null, 'products' => null, 'error' => null];
            }

            public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
            {
                return ['provider_tx_id' => 'tx', 'status' => 'delivered', 'receipt' => []];
            }

            public function checkBalance(array $receipt): array
            {
                return ['balance' => 12.34, 'currency' => 'USD', 'checked_at' => now()->toIso8601String()];
            }
        });
    }

    public function test_the_receipt_page_shows_check_balance_only_for_a_capable_provider(): void
    {
        $this->bindBalanceCheckableFake();
        $user = User::factory()->create(['is_active' => true]);
        $order = $this->order($user, 'reloadly');

        $this->actingAs($user)->get(route('gift-cards.order', $order))
            ->assertOk()->assertSee('Check remaining balance');
    }

    public function test_the_receipt_page_hides_check_balance_for_a_provider_without_it(): void
    {
        // Real ZenditVoucherService — implements neither balance nor status checking.
        $user = User::factory()->create(['is_active' => true]);
        $order = $this->order($user, 'zendit');

        $this->actingAs($user)->get(route('gift-cards.order', $order))
            ->assertOk()->assertDontSee('Check remaining balance');
    }

    public function test_balance_endpoint_returns_the_live_balance_for_a_capable_provider(): void
    {
        $this->bindBalanceCheckableFake();
        $user = User::factory()->create(['is_active' => true]);
        $order = $this->order($user, 'reloadly');

        $this->actingAs($user)->postJson(route('gift-cards.order.balance', $order))
            ->assertOk()
            ->assertJson(['balance' => 12.34, 'currency' => 'USD']);
    }

    public function test_balance_endpoint_422s_for_a_provider_without_the_capability(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $order = $this->order($user, 'zendit');

        $this->actingAs($user)->postJson(route('gift-cards.order.balance', $order))
            ->assertStatus(422);
    }

    public function test_balance_endpoint_422s_for_an_order_that_is_not_delivered(): void
    {
        $this->bindBalanceCheckableFake();
        $user = User::factory()->create(['is_active' => true]);
        $order = $this->order($user, 'reloadly', GiftCardOrder::STATUS_PROCESSING);

        $this->actingAs($user)->postJson(route('gift-cards.order.balance', $order))
            ->assertStatus(422);
    }

    public function test_balance_endpoint_is_owner_scoped(): void
    {
        $this->bindBalanceCheckableFake();
        $owner = User::factory()->create(['is_active' => true]);
        $intruder = User::factory()->create(['is_active' => true]);
        $order = $this->order($owner, 'reloadly');

        $this->actingAs($intruder)->postJson(route('gift-cards.order.balance', $order))
            ->assertStatus(404);
    }
}
