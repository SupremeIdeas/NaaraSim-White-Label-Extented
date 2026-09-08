<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Top-up pending-state UX (owner report): the actual credit lands via a
 * queued webhook job that can take up to ~2 minutes on shared hosting's
 * cron-drain cadence. A session-persisted marker survives the gateway
 * redirect-away/redirect-back round trip and drives a polling "processing"
 * banner that disappears the instant WalletService::creditTopUp() has
 * actually run — replaced by the existing fintech-style success hero toast.
 */
class TopUpPendingStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        Http::fake([
            'api.paystack.co/*' => Http::response(['data' => ['authorization_url' => 'https://checkout.paystack.com/xyz']]),
        ]);
    }

    /** PaystackGateway mints its OWN 'NAARA-{uuid}' reference — start a
     *  top-up and hand back the one it actually generated. */
    private function startTopUp(User $user): string
    {
        Livewire::actingAs($user)->test(Wallet::class)
            ->set('currency', 'USD')->set('amount', 20)->set('gateway', 'paystack')
            ->call('topUp');

        return session('pending_topup')['reference'];
    }

    public function test_topup_stores_a_pending_marker_in_the_session(): void
    {
        $user = User::factory()->create();

        $reference = $this->startTopUp($user);

        $pending = session('pending_topup');
        $this->assertNotNull($pending);
        $this->assertSame('paystack', $pending['gateway']);
        $this->assertSame($reference, $pending['reference']);
        $this->assertSame(20.0, $pending['amount']);
        $this->assertSame('USD', $pending['currency']);
    }

    public function test_a_fresh_mount_shows_the_processing_banner_while_still_pending(): void
    {
        $user = User::factory()->create();
        $reference = $this->startTopUp($user);

        // Simulates the browser landing back on /wallet after the gateway redirect.
        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSee('Processing your top-up')
            ->assertSet('pendingTopUp.reference', $reference);
    }

    public function test_a_fresh_mount_resolves_immediately_if_the_credit_already_landed(): void
    {
        $user = User::factory()->create();
        $reference = $this->startTopUp($user);

        // The queued job already ran (sync queue in tests) via the real webhook path.
        app(WalletService::class)->creditTopUp($user, 20, 'USD', ['reference' => "topup:paystack:{$reference}"]);

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertDontSee('Processing your top-up')
            ->assertSet('pendingTopUp', null)
            ->assertDispatched('nx-toast', variant: 'hero', type: 'success');

        $this->assertNull(session('pending_topup'));
    }

    public function test_the_polled_check_clears_the_banner_once_the_credit_lands(): void
    {
        $user = User::factory()->create();
        $reference = $this->startTopUp($user);

        $component = Livewire::actingAs($user)->test(Wallet::class);
        $component->call('checkPendingTopUp')->assertSet('pendingTopUp.reference', $reference);

        app(WalletService::class)->creditTopUp($user, 20, 'USD', ['reference' => "topup:paystack:{$reference}"]);

        $component->call('checkPendingTopUp')
            ->assertSet('pendingTopUp', null)
            ->assertDispatched('nx-toast', variant: 'hero', type: 'success');

        $this->assertNull(session('pending_topup'));
    }

    public function test_the_polled_check_does_nothing_while_still_genuinely_pending(): void
    {
        $user = User::factory()->create();
        $reference = $this->startTopUp($user);

        Livewire::actingAs($user)->test(Wallet::class)
            ->call('checkPendingTopUp')
            ->assertNotDispatched('nx-toast')
            ->assertSet('pendingTopUp.reference', $reference);

        $this->assertSame(0, WalletTransaction::where('user_id', $user->id)->count());
    }

    public function test_a_stale_pending_marker_past_the_timeout_is_cleared_not_shown(): void
    {
        $user = User::factory()->create();
        session(['pending_topup' => [
            'gateway' => 'paystack', 'reference' => 'OLD-REF', 'amount' => 20, 'currency' => 'USD',
            'started_at' => time() - 700, // past the 600s timeout
        ]]);

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertDontSee('Processing your top-up')
            ->assertSet('pendingTopUp', null);

        $this->assertNull(session('pending_topup'));
    }
}
