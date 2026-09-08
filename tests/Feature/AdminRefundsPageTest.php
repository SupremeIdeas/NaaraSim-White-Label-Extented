<?php

namespace Tests\Feature;

use App\Livewire\Admin\Refunds;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AdminRefundsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_page_is_admin_only(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/refunds')->assertNotFound();

        $this->actingAs($this->admin())->get('/adminmaster/refunds')->assertOk();
    }

    public function test_admin_can_refund_a_top_up_from_the_page(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x']);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['id' => 'RF_UI']])]);

        $buyer = User::factory()->create();
        $topup = app(WalletService::class)->credit($buyer, 25.0, 'USD', [
            'reference' => 'topup:paystack:UI-1', 'description' => 'Wallet top-up via paystack',
        ]);

        Livewire::actingAs($this->admin())->test(Refunds::class)
            ->call('openRefund', $topup->id)
            ->set('refundReason', 'customer request')
            ->call('refund')
            ->assertHasNoErrors();

        $this->assertSame(PaymentRefund::STATUS_DONE, PaymentRefund::first()->status);
        $this->assertSame('0.0000', (string) $buyer->wallet->fresh()->usd_balance);
    }

    public function test_refund_error_is_surfaced_not_thrown(): void
    {
        // Spent funds → RefundService refuses; the component shows a toast, no crash.
        config(['services.paystack.secret_key' => 'sk_test_x']);
        $buyer = User::factory()->create();
        $topup = app(WalletService::class)->credit($buyer, 20.0, 'USD', [
            'reference' => 'topup:paystack:UI-2', 'description' => 'Wallet top-up via paystack',
        ]);
        app(WalletService::class)->debit($buyer, 18.0, 'USD', ['reference' => 'spend:ui2', 'description' => 'spent']);

        Livewire::actingAs($this->admin())->test(Refunds::class)
            ->call('openRefund', $topup->id)
            ->call('refund')
            ->assertHasNoErrors();

        $this->assertSame(0, WalletTransaction::where('reference', 'refund-reversal:paystack:UI-2')->count());
    }
}
