<?php

namespace Tests\Feature;

use App\Livewire\Wizard;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WizardSession;
use App\Services\AI\AnthropicClient;
use App\Services\Pricing\PricingEngine;
use App\Services\SMS\OtpStatus;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeAnthropicClient;
use Tests\Support\FakePermanentProvider;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

/**
 * The NaaraSim Wizard core (roadmap §3) — a buttons-only state machine over the
 * Model registry + real routers. These lock the money-safety + supplier-masking
 * invariants of the guided flow (no LLM involved).
 */
class WizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        Queue::fake(); // PollSmsOtpJob is dispatched, not run
    }

    private function configureOtpLane(): void
    {
        config(['services.fivesim.api_key' => 'test-key']);
        \App\Support\ProviderKeys::flush();
    }

    private function configurePermanentLane(): void
    {
        config(['services.twilio.account_sid' => 'AC', 'services.twilio.auth_token' => 'tok']);
        \App\Support\ProviderKeys::flush();
    }

    private function configureConnectLane(): void
    {
        // Zendit is the primary Naara Connect (voice+data eSIM) provider.
        config(['services.zendit.api_key' => 'test-key']);
        \App\Support\ProviderKeys::flush();
    }

    public function test_naara_connect_is_routed_through_the_esim_path_not_the_number_router(): void
    {
        // Regression for BUILD-6 §A.2: once a voice-lane key is configured,
        // naara_connect appears as a purpose. Selecting it once led into the OTP
        // 'service' step, which called quote()/SmsNumberRouter with an undefined
        // MODEL_TYPE and threw "Unknown number type []". It must go to the eSIM
        // device-check step instead and never reach the number router.
        $this->configureConnectLane();
        $user = User::factory()->create();
        $country = array_key_first(\App\Support\NumberCatalogue::countries());

        $comp = Livewire::actingAs($user)->test(Wizard::class);
        $this->assertContains('naara_connect', collect($comp->instance()->purposes())->pluck('key')->all());

        $comp->call('choosePurpose', 'naara_connect')
            ->assertSet('model', 'naara_connect')
            ->assertSet('step', 'country')
            ->call('chooseCountry', $country)
            ->assertSet('step', 'device')   // eSIM path, NOT 'service'
            ->assertSet('error', null)
            ->assertHasNoErrors();
    }

    public function test_naara_connect_hands_off_to_the_full_esim_tab(): void
    {
        $this->configureConnectLane();
        $user = User::factory()->create();
        $countries = \App\Support\NumberCatalogue::countries();
        $country = array_key_first($countries);

        Livewire::actingAs($user)->test(Wizard::class)
            ->set('model', 'naara_connect')
            ->set('country', $country)
            ->set('step', 'device')
            ->call('goToEsims')
            ->assertRedirect(route('catalogue', ['q' => $countries[$country], 'tab' => 'full']));
    }

    public function test_only_available_models_appear_as_purposes(): void
    {
        // No provider keys at all → no purposes offered (nothing to sell).
        config(['services.fivesim.api_key' => null, 'services.esimgo.api_key' => null,
                'services.getatext.api_key' => null, 'services.herosms.api_key' => null, 'services.virtsms.api_key' => null,
                'services.twilio.account_sid' => null, 'services.telnyx.api_key' => null,
                'services.airalo.client_id' => null, 'services.quibity.api_key' => null]);
        \App\Support\ProviderKeys::flush();

        $comp = Livewire::actingAs(User::factory()->create())->test(Wizard::class);
        $this->assertCount(0, $comp->instance()->purposes());

        // Configure the OTP/rental lane → Verify + Rent appear, Line does not.
        $this->configureOtpLane();
        $comp = Livewire::actingAs(User::factory()->create())->test(Wizard::class);
        $keys = collect($comp->instance()->purposes())->pluck('key')->all();
        $this->assertContains('naara_verify', $keys);
        $this->assertContains('naara_rent', $keys);
        $this->assertNotContains('naara_line', $keys);
    }

    public function test_otp_flow_charges_reserves_and_never_exposes_the_supplier(): void
    {
        $this->configureOtpLane();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20, buyResponse: [
            'provider_ref' => '5S-1', 'number' => '+2348010000000', 'cost' => 0.20, 'status' => OtpStatus::PENDING,
        ]));

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        $comp = Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_verify')
            ->assertSet('step', 'country')
            ->call('chooseCountry', 'nigeria')
            ->assertSet('step', 'service')
            ->call('chooseService', 'whatsapp')
            ->assertSet('step', 'review')
            ->call('purchase')
            ->assertSet('step', 'otp'); // OTP surfaces on the live-code channel

        // A waiting order exists and belongs to the user.
        $order = SmsOrder::where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('waiting', $order->status);
        Queue::assertPushed(\App\Jobs\PollSmsOtpJob::class);

        // The wallet was charged retail (> cost).
        $this->assertLessThan(20.0, (float) $user->wallet->fresh()->usd_balance);

        // Supplier masking: the real provider is never rendered nor held in any
        // public (dehydrated) property that reaches the browser.
        $comp->assertDontSee('fivesim')->assertDontSee('5sim');
        $props = $comp->instance()->all();
        $this->assertStringNotContainsString('fivesim', strtolower(json_encode($props)));
    }

    public function test_insufficient_balance_routes_to_topup_without_charging(): void
    {
        $this->configureOtpLane();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20));
        $user = User::factory()->create(); // no funds

        Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_verify')
            ->call('chooseCountry', 'nigeria')
            ->call('chooseService', 'whatsapp')
            ->call('purchase')
            ->assertSet('step', 'topup');

        $this->assertSame(0, SmsOrder::count());
        // No debit transaction was written (the user was never charged).
        $this->assertSame(0, $user->walletTransactions()->count());
    }

    public function test_permanent_flow_provisions_a_number_via_the_wizard(): void
    {
        $this->configurePermanentLane();
        app()->instance('number.twilio', new FakePermanentProvider(cost: 1.00, results: [
            ['number' => '+15550001234', 'locality' => 'New York'],
        ]));

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_line')
            ->call('chooseCountry', 'usa')
            ->assertSet('step', 'match')      // Naara Line offers number matching first
            ->call('showAnyNumber')
            ->assertSet('step', 'pick')
            ->call('provisionPermanent', '+15550001234')
            ->assertSet('step', 'result');

        $vn = VirtualNumber::where('user_id', $user->id)->first();
        $this->assertNotNull($vn);
        $this->assertSame('active', $vn->status);
        $this->assertSame('+15550001234', $vn->phone_number);
        $this->assertLessThan(20.0, (float) $user->wallet->fresh()->usd_balance);
    }

    public function test_number_matching_filters_candidates_by_the_typed_pattern(): void
    {
        $this->configurePermanentLane();
        app()->instance('number.twilio', new FakePermanentProvider(cost: 1.00, results: [
            ['number' => '+15550001234', 'locality' => 'NY'],
            ['number' => '+15559991234', 'locality' => 'CA'],
            ['number' => '+15558887777', 'locality' => 'TX'],
        ]));
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        $comp = Livewire::actingAs($user)->test(Wizard::class)
            ->set('open', true)
            ->call('choosePurpose', 'naara_line')
            ->call('chooseCountry', 'usa')
            ->set('matchDigits', '1234')
            ->set('matchPosition', 'ends')
            ->call('findNumbers')
            ->assertSet('step', 'pick')
            ->assertSee('+15550001234')
            ->assertSee('+15559991234')
            ->assertDontSee('+15558887777'); // doesn't end in 1234

        // Only the two matching numbers survived the server-side filter.
        $this->assertCount(2, $comp->get('candidates'));
    }

    public function test_number_matching_with_no_hit_offers_a_fallback(): void
    {
        $this->configurePermanentLane();
        app()->instance('number.twilio', new FakePermanentProvider(cost: 1.00, results: [
            ['number' => '+15558887777', 'locality' => 'TX'],
        ]));
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->set('open', true)
            ->call('choosePurpose', 'naara_line')
            ->call('chooseCountry', 'usa')
            ->set('matchDigits', '1234')
            ->call('findNumbers')
            ->assertSet('step', 'pick')
            ->assertSet('candidates', [])
            ->assertSee('No number matched that pattern')
            ->assertSee('Show any number');
    }

    public function test_empty_pattern_is_rejected_before_searching(): void
    {
        $this->configurePermanentLane();
        app()->instance('number.twilio', new FakePermanentProvider());
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->set('open', true)
            ->call('choosePurpose', 'naara_line')
            ->call('chooseCountry', 'usa')
            ->call('findNumbers')          // no digits typed
            ->assertSet('step', 'match')   // stays on the match step
            ->assertSee('Type a few digits');
    }

    public function test_esim_purpose_guides_to_the_catalogue(): void
    {
        config(['services.esimgo.api_key' => 'test-key']);
        \App\Support\ProviderKeys::flush();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_data')
            ->call('chooseCountry', 'nigeria')
            ->assertSet('step', 'device')
            ->call('goToEsims')
            ->assertRedirect(route('catalogue', ['q' => 'Nigeria']));
    }

    public function test_progress_is_saved_and_restored_across_remounts(): void
    {
        $this->configureOtpLane();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->call('choosePurpose', 'naara_verify')
            ->call('chooseCountry', 'ghana')
            ->assertSet('step', 'service');

        // A fresh mount (e.g. after navigating to top up) rehydrates the state.
        $this->assertDatabaseHas('wizard_sessions', ['user_id' => $user->id, 'step' => 'service']);
        Livewire::actingAs($user)->test(Wizard::class)
            ->assertSet('step', 'service')
            ->assertSet('model', 'naara_verify')
            ->assertSet('country', 'ghana');

        // Completing a purchase clears the saved session.
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20, buyResponse: [
            'provider_ref' => '5S-9', 'number' => '+233200000000', 'cost' => 0.20, 'status' => OtpStatus::PENDING,
        ]));
        app(WalletService::class)->credit($user, 20, 'USD');
        Livewire::actingAs($user)->test(Wizard::class)
            ->call('chooseService', 'whatsapp')
            ->call('purchase')
            ->assertSet('step', 'otp');
        $this->assertDatabaseMissing('wizard_sessions', ['user_id' => $user->id]);
    }

    // ---- OTP push-to-widget (roadmap §3.10) --------------------------------

    private function otpOrder(User $u, array $extra = []): SmsOrder
    {
        return SmsOrder::create(array_merge([
            'user_id' => $u->id, 'provider' => 'fivesim', 'service_name' => 'whatsapp',
            'type' => 'otp', 'phone_number' => '+15550001111', 'status' => 'completed',
            'otp_code' => '123456', 'provider_cost' => 0.20, 'charged_to_user' => 0.50, 'profit' => 0.30,
        ], $extra));
    }

    public function test_a_completed_otp_from_anywhere_surfaces_in_the_widget(): void
    {
        $user = User::factory()->create();
        $order = $this->otpOrder($user); // e.g. bought on the dedicated numbers page

        $comp = Livewire::actingAs($user)->test(Wizard::class);
        // The launcher advertises the ready code, and the code is one-tap copyable.
        $comp->assertSee('Your code is ready')
            ->call('openOtp')
            ->assertSet('step', 'otp')
            ->assertSee('123456')
            ->assertSee('Tap the code to copy');

        // The supplier is still never rendered.
        $comp->assertDontSee('fivesim');
        $this->assertSame($order->id, $comp->instance()->liveOtp()->id);
    }

    public function test_dismissing_an_otp_hides_it_and_anything_older(): void
    {
        $user = User::factory()->create();
        $this->otpOrder($user); // older
        $newer = $this->otpOrder($user, ['otp_code' => '999000']);

        $comp = Livewire::actingAs($user)->test(Wizard::class)
            ->call('openOtp')
            ->assertSee('999000')
            ->call('dismissOtp')
            ->assertSet('step', 'purpose');

        // Neither the dismissed code nor the older one resurfaces.
        $this->assertNull($comp->instance()->liveOtp());
        $comp->assertDontSee('Your code is ready');
    }

    public function test_only_the_owning_user_sees_their_otp(): void
    {
        $mine = User::factory()->create();
        $other = User::factory()->create();
        $this->otpOrder($other, ['otp_code' => '777777']);

        $comp = Livewire::actingAs($mine)->test(Wizard::class);
        $this->assertNull($comp->instance()->liveOtp());
        $comp->assertDontSee('777777');
    }

    public function test_a_stale_otp_older_than_the_window_does_not_surface(): void
    {
        $user = User::factory()->create();
        // created_at isn't mass-assignable — force it so the order is genuinely stale.
        $this->otpOrder($user)->forceFill(['created_at' => now()->subHour()])->save();

        $comp = Livewire::actingAs($user)->test(Wizard::class);
        $this->assertNull($comp->instance()->liveOtp());
    }

    // ---- Claude NLU sprinkle (roadmap §8) ----------------------------------

    private function fakeAi(array $json, bool $on = true): void
    {
        $this->app->instance(AnthropicClient::class, new FakeAnthropicClient(json: $json, on: $on));
    }

    public function test_free_text_maps_to_fixed_options_and_advances_to_a_quote(): void
    {
        $this->configureOtpLane();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20));
        $this->fakeAi(['model' => 'naara_verify', 'country' => 'nigeria', 'service' => 'whatsapp']);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->assertSet('nluOn', true)
            ->set('freeText', 'I need a whatsapp code for Nigeria')
            ->call('interpret')
            ->assertSet('model', 'naara_verify')
            ->assertSet('country', 'nigeria')
            ->assertSet('service', 'whatsapp')
            ->assertSet('step', 'review');

        // The NLU path never buys — a quote is a read, not a charge.
        $this->assertSame(0, SmsOrder::count());
        $this->assertSame(0, $user->walletTransactions()->count());
    }

    public function test_free_text_helper_is_hidden_and_inert_when_claude_is_off(): void
    {
        $this->configureOtpLane();
        $this->fakeAi([], on: false); // no Anthropic key
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->set('open', true)
            ->assertSet('nluOn', false)
            ->assertDontSee('Tell me in your words')
            ->set('freeText', 'permanent number for the US')
            ->call('interpret')
            ->assertSet('step', 'purpose'); // buttons still the only path
    }

    public function test_claude_can_only_pick_from_whitelisted_options(): void
    {
        // Only the OTP/rental lane is live → Naara Line is NOT available. Even if
        // the model names it (or an off-list country), those are dropped.
        $this->configureOtpLane();
        $this->fakeAi(['model' => 'naara_line', 'country' => 'atlantis', 'service' => 'whatsapp']);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->set('open', true)
            ->set('freeText', 'I want a permanent number in Atlantis')
            ->call('interpret')
            ->assertSet('model', null)      // naara_line dropped (not available)
            ->assertSet('step', 'purpose')
            ->assertSee('didn’t quite catch');
    }

    public function test_a_model_without_a_country_advances_to_the_country_step(): void
    {
        $this->configureOtpLane();
        $this->fakeAi(['model' => 'naara_verify', 'country' => '', 'service' => '']);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wizard::class)
            ->set('freeText', 'get me an otp')
            ->call('interpret')
            ->assertSet('model', 'naara_verify')
            ->assertSet('step', 'country');
    }

    // ---- Wizard convenience fee (roadmap §6) -------------------------------

    private function otpRetail(): float
    {
        return round(app(PricingEngine::class)->calculateSmsRetail(0.20, 'fivesim'), 4);
    }

    private function usedWizard(int $times): User
    {
        $u = User::factory()->create();
        $u->forceFill(['wizard_uses' => $times])->save();

        return $u;
    }

    public function test_the_first_sessions_are_free_then_the_fee_applies(): void
    {
        $this->configureOtpLane();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20, buyResponse: [
            'provider_ref' => '5S-1', 'number' => '+2348010000000', 'cost' => 0.20, 'status' => OtpStatus::PENDING,
        ]));
        $retail = $this->otpRetail();

        // Within the free allowance (2 of 3 used) → no fee, counter advances.
        $free = $this->usedWizard(2);
        app(WalletService::class)->credit($free, 20, 'USD');
        Livewire::actingAs($free)->test(Wizard::class)
            ->call('choosePurpose', 'naara_verify')->call('chooseCountry', 'nigeria')
            ->call('chooseService', 'whatsapp')->call('purchase')->assertSet('step', 'otp');

        $this->assertSame(number_format(20 - $retail, 4, '.', ''), (string) $free->wallet->fresh()->usd_balance);
        $this->assertSame(3, (int) $free->fresh()->wizard_uses);
        $this->assertSame(0, $free->walletTransactions()->where('description', 'Wizard assist fee')->count());
    }

    public function test_the_fee_is_charged_on_top_once_the_allowance_is_spent(): void
    {
        $this->configureOtpLane();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20, buyResponse: [
            'provider_ref' => '5S-2', 'number' => '+2348010000000', 'cost' => 0.20, 'status' => OtpStatus::PENDING,
        ]));
        $retail = $this->otpRetail();
        $fee = 0.45;

        $paid = $this->usedWizard(3); // allowance spent → fee applies
        app(WalletService::class)->credit($paid, 20, 'USD');
        Livewire::actingAs($paid)->test(Wizard::class)
            ->set('open', true)
            ->call('choosePurpose', 'naara_verify')->call('chooseCountry', 'nigeria')
            ->call('chooseService', 'whatsapp')
            ->assertSee('Wizard help')            // shown up front, never hidden
            ->assertSee('0.45')
            ->call('purchase')->assertSet('step', 'otp');

        // Retail AND the fee were charged; the counter advanced.
        $this->assertSame(number_format(20 - $retail - $fee, 4, '.', ''), (string) $paid->wallet->fresh()->usd_balance);
        $this->assertSame(4, (int) $paid->fresh()->wizard_uses);
        $this->assertSame(1, $paid->walletTransactions()->where('description', 'Wizard assist fee')->count());
    }

    public function test_a_wallet_that_covers_retail_but_not_the_fee_charges_nothing(): void
    {
        $this->configureOtpLane();
        app()->instance('number.fivesim', new FakeSmsProvider(price: 0.20, buyResponse: [
            'provider_ref' => '5S-3', 'number' => '+2348010000000', 'cost' => 0.20, 'status' => OtpStatus::PENDING,
        ]));
        $retail = $this->otpRetail();

        $paid = $this->usedWizard(3);
        // Enough for the number, but not the extra $0.45 fee.
        app(WalletService::class)->credit($paid, $retail + 0.10, 'USD');

        Livewire::actingAs($paid)->test(Wizard::class)
            ->call('choosePurpose', 'naara_verify')->call('chooseCountry', 'nigeria')
            ->call('chooseService', 'whatsapp')->call('purchase')
            ->assertSet('step', 'topup');

        // The retail debit was rolled back — the balance is whole again, no order.
        $this->assertSame(number_format($retail + 0.10, 4, '.', ''), (string) $paid->wallet->fresh()->usd_balance);
        $this->assertSame(0, SmsOrder::count());
        $this->assertSame(3, (int) $paid->fresh()->wizard_uses); // not counted
    }

    public function test_the_permanent_flow_also_charges_the_fee_and_counts_the_use(): void
    {
        $this->configurePermanentLane();
        app()->instance('number.twilio', new FakePermanentProvider(cost: 1.00, results: [
            ['number' => '+15550001234', 'locality' => 'NY'],
        ]));
        $paid = $this->usedWizard(3);
        app(WalletService::class)->credit($paid, 20, 'USD');

        Livewire::actingAs($paid)->test(Wizard::class)
            ->call('choosePurpose', 'naara_line')->call('chooseCountry', 'usa')
            ->call('showAnyNumber')->call('provisionPermanent', '+15550001234')
            ->assertSet('step', 'result');

        $this->assertSame(1, $paid->walletTransactions()->where('description', 'Wizard assist fee')->count());
        $this->assertSame(4, (int) $paid->fresh()->wizard_uses);
        $this->assertSame(1, VirtualNumber::where('user_id', $paid->id)->count());
    }
}
