<?php

namespace Tests\Feature;

use App\Livewire\MerchantEarnings;
use App\Livewire\Receipts;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * BUILD-7 §1 + §3: the customer receipts tab (over wallet_transactions) and the
 * merchant earnings analytics page.
 */
class ReceiptsAndEarningsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipts_tab_lists_purchase_debits_for_the_owner_only(): void
    {
        $user = User::factory()->create();
        WalletTransaction::create([
            'user_id' => $user->id, 'type' => 'debit', 'amount' => 12.5, 'currency' => 'USD',
            'balance_before' => 100, 'balance_after' => 87.5, 'reference' => 'ESIM-1',
            'description' => 'Global 5GB eSIM', 'status' => 'completed',
        ]);
        $other = User::factory()->create();
        WalletTransaction::create([
            'user_id' => $other->id, 'type' => 'debit', 'amount' => 9, 'currency' => 'USD',
            'balance_before' => 9, 'balance_after' => 0, 'reference' => 'NUM-9',
            'description' => 'Someone else purchase', 'status' => 'completed',
        ]);

        Livewire::actingAs($user)->test(Receipts::class)
            ->assertSee('Global 5GB eSIM')
            ->assertDontSee('Someone else purchase');
    }

    public function test_merchant_earnings_page_is_merchant_only(): void
    {
        $plain = User::factory()->create();
        Livewire::actingAs($plain)->test(MerchantEarnings::class)->assertStatus(404);

        $merchantUser = User::factory()->create();
        Merchant::create(['owner_user_id' => $merchantUser->id, 'business_name' => 'Biz', 'slug' => 'biz-x', 'status' => Merchant::ACTIVE]);
        Livewire::actingAs($merchantUser)->test(MerchantEarnings::class)
            ->assertOk()
            ->assertSee('Earnings');
    }
}
