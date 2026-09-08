<?php

namespace Tests\Feature;

use App\Events\PayoutReversed;
use App\Livewire\Admin\Payouts;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutTransferResult;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ROADMAP §Layer 0 admin controls — the operator toggles payouts and, in manual
 * mode, approves or declines each pending request.
 */
class AdminPayoutsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function fakeEngine(string $sendStatus = 'processing'): void
    {
        $gateway = new class($sendStatus) implements PayoutGatewayInterface
        {
            public function __construct(private string $sendStatus)
            {
            }

            public function name(): string
            {
                return 'paystack';
            }

            public function available(): bool
            {
                return true;
            }

            public function createRecipient(PayoutAccount $account): string
            {
                return 'RCP';
            }

            public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
            {
                return new PayoutTransferResult(status: $this->sendStatus, providerRef: 'TRF');
            }

            public function verifyWebhook(\Illuminate\Http\Request $request): bool
            {
                return true;
            }

            public function parseWebhook(\Illuminate\Http\Request $request): ?\App\Services\Payouts\PayoutEvent
            {
                return null;
            }
        };
        $this->app->instance(PayoutService::class, new PayoutService([$gateway]));
    }

    private function pendingRequest(User $user): PayoutRequest
    {
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'JANE T.',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);

        return PayoutRequest::create([
            'user_id' => $user->id, 'payout_account_id' => $account->id, 'amount' => 12.0,
            'currency' => 'NGN', 'source_bucket' => 'referral_credits', 'status' => PayoutRequest::PENDING,
            'provider' => 'paystack', 'reference' => 'wd:admin:'.$user->id,
        ]);
    }

    public function test_the_page_is_admin_only(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Payouts::class)->assertForbidden();
    }

    public function test_admin_can_toggle_the_feature_and_settings(): void
    {
        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->set('enabled', true)
            ->set('mode', 'autopilot')
            ->set('minWithdrawal', 20)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(PayoutSettings::enabled());
        $this->assertTrue(PayoutSettings::autopilot());
        $this->assertSame(20.0, PayoutSettings::minWithdrawal());
        $this->assertSame(true, (bool) Setting::getValue(PayoutSettings::FLAG));
    }

    public function test_admin_can_approve_a_pending_payout(): void
    {
        $this->fakeEngine('processing');
        $user = User::factory()->create();
        $req = $this->pendingRequest($user);

        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->call('approve', $req->id);

        $this->assertSame(PayoutRequest::PROCESSING, $req->fresh()->status);
    }

    public function test_admin_can_decline_a_pending_payout_and_reverse_it(): void
    {
        Event::fake([PayoutReversed::class]);
        $this->fakeEngine();
        $user = User::factory()->create();
        $req = $this->pendingRequest($user);

        Livewire::actingAs($this->admin())->test(Payouts::class)
            ->call('reject', $req->id);

        $this->assertSame(PayoutRequest::REVERSED, $req->fresh()->status);
        Event::assertDispatched(PayoutReversed::class);
    }
}
