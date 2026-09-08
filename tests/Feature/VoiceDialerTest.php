<?php

namespace Tests\Feature;

use App\Jobs\LogVoiceCdrJob;
use App\Livewire\Dialer;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\Voice\VoiceDialerService;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeVoiceProvider;
use Tests\TestCase;

/**
 * Live Voice — Part B (in-browser dialer). The wallet is quoted at the live
 * retail rate, pre-authorised for a funded block of minutes, and settled on
 * hang-up (unused minutes refunded). Cost is never exposed; the whole feature
 * is gated on the existing Twilio provider status.
 */
class VoiceDialerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        // Voice rides the same Twilio keys — Active for these tests.
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);
    }

    private function fundedUser(float $usd = 10.0, float $rate = 0.10): User
    {
        app()->instance('number.twilio', new FakeVoiceProvider(rate: $rate));
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $usd, 'USD', ['reference' => 'seed:'.$user->id]);

        return $user->fresh();
    }

    public function test_quote_returns_retail_and_funded_minutes_without_cost(): void
    {
        // Cost 0.10/min → retail = 0.10 * 1.40 = 0.14/min. $10 funds floor(10/0.14)=71,
        // capped at the 60-minute ceiling.
        $user = $this->fundedUser(usd: 10.0, rate: 0.10);

        $quote = app(VoiceDialerService::class)->quote($user, '+2348012345678');

        $this->assertSame(0.14, round($quote['retail_per_min'], 2));
        $this->assertSame(60, $quote['funded_minutes']); // capped
        $this->assertArrayNotHasKey('cost', $quote);
        $this->assertArrayNotHasKey('provider_rate', $quote);
    }

    public function test_begin_holds_the_funded_block_atomically(): void
    {
        // Small balance so the cap doesn't bite: $1.40 at 0.14/min funds 10 min.
        $user = $this->fundedUser(usd: 1.40, rate: 0.10);

        $call = app(VoiceDialerService::class)->begin($user, '+2348012345678');

        $this->assertSame(10, $call->minutes_authorized);
        $this->assertSame('1.4000', (string) $call->amount_held);
        // Wallet fully held; a paired debit row with balance_before/after exists.
        $this->assertSame('0.0000', (string) $user->wallet->fresh()->usd_balance);
        $debit = $user->walletTransactions()->where('type', 'debit')->latest('id')->first();
        $this->assertSame('1.4000', (string) $debit->balance_before);
        $this->assertSame('0.0000', (string) $debit->balance_after);
        // Cost is stored for margin auditing but hidden from serialisation.
        $this->assertArrayNotHasKey('provider_rate', $call->toArray());
    }

    public function test_begin_rejects_when_balance_cannot_fund_a_minute(): void
    {
        $user = $this->fundedUser(usd: 0.05, rate: 0.10); // < one 0.14 minute

        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        app(VoiceDialerService::class)->begin($user, '+2348012345678');

        // No charge, no call row.
        $this->assertSame('0.0500', (string) $user->wallet->fresh()->usd_balance);
        $this->assertDatabaseCount('voice_calls', 0);
    }

    public function test_settle_bills_used_minutes_and_refunds_the_rest(): void
    {
        Queue::fake();
        $user = $this->fundedUser(usd: 1.40, rate: 0.10); // 10 min held @ 0.14
        $dialer = app(VoiceDialerService::class);
        $call = $dialer->begin($user, '+2348012345678');

        // 2m30s of talk → billed 3 minutes = 0.42; refund 1.40 - 0.42 = 0.98.
        $dialer->settle($call, 150, 'completed');

        $call->refresh();
        $this->assertSame(3, $call->minutes_billed);
        $this->assertSame('0.4200', (string) $call->amount_charged);
        $this->assertSame('0.9800', (string) $call->refunded);
        $this->assertSame('0.9800', (string) $user->wallet->fresh()->usd_balance);
        $this->assertNotNull($call->settled_at);
        Queue::assertPushed(LogVoiceCdrJob::class);
    }

    public function test_a_call_that_never_connects_is_refunded_in_full(): void
    {
        $user = $this->fundedUser(usd: 1.40, rate: 0.10);
        $dialer = app(VoiceDialerService::class);
        $call = $dialer->begin($user, '+2348012345678');

        $dialer->settle($call, 0, 'no-answer');

        $call->refresh();
        $this->assertSame(0, $call->minutes_billed);
        $this->assertSame('1.4000', (string) $call->refunded);
        $this->assertSame('1.4000', (string) $user->wallet->fresh()->usd_balance);
    }

    public function test_settlement_is_idempotent(): void
    {
        $user = $this->fundedUser(usd: 1.40, rate: 0.10);
        $dialer = app(VoiceDialerService::class);
        $call = $dialer->begin($user, '+2348012345678');

        $dialer->settle($call, 150, 'completed');
        $dialer->settle($call, 150, 'completed'); // webhook + client hang-up

        // Refund happened exactly once.
        $this->assertSame('0.9800', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(1, $user->walletTransactions()->where('type', 'refund')->count());
    }

    public function test_the_dialer_is_hidden_until_twilio_is_active(): void
    {
        config(['services.twilio.account_sid' => '', 'services.twilio.auth_token' => '']);

        Livewire::actingAs(User::factory()->create())->test(Dialer::class)->assertStatus(404);
    }

    public function test_the_token_endpoint_is_gated_and_scoped_to_the_user(): void
    {
        app()->instance('number.twilio', new FakeVoiceProvider());
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $this->actingAs($user)->post(route('voice.token'))
            ->assertOk()
            ->assertJson(['identity' => 'naara_user_'.$user->id]);

        // Gone when Twilio isn't Active.
        config(['services.twilio.account_sid' => '', 'services.twilio.auth_token' => '']);
        $this->actingAs($user)->post(route('voice.token'))->assertNotFound();
    }

    public function test_dial_opens_a_call_and_hands_the_browser_the_funded_seconds(): void
    {
        $user = $this->fundedUser(usd: 1.40, rate: 0.10);

        Livewire::actingAs($user)->test(Dialer::class)
            ->set('destination', '+2348012345678')
            ->call('prepare')
            ->assertSet('quoted', true)
            ->assertSet('fundedMinutes', 10)
            ->call('dial')
            ->assertDispatched('voice-dial', fundedSeconds: 600);

        $this->assertDatabaseHas('voice_calls', [
            'user_id' => $user->id, 'destination' => '+2348012345678', 'status' => 'connecting',
        ]);
    }

    public function test_an_invalid_number_is_rejected_before_charging(): void
    {
        $user = $this->fundedUser(usd: 10.0, rate: 0.10);

        Livewire::actingAs($user)->test(Dialer::class)
            ->set('destination', '08012345678') // not E.164
            ->call('prepare')
            ->assertSet('quoted', false)
            ->assertSet('error', fn ($e) => $e !== null);

        $this->assertSame('10.0000', (string) $user->wallet->fresh()->usd_balance);
    }
}
