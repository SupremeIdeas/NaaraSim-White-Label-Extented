<?php

namespace Tests\Feature;

use App\Jobs\SyncVoiceWebhookJob;
use App\Livewire\CallForwarding;
use App\Models\CallForwardingRule;
use App\Models\User;
use App\Models\VirtualNumber;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Live Voice — Part A (call forwarding). Inbound calls to a permanent NaaraSim
 * number forward to the user's real phone via signature-verified TwiML. The
 * whole feature is gated on the existing Twilio provider status.
 */
class CallForwardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);
    }

    private function number(User $user): VirtualNumber
    {
        return VirtualNumber::create([
            'user_id' => $user->id, 'provider' => 'twilio', 'phone_number' => '+15005550006',
            'sid' => 'PN_test', 'status' => 'active', 'monthly_cost' => 1.0, 'monthly_retail' => 3.0,
        ]);
    }

    /** Twilio's X-Twilio-Signature over the URL + sorted POST params. */
    private function sign(string $url, array $params): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }

        return base64_encode(hash_hmac('sha1', $data, 'secret_token', true));
    }

    public function test_a_verified_inbound_call_returns_twiml_that_dials_the_target(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        CallForwardingRule::create([
            'user_id' => $user->id, 'twilio_number' => '+15005550006',
            'forward_to_number' => '+2348012345678', 'status' => 'active',
        ]);

        $url = route('webhooks.twilio.voice');
        $params = ['To' => '+15005550006', 'From' => '+15551112222', 'CallSid' => 'CA1', 'CallStatus' => 'ringing'];

        $res = $this->withHeaders(['X-Twilio-Signature' => $this->sign($url, $params)])
            ->post($url, $params);

        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $res->assertSee('<Dial', false);
        $res->assertSee('<Number>+2348012345678</Number>', false);
        $res->assertSee('callerId="+15551112222"', false); // original caller preserved
        Queue::assertPushed(\App\Jobs\LogCallEventJob::class);
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        $url = route('webhooks.twilio.voice');
        $params = ['To' => '+15005550006', 'From' => '+15551112222'];

        $res = $this->withHeaders(['X-Twilio-Signature' => 'wrong'])->post($url, $params);

        $res->assertStatus(403);
        $res->assertSee('<Reject', false);
    }

    public function test_a_number_with_no_active_rule_is_politely_rejected(): void
    {
        $url = route('webhooks.twilio.voice');
        $params = ['To' => '+15005559999', 'From' => '+15551112222'];

        $this->withHeaders(['X-Twilio-Signature' => $this->sign($url, $params)])
            ->post($url, $params)
            ->assertOk()
            ->assertSee('<Reject', false);
    }

    public function test_the_screen_is_hidden_until_twilio_is_active(): void
    {
        config(['services.twilio.account_sid' => '', 'services.twilio.auth_token' => '']);

        Livewire::actingAs(User::factory()->create())->test(CallForwarding::class)->assertStatus(404);
    }

    public function test_a_user_can_set_forwarding_and_the_voice_url_is_synced(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $number = $this->number($user);

        Livewire::actingAs($user)->test(CallForwarding::class)
            ->call('edit', $number->id)
            ->set('forwardTo', '+2348012345678')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('call_forwarding_rules', [
            'twilio_number' => '+15005550006', 'forward_to_number' => '+2348012345678', 'status' => 'active',
        ]);
        Queue::assertPushed(SyncVoiceWebhookJob::class, fn ($job) => $job->attach === true && $job->numberSid === 'PN_test');
    }

    public function test_an_invalid_forward_number_is_rejected(): void
    {
        $user = User::factory()->create();
        $number = $this->number($user);

        Livewire::actingAs($user)->test(CallForwarding::class)
            ->call('edit', $number->id)
            ->set('forwardTo', '08012345678') // not E.164
            ->call('save')
            ->assertHasErrors('forwardTo');
    }

    public function test_disabling_forwarding_detaches_the_voice_url(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $number = $this->number($user);
        $rule = CallForwardingRule::create([
            'user_id' => $user->id, 'virtual_number_id' => $number->id, 'twilio_number' => '+15005550006',
            'forward_to_number' => '+2348012345678', 'status' => 'active',
        ]);

        Livewire::actingAs($user)->test(CallForwarding::class)->call('disable', $rule->id);

        $this->assertSame('inactive', $rule->fresh()->status);
        Queue::assertPushed(SyncVoiceWebhookJob::class, fn ($job) => $job->attach === false);
    }

    public function test_edit_does_not_disclose_another_users_forwarding_numbers(): void
    {
        // IDOR: numberId arrives from the client. edit() must not populate the
        // form with a victim's private forward-to / fallback numbers.
        $victim = User::factory()->create();
        $victimNumber = $this->number($victim);
        CallForwardingRule::create([
            'user_id' => $victim->id, 'virtual_number_id' => $victimNumber->id,
            'twilio_number' => '+15005550006', 'forward_to_number' => '+2348055556666',
            'fallback_number' => '+2348077778888', 'status' => 'active',
        ]);

        $attacker = User::factory()->create();

        Livewire::actingAs($attacker)->test(CallForwarding::class)
            ->call('edit', $victimNumber->id)
            ->assertSet('forwardTo', '')
            ->assertSet('fallback', '');
    }
}
