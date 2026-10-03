<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Livewire\Admin\PayoutHealth;
use App\Livewire\Admin\PayoutSettingsPage;
use App\Models\CreditLedger;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\PayoutUserFreeze;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payouts\Hardening\PayoutEnvGuard;
use App\Services\Payouts\Hardening\PayoutFreeze;
use App\Services\Payouts\PayoutService;
use App\Support\PayoutSettings;
use App\Support\PayoutSettingsSchema;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** The "easy, not hard-coded" admin surface: schema-driven settings + the operations/health page. */
class PayoutSettingsAndHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Queue::fake();
    }

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function sa(): User
    {
        return $this->user('super_admin');
    }

    // ── settings schema ──

    public function test_every_schema_default_matches_what_the_code_uses_when_nothing_is_stored(): void
    {
        $accessors = [
            PayoutSettings::FLAG => fn () => PayoutSettings::enabled(),
            PayoutSettings::MODE => fn () => PayoutSettings::mode(),
            PayoutSettings::MIN => fn () => PayoutSettings::minWithdrawal(),
            PayoutSettings::FREE_COUNT => fn () => PayoutSettings::freePayoutCount(),
            PayoutSettings::MAX_OPEN => fn () => PayoutSettings::maxOpenRequests(),
            PayoutSettings::QUOTE_HOURS => fn () => PayoutSettings::quoteLockHours(),
            PayoutSettings::MANUAL_EXTERNAL => fn () => PayoutSettings::manualExternalEnabled(),
            PayoutSettings::STRIPE_GLOBAL => fn () => PayoutSettings::stripeGlobalPayouts(),
            PayoutSettings::AUTO_APPROVAL => fn () => PayoutSettings::autoApprovalEnabled(),
            PayoutSettings::AUTO_SHADOW => fn () => PayoutSettings::shadowMode(),
            PayoutSettings::TIER_LIMIT_PREFIX.'new' => fn () => PayoutSettings::tierLimitUsd('new'),
            PayoutSettings::TIER_LIMIT_PREFIX.'trusted' => fn () => PayoutSettings::tierLimitUsd('trusted'),
            PayoutSettings::TIER_LIMIT_PREFIX.'vip' => fn () => PayoutSettings::tierLimitUsd('vip'),
            PayoutSettings::DAILY_CAP_USD => fn () => PayoutSettings::dailyAutoCapUsd(),
            PayoutSettings::BREAKER_HOURLY_FLOOR => fn () => PayoutSettings::breakerHourlyFloor(),
            PayoutSettings::BAND_APPROVE => fn () => PayoutSettings::bandApprove(),
            PayoutSettings::BAND_HOLD => fn () => PayoutSettings::bandHold(),
            PayoutSettings::QA_SAMPLE_PCT => fn () => PayoutSettings::qaSamplePct(),
            PayoutSettings::FAIL_CLOSED_HEARTBEAT => fn () => PayoutSettings::failClosedOnStaleHeartbeat(),
            PayoutSettings::COOLING_OFF_HOURS => fn () => PayoutSettings::coolingOffHours(),
            PayoutSettings::MATURITY_DAYS => fn () => PayoutSettings::maturityDays(),
            PayoutSettings::NEW_ACCOUNT_DAYS => fn () => PayoutSettings::newAccountDays(),
            PayoutSettings::NAME_MATCH => fn () => PayoutSettings::nameMatchThreshold(),
            PayoutSettings::LARGEST_MULT => fn () => PayoutSettings::largestPreviousMultiple(),
            PayoutSettings::USER_CAP_PREFIX.'daily' => fn () => PayoutSettings::userCapUsd('daily'),
            PayoutSettings::USER_CAP_PREFIX.'weekly' => fn () => PayoutSettings::userCapUsd('weekly'),
            PayoutSettings::USER_CAP_PREFIX.'monthly' => fn () => PayoutSettings::userCapUsd('monthly'),
            PayoutSettings::STEP_UP_REQUIRED => fn () => PayoutSettings::stepUpRequired(),
            PayoutSettings::STEP_UP_MINUTES => fn () => PayoutSettings::stepUpValidMinutes(),
            PayoutSettings::SEND_DELAY_GLOBAL => fn () => PayoutSettings::sendDelayMinutes(true),
            PayoutSettings::SEND_DELAY_LOCAL => fn () => PayoutSettings::sendDelayMinutes(false),
            PayoutSettings::NOTIFY_ON_REQUEST => fn () => PayoutSettings::notifyOnRequest(),
            PayoutSettings::DUAL_CONTROL_USD => fn () => PayoutSettings::dualControlUsd(),
            PayoutSettings::FX_TOLERANCE_PCT => fn () => PayoutSettings::fxTolerancePct(),
            PayoutSettings::FX_BAND => fn () => PayoutSettings::fxSanityBandPct(),
            PayoutSettings::MAX_FEE_RATIO => fn () => PayoutSettings::maxFeeRatio() * 100,
            PayoutSettings::TAX_FORM_OVER_USD => fn () => PayoutSettings::taxFormOverUsd(),
            PayoutSettings::LOOKUP_GRACE_MIN => fn () => PayoutSettings::lookupGraceMinutes(),
            PayoutSettings::OUTAGE_ALERT_MINUTES => fn () => PayoutSettings::outageAlertMinutes(),
            PayoutSettings::HTTP_TIMEOUT => fn () => PayoutSettings::httpTimeout(),
            PayoutSettings::HTTP_CONNECT_TIMEOUT => fn () => PayoutSettings::httpConnectTimeout(),
            PayoutSettings::WEBHOOK_PAYLOAD_DAYS => fn () => PayoutSettings::webhookPayloadRetentionDays(),
            PayoutSettings::ADD_ACCOUNT_PER_HOUR => fn () => PayoutSettings::addAccountPerHour(),
            PayoutSettings::WITHDRAW_PER_MINUTE => fn () => PayoutSettings::withdrawPerMinute(),
            PayoutSettings::WITHDRAW_PER_DAY => fn () => PayoutSettings::withdrawPerDay(),
            PayoutSettings::PEER_ENABLED => fn () => PayoutSettings::peerEnabled() ? 1 : 0,
            PayoutSettings::PEER_ONLY_UNSUPPORTED => fn () => PayoutSettings::peerOnlyWhenUnsupported() ? 1 : 0,
            PayoutSettings::PEER_MIN_USD => fn () => PayoutSettings::peerMinUsd(),
            PayoutSettings::PEER_MAX_USD => fn () => PayoutSettings::peerMaxUsd(),
            PayoutSettings::PEER_SENDER_30D => fn () => PayoutSettings::peerSender30dUsd(),
            PayoutSettings::PEER_RECIPIENT_30D => fn () => PayoutSettings::peerRecipient30dUsd(),
            PayoutSettings::PEER_EXPIRY_HOURS => fn () => PayoutSettings::peerExpiryHours(),
            PayoutSettings::PEER_RECIPIENT_KYC => fn () => PayoutSettings::peerRecipientKyc(),
            PayoutSettings::PEER_SENDER_AGE_DAYS => fn () => PayoutSettings::peerSenderAgeDays(),
            PayoutSettings::PEER_MAX_PENDING => fn () => PayoutSettings::peerMaxPending(),
            PayoutSettings::PEER_REVIEW_RECEIVED => fn () => PayoutSettings::peerReviewReceived() ? 1 : 0,
        ];

        foreach (PayoutSettingsSchema::flat() as $key => $field) {
            if (in_array($key, [PayoutSettings::DENIED_COUNTRIES, 'payouts.currency_decimals', PayoutSettings::DISABLED_EXTENSIONS], true)) {
                continue; // free-text fields: default ''/'{}' by construction
            }
            $this->assertArrayHasKey($key, $accessors, "{$key} has a schema entry but no accessor comparison");
            $this->assertEqualsWithDelta((float) $accessors[$key](), (float) $field['default'], 0.0001, "{$key}: the schema default must equal the code default");
            $this->assertFalse(PayoutSettingsSchema::isCustom($field), "{$key} must not read as changed when nothing is stored");
        }
    }

    public function test_the_settings_page_is_super_admin_only_and_lists_every_group(): void
    {
        Livewire::actingAs($this->user('admin'))->test(PayoutSettingsPage::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(PayoutSettingsPage::class)->assertForbidden();

        $c = Livewire::actingAs($this->sa())->test(PayoutSettingsPage::class)
            ->assertSee('Basics')->assertSee('Auto-approval (Guardian)')->assertSee('Fraud &amp; safety rules', false)
            ->assertSee('Money &amp; exchange rates', false)->assertSee('Operations &amp; timing', false)
            ->assertSee('Default:');
        $c->set('search', 'timeout')->assertSee('Provider call timeout')->assertDontSee('Minimum withdrawal');
    }

    public function test_saving_validates_audits_and_tells_the_other_super_admins(): void
    {
        $slot = fn ($k) => 'values.'.PayoutSettingsPage::slot($k);
        $c = Livewire::actingAs($sa = $this->sa())->test(PayoutSettingsPage::class)
            ->set($slot(PayoutSettings::MIN), 12.5)
            ->set($slot(PayoutSettings::MAX_OPEN), 5)
            ->call('save')->assertHasNoErrors();

        $this->assertSame(12.5, PayoutSettings::minWithdrawal());
        $this->assertSame(5, PayoutSettings::maxOpenRequests());
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'payout_settings_changed' && str_contains($j->message, 'payouts.min_withdrawal_usd'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'payout.settings_changed']);
        $c->assertSee('Changed from default');
    }

    public function test_out_of_range_and_inconsistent_values_are_refused_and_nothing_is_saved(): void
    {
        $slot = fn ($k) => 'values.'.PayoutSettingsPage::slot($k);
        Livewire::actingAs($this->sa())->test(PayoutSettingsPage::class)
            ->set($slot(PayoutSettings::HTTP_TIMEOUT), 500)          // would outlive the worker
            ->set($slot(PayoutSettings::BAND_APPROVE), 80)
            ->set($slot(PayoutSettings::BAND_HOLD), 40)              // hold band below approve band
            ->set($slot(PayoutSettings::DENIED_COUNTRIES), 'XXX,12')
            ->call('save')
            ->assertHasErrors([$slot(PayoutSettings::HTTP_TIMEOUT), $slot(PayoutSettings::BAND_HOLD), $slot(PayoutSettings::DENIED_COUNTRIES)]);

        $this->assertSame(25, PayoutSettings::httpTimeout());
        $this->assertSame(30, PayoutSettings::bandApprove());
    }

    public function test_switches_that_change_how_money_moves_need_an_explicit_tick_and_reset_restores_the_default(): void
    {
        $slot = fn ($k) => 'values.'.PayoutSettingsPage::slot($k);
        $c = Livewire::actingAs($this->sa())->test(PayoutSettingsPage::class)
            ->set($slot(PayoutSettings::AUTO_SHADOW), true)          // switch to learning mode: money flow changes
            ->call('save')->assertHasErrors('confirmed');
        $this->assertFalse(PayoutSettings::shadowMode(), 'not saved without the tick');

        $c->set('confirmed', true)->call('save')->assertHasNoErrors();
        $this->assertTrue(PayoutSettings::shadowMode());

        $c->call('resetField', PayoutSettingsPage::slot(PayoutSettings::AUTO_SHADOW));
        $this->assertFalse(PayoutSettings::shadowMode(), 'reset puts back the default (automatic)');
        $this->assertNull(Setting::where('key', PayoutSettings::AUTO_SHADOW)->first(), 'the stored override is removed, so future default changes apply');
    }

    public function test_country_list_and_currency_json_are_normalised_on_save(): void
    {
        $slot = fn ($k) => 'values.'.PayoutSettingsPage::slot($k);
        Livewire::actingAs($this->sa())->test(PayoutSettingsPage::class)
            ->set($slot(PayoutSettings::DENIED_COUNTRIES), ' kp , ir ')
            ->set($slot('payouts.currency_decimals'), '{ "ngn" : 0 }')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(['KP', 'IR'], PayoutSettings::deniedCountries());
        $this->assertSame(0, \App\Support\PayoutMoney::decimals('NGN') === 0 ? 0 : 99, 'JSON stored; lookup is case-normalised by the reader');
    }

    // ── health page ──

    public function test_the_health_page_needs_a_payout_scope_and_shows_the_latest_money_check(): void
    {
        Livewire::actingAs(User::factory()->create())->test(PayoutHealth::class)->assertForbidden();
        Livewire::actingAs($this->user('staff'))->test(PayoutHealth::class)->assertForbidden();

        $c = Livewire::actingAs($this->user('admin'))->test(PayoutHealth::class)->assertSee('No run yet')
            ->call('runInvariants')->assertSee('All checks passed');
        $c->assertSee('Every paid payout has exactly one consumed hold');
    }

    private function heldRequest(string $provider = 'paystack'): PayoutRequest
    {
        config(['services.paystack.secret_key' => 'sk_test']);
        $u = User::factory()->create();
        $acct = PayoutAccount::create(['user_id' => $u->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'T U', 'provider' => $provider, 'is_verified' => true, 'is_default' => true]);
        $ref = 'wd:'.uniqid();
        CreditLedger::create(['user_id' => $u->id, 'type' => 'spend', 'source' => 'withdraw', 'withdrawable' => true, 'amount' => 5, 'balance_after' => 0, 'reference' => 'wd-hold:'.$ref]);
        $r = app(PayoutService::class)->createRequest($u, 5000, 'NGN', 'referral_credits', $acct, $ref);
        $r->forceFill(['credit_amount' => 5])->save();

        return $r->fresh();
    }

    public function test_a_changed_destination_is_listed_and_can_be_accepted_or_declined_only_with_a_note(): void
    {
        $r = $this->heldRequest();
        $r->account->forceFill(['account_number' => '9999999999'])->save();
        app(PayoutService::class)->send($r);

        $c = Livewire::actingAs($this->user('admin'))->test(PayoutHealth::class)
            ->assertSee('Not sent: the payout account was changed')
            ->call('acceptDestination', $r->id)->assertSee('add a note first');
        $this->assertSame('account_changed_after_request', $r->fresh()->hold_reason);

        $c->set("notes.{$r->id}", 'Called user, confirmed')->call('acceptDestination', $r->id)->assertSee('back in the review queue');
        $this->assertNull($r->fresh()->hold_reason);
    }

    public function test_manual_payouts_are_settled_from_the_page_with_proof(): void
    {
        Setting::setValue(PayoutSettings::MANUAL_EXTERNAL, true);
        $r = $this->heldRequest('manual_external');
        app(PayoutService::class)->send($r);

        Livewire::actingAs($this->user('admin'))->test(PayoutHealth::class)
            ->assertSee('Pay by hand')
            ->set("notes.{$r->id}", 'Paid from GTB')->call('recordManual', $r->id)->assertSee('Proof reference')   // no proof → refused
            ->set("proofs.{$r->id}", 'TRF-99')->call('recordManual', $r->id)->assertSee('marked delivered');

        $this->assertSame(PayoutRequest::PAID, $r->fresh()->status);
    }

    public function test_mark_returned_from_the_page_re_credits_and_only_works_on_paid(): void
    {
        $r = $this->heldRequest();
        $c = Livewire::actingAs($this->user('admin'))->test(PayoutHealth::class)
            ->set('returnedId', (string) $r->id)->set('returnedEvidence', 'Bank notice 7')->call('markReturned')
            ->assertSee('Only a payout that is marked delivered');
        $r->forceFill(['status' => PayoutRequest::PAID])->save();

        $c->call('markReturned')->assertSee('Marked returned');
        $this->assertSame(PayoutRequest::RETURNED, $r->fresh()->status);
    }

    public function test_only_a_super_admin_can_clear_a_paused_payee_and_a_note_is_required(): void
    {
        $victim = User::factory()->create();
        app(PayoutFreeze::class)->freeze($victim);

        Livewire::actingAs($this->user('admin'))->test(PayoutHealth::class)->assertSee('Paused payees')->call('releaseFreeze', $victim->id)->assertForbidden();

        $c = Livewire::actingAs($this->sa())->test(PayoutHealth::class)->call('releaseFreeze', $victim->id)->assertSee('Add a note');
        $this->assertTrue(PayoutFreeze::isFrozen($victim->id));
        $c->set('releaseNote', 'Verified by phone')->call('releaseFreeze', $victim->id)->assertSee('re-enabled');
        $this->assertFalse(PayoutFreeze::isFrozen($victim->id));
        $this->assertNotNull(PayoutUserFreeze::where('user_id', $victim->id)->first()->released_at);
    }

    public function test_a_statement_upload_reconciles_and_the_accounting_export_downloads(): void
    {
        $r = $this->heldRequest();
        app(PayoutService::class)->confirm($r, 'TRF');
        $csv = UploadedFile::fake()->createWithContent('statement.csv', "reference,amount,currency\n{$r->provider_reference},{$r->amount},NGN\n");

        Livewire::actingAs($this->user('admin'))->test(PayoutHealth::class)
            ->set('statement', $csv)->set('reconProvider', 'paystack')
            ->set('reconFrom', now()->subDay()->toDateString())->set('reconTo', now()->addDay()->toDateString())
            ->call('reconcile')->assertSee('1 matched, 0 to review')
            ->call('exportAccounting')->assertFileDownloaded('payout-accounting-'.now()->format('Y-m').'.csv');
    }

    public function test_the_fx_acceptance_is_super_admin_only(): void
    {
        Livewire::actingAs($this->user('admin'))->test(PayoutHealth::class)->set('fxCurrency', 'KES')->set('fxRate', '130')->call('acceptFx')->assertForbidden();

        Livewire::actingAs($this->sa())->test(PayoutHealth::class)->set('fxCurrency', 'KES')->set('fxRate', '130')->call('acceptFx')->assertSee('rate accepted');
        $this->assertSame(130.0, \Illuminate\Support\Facades\Cache::get('payouts.fx.last_good.KES')['rate']);
    }

    // ── env guard + fingerprints ──

    public function test_a_live_key_is_refused_outside_live_mode_and_a_test_key_inside_it(): void
    {
        config(['services.paystack.secret_key' => 'sk_live_abc', 'payouts.env' => 'sandbox']);
        $this->assertFalse(PayoutEnvGuard::allows('paystack'), 'a live key on a sandbox/dev box');
        $this->assertNull(app(PayoutService::class)->gatewayFor('paystack'));

        config(['services.paystack.secret_key' => 'sk_test_abc']);
        $this->assertTrue(PayoutEnvGuard::allows('paystack'));

        config(['payouts.env' => 'live']);   // testing env is not production, so 'live' is not honoured
        $this->assertSame('sandbox', PayoutEnvGuard::mode());
        $this->app['env'] = 'production';
        $this->assertSame('live', PayoutEnvGuard::mode());
        $this->assertFalse(PayoutEnvGuard::allows('paystack'), 'a test key in live mode');
        config(['services.paystack.secret_key' => 'sk_live_abc']);
        $this->assertTrue(PayoutEnvGuard::allows('paystack'));
        $this->assertTrue(PayoutEnvGuard::allows('flutterwave'), 'providers without a key marker pass');
    }

    public function test_rotating_the_fingerprint_key_and_reindexing_keeps_duplicate_destination_detection(): void
    {
        $u = User::factory()->create();
        $a = PayoutAccount::create(['user_id' => $u->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN', 'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'T', 'provider' => 'paystack', 'is_verified' => true]);
        $before = $a->lookup_hash;

        config(['payouts.fp_key' => 'a-brand-new-key']);
        $this->assertNotSame($before, PayoutAccount::lookupHashFor('0123456789'));

        $this->artisan('payouts:reindex-fingerprints')->expectsOutputToContain('Re-indexed 1')->assertSuccessful();
        $this->assertSame(PayoutAccount::lookupHashFor('0123456789'), $a->fresh()->lookup_hash);
        $this->artisan('payouts:reindex-fingerprints')->expectsOutputToContain('Re-indexed 0');   // idempotent
    }
}
