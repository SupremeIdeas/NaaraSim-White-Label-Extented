<?php

namespace Tests\Feature;

use App\Exceptions\SmsException;
use App\Livewire\SendMessage;
use App\Models\OutboundMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\Pricing\PricingEngine;
use App\Services\SMS\MessageSenderService;
use App\Services\Wallet\WalletService;
use App\Support\BrandSettings;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakePermanentProvider;
use Tests\TestCase;

/**
 * Numbers V6 §6 — sending an SMS from the user's Naara Line. Same money
 * discipline as every other path: live retail quote (cost never exposed),
 * atomic charge before the message leaves, refund on any delivery failure.
 */
class MessageSenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
    }

    private function line(User $user, string $provider = 'twilio', array $caps = ['sms' => true, 'voice' => true]): VirtualNumber
    {
        return VirtualNumber::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'phone_number' => '+15550001111',
            'sid' => 'SID-1',
            'capabilities' => $caps,
            'monthly_cost' => 1.0,
            'monthly_retail' => 1.4,
            'status' => 'active',
            'next_billing_date' => now()->addMonth()->toDateString(),
            'provisioned_at' => now(),
        ]);
    }

    private function fundedUser(float $usd = 10.0, float $smsCost = 0.0079, bool $throwOnSend = false): User
    {
        app()->instance('number.twilio', new FakePermanentProvider(smsCost: $smsCost, throwOnSend: $throwOnSend));
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $usd, 'USD', ['reference' => 'seed:'.$user->id]);

        return $user->fresh();
    }

    // --- Owner audit (2026-09-15): brand-word wiring ---

    public function test_the_no_line_empty_state_copy_is_rebranded(): void
    {
        Setting::setValue('brand.word', 'Acme', 'brand');
        BrandSettings::flush();

        Livewire::actingAs(User::factory()->create())->test(SendMessage::class)
            ->set('open', true)
            ->assertSee('Acme Line')
            ->assertDontSee('Naara Line');
    }

    public function test_quote_returns_retail_total_without_cost(): void
    {
        $user = $this->fundedUser(smsCost: 0.01);
        $line = $this->line($user);

        // cost 0.01 → retail = 0.01 * 1.40 = 0.014, floored by min-profit (0.01+0.01=0.02).
        $quote = app(MessageSenderService::class)->quote($line, 'Hi there');

        $this->assertSame(1, $quote['segments']);
        $this->assertSame(0.02, round($quote['retail_total'], 2));
        $this->assertArrayNotHasKey('cost', $quote);
        $this->assertArrayNotHasKey('retail_per_segment_cost', $quote);
    }

    public function test_send_charges_retail_atomically_and_delivers(): void
    {
        $user = $this->fundedUser(usd: 5.0, smsCost: 0.10);
        $line = $this->line($user);

        // Retail comes from the engine (never a literal here) — MarginGuard-floored.
        $retail = round(app(PricingEngine::class)->calculateSmsRetail(0.10, 'twilio'), 4);

        $msg = app(MessageSenderService::class)->send($user, $line, '+2348012345678', 'Hello world');

        $this->assertSame('sent', $msg->status);
        $this->assertSame(sprintf('%.4f', $retail), (string) $msg->amount_charged);
        $this->assertSame('MSG-1', $msg->provider_ref);
        $this->assertSame(sprintf('%.4f', 5.0 - $retail), (string) $user->wallet->fresh()->usd_balance);

        // Paired debit row carries balance_before/after (atomic).
        $debit = $user->walletTransactions()->where('type', 'debit')->latest('id')->first();
        $this->assertSame('5.0000', (string) $debit->balance_before);
        $this->assertSame(sprintf('%.4f', 5.0 - $retail), (string) $debit->balance_after);
    }

    public function test_a_multi_part_message_is_charged_per_segment(): void
    {
        $user = $this->fundedUser(usd: 5.0, smsCost: 0.10);
        $line = $this->line($user);

        $retail = round(app(PricingEngine::class)->calculateSmsRetail(0.10, 'twilio'), 4);

        $msg = app(MessageSenderService::class)->send($user, $line, '+2348012345678', str_repeat('a', 200));

        $this->assertSame(2, $msg->segments);          // 200 chars → 2 parts
        $this->assertSame(sprintf('%.4f', round($retail * 2, 4)), (string) $msg->amount_charged);
    }

    public function test_a_delivery_failure_refunds_in_full(): void
    {
        $user = $this->fundedUser(usd: 5.0, smsCost: 0.10, throwOnSend: true);
        $line = $this->line($user);

        try {
            app(MessageSenderService::class)->send($user, $line, '+2348012345678', 'Hello');
            $this->fail('Expected an SmsException.');
        } catch (SmsException $e) {
            // expected
        }

        // Never charged without delivering — balance restored, row marked failed.
        $this->assertSame('5.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame('failed', OutboundMessage::first()->status);
    }

    public function test_an_attachment_sends_as_mms_and_is_stored(): void
    {
        Storage::fake('public');
        $user = $this->fundedUser(usd: 5.0);
        $line = $this->line($user); // +1 US number → MMS-capable
        $fake = app('number.twilio');

        $msg = app(MessageSenderService::class)->send(
            $user, $line, '+15551234567', 'See attached', 'https://cdn.test/pic.jpg'
        );

        $this->assertSame('sent', $msg->status);
        $this->assertSame('https://cdn.test/pic.jpg', $msg->attachment_url);
        $this->assertSame(1, $msg->segments); // MMS billed as one media message
        // The provider received the media URL (real MMS send).
        $this->assertSame('https://cdn.test/pic.jpg', $fake->sent[0]['media']);
    }

    public function test_a_non_us_line_refuses_an_attachment(): void
    {
        $user = $this->fundedUser(usd: 5.0);
        $line = $this->line($user);
        $line->update(['phone_number' => '+442012345678']); // UK — no MMS

        try {
            app(MessageSenderService::class)->send($user, $line, '+15551234567', 'Hi', 'https://cdn.test/pic.jpg');
            $this->fail('Expected an SmsException.');
        } catch (SmsException $e) {
            // expected
        }

        // Never charged — the media send was refused before any debit.
        $this->assertSame('5.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    /**
     * Marketing/Chat blueprint Phase B3 audit: only Twilio, Telnyx and Plivo
     * actually accept a native MMS media attachment — Vonage and Sinch
     * silently degrade a media URL to an appended text link
     * (VonageService/SinchService::sendSms()), and Sonetel has no
     * outbound-SMS endpoint at all. A US/Canada number provisioned via one
     * of those must NOT report itself as MMS-capable, or a user's photo/
     * voice note would quietly arrive as a bare link instead of a real
     * attachment.
     */
    public function test_mms_capability_is_provider_aware_not_just_country_aware(): void
    {
        $user = $this->fundedUser(usd: 5.0);
        $n = 0;
        $lineFor = function (string $provider) use ($user, &$n) {
            $n++;

            return VirtualNumber::create([
                'user_id' => $user->id, 'provider' => $provider, 'phone_number' => "+1555000{$n}",
                'sid' => "SID-{$n}", 'capabilities' => ['sms' => true, 'voice' => true],
                'monthly_cost' => 1.0, 'monthly_retail' => 1.4, 'status' => 'active',
                'next_billing_date' => now()->addMonth()->toDateString(), 'provisioned_at' => now(),
            ]);
        };

        $this->assertTrue($lineFor('twilio')->supportsMms());
        $this->assertTrue($lineFor('telnyx')->supportsMms());
        $this->assertTrue($lineFor('plivo')->supportsMms());
        $this->assertFalse($lineFor('vonage')->supportsMms());
        $this->assertFalse($lineFor('sinch')->supportsMms());
        $this->assertFalse($lineFor('sonetel')->supportsMms());
    }

    public function test_a_voice_note_sends_as_mms_through_the_modal(): void
    {
        Storage::fake('public');
        $user = $this->fundedUser(usd: 5.0, smsCost: 0.10);
        $line = $this->line($user); // twilio, US → MMS-capable

        Livewire::actingAs($user)->test(SendMessage::class)
            ->call('openFor', '+2348012345678', 'Ada Obi')
            ->set('voiceNote', UploadedFile::fake()->create('note.webm', 50, 'audio/webm'))
            ->call('send')
            ->assertSet('open', false)
            ->assertSet('error', null);

        $msg = OutboundMessage::first();
        $this->assertSame('sent', $msg->status);
        $this->assertNotNull($msg->attachment_url);
        $this->assertSame(1, $msg->segments); // MMS billed as one media message
    }

    public function test_a_voice_note_from_a_non_mms_provider_is_refused_before_charging(): void
    {
        $user = $this->fundedUser(usd: 5.0);
        $line = $this->line($user, 'vonage'); // US number, but Vonage has no native MMS

        Livewire::actingAs($user)->test(SendMessage::class)
            ->call('openFor', '+2348012345678', 'Ada Obi')
            ->set('lineId', $line->id)
            ->set('voiceNote', UploadedFile::fake()->create('note.webm', 50, 'audio/webm'))
            ->call('send')
            ->assertSet('open', true); // never closes — refused, not sent

        $this->assertSame(0, OutboundMessage::count());
        $this->assertSame('5.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    public function test_it_refuses_to_send_from_another_users_line(): void
    {
        $owner = $this->fundedUser();
        $line = $this->line($owner);
        $attacker = User::factory()->create();
        app(WalletService::class)->credit($attacker, 5.0, 'USD', ['reference' => 'seed-att']);

        $this->expectException(SmsException::class);
        app(MessageSenderService::class)->send($attacker->fresh(), $line, '+2348012345678', 'Hi');
    }

    public function test_it_rejects_an_invalid_destination_before_charging(): void
    {
        $user = $this->fundedUser(usd: 5.0);
        $line = $this->line($user);

        try {
            app(MessageSenderService::class)->send($user, $line, '12345', 'Hi');
            $this->fail('Expected an SmsException.');
        } catch (SmsException $e) {
            // expected
        }

        $this->assertSame('5.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertSame(0, OutboundMessage::count());
    }

    public function test_the_modal_sends_and_closes(): void
    {
        $user = $this->fundedUser(usd: 5.0, smsCost: 0.10);
        $line = $this->line($user);

        Livewire::actingAs($user)->test(SendMessage::class)
            ->call('openFor', '+2348012345678', 'Ada Obi')
            ->assertSet('open', true)
            ->assertSet('lineId', $line->id)
            ->set('body', 'Hello world')
            ->call('send')
            ->assertSet('open', false)
            ->assertHasNoErrors();

        $this->assertSame('sent', OutboundMessage::first()->status);
    }

    public function test_the_provider_is_never_serialised_on_a_message(): void
    {
        $user = $this->fundedUser(usd: 5.0, smsCost: 0.10);
        $line = $this->line($user);
        $msg = app(MessageSenderService::class)->send($user, $line, '+2348012345678', 'Hi');

        $this->assertArrayNotHasKey('provider', $msg->toArray());
    }
}
