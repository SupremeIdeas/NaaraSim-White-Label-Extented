<?php

namespace Tests\Feature;

use App\Events\OtpReceived;
use App\Jobs\PollSmsOtpJob;
use App\Models\SmsOrder;
use App\Models\User;
use App\Services\SMS\OtpStatus;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

class PollSmsOtpJobTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $user, array $overrides = []): SmsOrder
    {
        return SmsOrder::create(array_merge([
            'user_id' => $user->id,
            'provider' => 'fivesim',
            'service_name' => 'whatsapp',
            'getatext_id' => 'REF-9',
            'phone_number' => '2348010000000',
            'status' => 'waiting',
            'provider_cost' => 0.2,
            'charged_to_user' => 3.0,
            'ordered_at' => now(),
        ], $overrides));
    }

    public function test_received_code_finishes_order_broadcasts_and_completes(): void
    {
        Event::fake([OtpReceived::class]);
        $user = User::factory()->create();
        $order = $this->order($user);

        $fake = new FakeSmsProvider(checkResponse: ['status' => OtpStatus::RECEIVED, 'code' => '778899']);
        app()->instance('number.fivesim', $fake);

        (new PollSmsOtpJob($order->id))->handle(app(WalletService::class));

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('778899', $order->otp_code);
        $this->assertSame(1, $fake->finishCalls, '5sim finish must be called on received');
        Event::assertDispatched(OtpReceived::class, fn ($e) => $e->code === '778899' && $e->userId === $user->id);
    }

    public function test_timeout_cancels_and_refunds(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, ['ordered_at' => now()->subMinutes(20)]);

        $fake = new FakeSmsProvider(checkResponse: ['status' => OtpStatus::PENDING, 'code' => null]);
        app()->instance('number.fivesim', $fake);

        (new PollSmsOtpJob($order->id, 'USD'))->handle(app(WalletService::class));

        $order->refresh();
        $this->assertSame('timeout', $order->status);
        $this->assertSame(1, $fake->cancelCalls, 'order must be cancelled on timeout');
        // Refunded charged_to_user (3.00) to the USD wallet.
        $this->assertSame('3.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(1, $user->walletTransactions()->where('type', 'refund')->count());
    }

    public function test_a_failing_provider_cancel_still_refunds_the_user(): void
    {
        // The auto-refund is the money promise — it must not be gated behind a
        // best-effort provider cancel that can fail.
        $user = User::factory()->create();
        $order = $this->order($user, ['ordered_at' => now()->subMinutes(20)]);

        $fake = new FakeSmsProvider(checkResponse: ['status' => OtpStatus::PENDING, 'code' => null]);
        $fake->cancelThrows = true;
        app()->instance('number.fivesim', $fake);

        (new PollSmsOtpJob($order->id, 'USD'))->handle(app(WalletService::class));

        $order->refresh();
        $this->assertSame('timeout', $order->status);
        $this->assertSame('3.0000', (string) $user->wallet->fresh()->usd_balance); // refunded despite cancel throwing
        $this->assertSame(1, $user->walletTransactions()->where('type', 'refund')->count());
    }

    public function test_still_waiting_reschedules_another_poll(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $order = $this->order($user); // ordered just now

        app()->instance('number.fivesim', new FakeSmsProvider(checkResponse: ['status' => OtpStatus::PENDING, 'code' => null]));

        (new PollSmsOtpJob($order->id))->handle(app(WalletService::class));

        $this->assertSame('waiting', $order->fresh()->status);
        Queue::assertPushed(PollSmsOtpJob::class);
    }
}
