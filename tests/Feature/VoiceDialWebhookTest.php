<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VoiceCall;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Live Voice — Part B webhooks. The outbound-dial TwiML webhook and the
 * settlement webhook are both signature-verified (money-safety rule 9) before
 * they touch a call or the wallet.
 */
class VoiceDialWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        config([
            'services.twilio.account_sid' => 'AC_test',
            'services.twilio.auth_token' => 'secret_token',
            'services.twilio.caller_id' => '+15005550006',
        ]);
    }

    /** Twilio's X-Twilio-Signature over the FULL URL + sorted POST params. */
    private function sign(string $url, array $params): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }

        return base64_encode(hash_hmac('sha1', $data, 'secret_token', true));
    }

    private function connectingCall(User $user, int $minutes = 10): VoiceCall
    {
        return VoiceCall::create([
            'user_id' => $user->id, 'destination' => '+2348012345678', 'provider' => 'twilio',
            'status' => 'connecting', 'retail_per_min' => 0.14, 'provider_rate' => 0.10,
            'minutes_authorized' => $minutes, 'amount_held' => round($minutes * 0.14, 4),
            'hold_reference' => 'voice-hold:'.$user->id.':x',
        ]);
    }

    public function test_verified_dial_webhook_returns_dial_twiml_with_the_funded_time_limit(): void
    {
        $user = User::factory()->create();
        $call = $this->connectingCall($user);

        $url = route('webhooks.twilio.dial');
        $params = [
            'To' => '+2348012345678', 'From' => 'client:naara_user_'.$user->id,
            'CallId' => (string) $call->id, 'CallSid' => 'CA_dialer',
        ];

        $res = $this->withHeaders(['X-Twilio-Signature' => $this->sign($url, $params)])->post($url, $params);

        $res->assertOk();
        $res->assertSee('<Dial', false);
        $res->assertSee('timeLimit="600"', false); // 10 funded minutes
        $res->assertSee('<Number>+2348012345678</Number>', false);
        $res->assertSee('callerId="+15005550006"', false);
        $this->assertSame('in-progress', $call->fresh()->status);
        $this->assertSame('CA_dialer', $call->fresh()->call_sid);
    }

    public function test_dial_webhook_rejects_an_invalid_signature(): void
    {
        $user = User::factory()->create();
        $call = $this->connectingCall($user);

        $url = route('webhooks.twilio.dial');
        $params = ['To' => '+2348012345678', 'From' => 'client:naara_user_'.$user->id, 'CallId' => (string) $call->id];

        $this->withHeaders(['X-Twilio-Signature' => 'wrong'])->post($url, $params)
            ->assertStatus(403)
            ->assertSee('<Reject', false);
        $this->assertSame('connecting', $call->fresh()->status);
    }

    public function test_dial_webhook_rejects_a_call_not_owned_by_the_client_identity(): void
    {
        $owner = User::factory()->create();
        $call = $this->connectingCall($owner);
        $attacker = User::factory()->create();

        $url = route('webhooks.twilio.dial');
        // Attacker's identity but the owner's CallId — must not dial.
        $params = ['To' => '+2348012345678', 'From' => 'client:naara_user_'.$attacker->id, 'CallId' => (string) $call->id];

        $this->withHeaders(['X-Twilio-Signature' => $this->sign($url, $params)])->post($url, $params)
            ->assertOk()
            ->assertSee('<Reject', false);
        $this->assertSame('connecting', $call->fresh()->status);
    }

    public function test_verified_status_webhook_settles_and_refunds(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 1.40, 'USD', ['reference' => 'seed']);
        // Simulate the upfront hold already taken by begin().
        app(WalletService::class)->debit($user, 1.40, 'USD', ['reference' => 'voice-hold:'.$user->id.':x']);
        $call = $this->connectingCall($user);

        $url = route('webhooks.twilio.dial-status').'?call='.$call->id;
        $params = ['DialCallStatus' => 'completed', 'DialCallDuration' => '150', 'CallSid' => 'CA_dialer'];

        $this->withHeaders(['X-Twilio-Signature' => $this->sign($url, $params)])->post($url, $params)
            ->assertOk();

        $call->refresh();
        $this->assertSame(3, $call->minutes_billed);           // ceil(150/60)
        $this->assertSame('0.9800', (string) $call->refunded); // 1.40 - 0.42
        $this->assertSame('0.9800', (string) $user->wallet->fresh()->usd_balance);
    }

    public function test_status_webhook_rejects_an_invalid_signature(): void
    {
        $user = User::factory()->create();
        $call = $this->connectingCall($user);

        $url = route('webhooks.twilio.dial-status').'?call='.$call->id;
        $params = ['DialCallStatus' => 'completed', 'DialCallDuration' => '150'];

        $this->withHeaders(['X-Twilio-Signature' => 'wrong'])->post($url, $params)->assertStatus(403);
        $this->assertNull($call->fresh()->settled_at);
    }
}
