<?php

namespace Tests\Feature;

use App\Exceptions\SmsException;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\SMS\MessageSenderService;
use App\Services\SMS\SmsOptOuts;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakePermanentProvider;
use Tests\TestCase;

/** Blueprint 5.1 compliance: STOP / START from the other party is honoured on that Naara Line. */
class SmsOptOutTest extends TestCase
{
    use RefreshDatabase;

    private function line(User $user): VirtualNumber
    {
        return VirtualNumber::create([
            'user_id' => $user->id, 'provider' => 'twilio', 'phone_number' => '+15550001111', 'sid' => 'SID-1', 'capabilities' => ['sms' => true],
            'monthly_cost' => 1.0, 'monthly_retail' => 1.4, 'status' => 'active', 'next_billing_date' => now()->addMonth()->toDateString(), 'provisioned_at' => now(),
        ]);
    }

    public function test_only_a_whole_message_keyword_counts(): void
    {
        foreach (['STOP', ' stop. ', 'Unsubscribe!', 'STOPALL'] as $b) {
            $this->assertSame('stop', SmsOptOuts::keyword($b), $b);
        }
        $this->assertSame('start', SmsOptOuts::keyword('Start'));
        $this->assertSame('help', SmsOptOuts::keyword('HELP'));
        foreach (['please do not stop', 'stop calling me at night', 'ok', '', null] as $b) {
            $this->assertNull(SmsOptOuts::keyword($b), (string) $b);
        }
    }

    public function test_inbound_stop_blocks_sending_and_start_restores_it_without_charging(): void
    {
        $this->seed(PricingSettingsSeeder::class);
        Queue::fake();
        config(['services.twilio.webhook_token' => 'secret']);
        app()->instance('number.twilio', new FakePermanentProvider(smsCost: 0.0079));
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 5.0, 'USD', ['reference' => 'seed']);
        $line = $this->line($user);
        $hit = fn (string $body) => $this->postJson('/webhooks/sms-inbound/twilio?token=secret', ['To' => '+15550001111', 'From' => '+2348012345678', 'Body' => $body, 'MessageSid' => 'S'.uniqid()])->assertOk();

        $hit('STOP');
        $before = (float) $user->fresh()->wallet->usd_balance;
        try {
            app(MessageSenderService::class)->send($user, $line, '+2348012345678', 'Hello');
            $this->fail('A STOP-ed number must not be texted.');
        } catch (SmsException $e) {
            $this->assertStringContainsString('STOP', $e->getMessage());
        }
        $this->assertSame($before, (float) $user->fresh()->wallet->usd_balance, 'nothing is charged for a refused send');

        $hit('START');
        $this->assertNotNull(app(MessageSenderService::class)->send($user, $line, '+2348012345678', 'Hello'));
    }
}
