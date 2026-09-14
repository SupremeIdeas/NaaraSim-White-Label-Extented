<?php

namespace Tests\Feature;

use App\Livewire\Contacts;
use App\Livewire\Dialer;
use App\Models\Contact;
use App\Models\User;
use App\Services\Voice\SpamReportService;
use App\Services\Voice\VoiceDialerService;
use App\Services\Wallet\WalletService;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Support\FakeVoiceProvider;
use Tests\TestCase;

/**
 * Spam-report + auto-block (Prompt 11): reports from Contacts or the Dialer
 * feed one platform-wide count; crossing the configured distinct-reporter
 * threshold blocks a number from being dialed BEFORE any wallet hold.
 */
class SpamReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);
        app()->instance('number.twilio', new FakeVoiceProvider(rate: 0.10));
    }

    private function fundedUser(float $usd = 10.0): User
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $usd, 'USD', ['reference' => 'seed:'.$user->id]);

        return $user->fresh();
    }

    public function test_reporting_the_same_number_twice_by_the_same_user_does_not_inflate_the_count(): void
    {
        $spam = app(SpamReportService::class);
        $reporter = $this->fundedUser();

        $spam->report('+15550001234', $reporter, 'dialer');
        $spam->report('+15550001234', $reporter, 'dialer', 'still bothering me');

        $this->assertSame(1, $spam->reportCount('+15550001234'));
        $this->assertDatabaseCount('spam_reports', 1);
    }

    public function test_crossing_the_threshold_auto_blocks_the_number(): void
    {
        \App\Models\Setting::setValue('spam.report_threshold', 3);
        $spam = app(SpamReportService::class);
        $number = '+15550001234';

        $spam->report($number, $this->fundedUser(), 'dialer');
        $this->assertFalse($spam->isBlocked($number));

        $spam->report($number, $this->fundedUser(), 'contacts');
        $this->assertFalse($spam->isBlocked($number));

        $spam->report($number, $this->fundedUser(), 'dialer');
        $this->assertTrue($spam->isBlocked($number));

        $this->assertDatabaseHas('spam_blocked_callers', ['msisdn' => '15550001234', 'source' => 'auto']);
    }

    public function test_a_blocked_number_is_refused_before_any_wallet_hold(): void
    {
        \App\Models\Setting::setValue('spam.report_threshold', 1);
        app(SpamReportService::class)->report('+15550001234', $this->fundedUser(), 'dialer');

        $user = $this->fundedUser(usd: 5.0);

        try {
            app(VoiceDialerService::class)->begin($user, '+15550001234');
            $this->fail('Expected a blocked number to be refused.');
        } catch (\App\Exceptions\SmsException $e) {
            $this->assertStringContainsString('spam', $e->getMessage());
        }

        $this->assertSame('5.0000', (string) $user->wallet->fresh()->usd_balance);
        $this->assertDatabaseCount('voice_calls', 0);
    }

    public function test_the_dialer_refuses_to_quote_a_blocked_number(): void
    {
        \App\Models\Setting::setValue('spam.report_threshold', 1);
        app(SpamReportService::class)->report('+15550001234', $this->fundedUser(), 'dialer');
        $user = $this->fundedUser(usd: 5.0);

        Livewire::actingAs($user)->test(Dialer::class)
            ->set('destination', '+15550001234')
            ->call('prepare')
            ->assertSet('quoted', false)
            ->assertSet('error', fn ($e) => str_contains($e, 'spam'));
    }

    public function test_reporting_from_the_dialer_uses_the_recent_calls_destination(): void
    {
        $user = $this->fundedUser(usd: 1.40);
        $dialer = app(VoiceDialerService::class);
        $call = $dialer->begin($user, '+15550001234');
        $dialer->settle($call, 60, 'completed');

        Livewire::actingAs($user)->test(Dialer::class)->call('reportSpam', $call->id);

        $this->assertDatabaseHas('spam_reports', [
            'msisdn' => '15550001234', 'reporter_user_id' => $user->id, 'source' => 'dialer',
        ]);
    }

    public function test_reporting_from_contacts_uses_the_contacts_own_number_and_is_owner_scoped(): void
    {
        $owner = $this->fundedUser();
        $stranger = $this->fundedUser();
        $contact = Contact::create(['user_id' => $owner->id, 'name' => 'Scam Caller', 'phone_number' => '+15550001234']);

        // A stranger can't report via someone else's contact row.
        Livewire::actingAs($stranger)->test(Contacts::class)->call('reportSpam', $contact->id);
        $this->assertDatabaseCount('spam_reports', 0);

        Livewire::actingAs($owner)->test(Contacts::class)->call('reportSpam', $contact->id);
        $this->assertDatabaseHas('spam_reports', [
            'msisdn' => '15550001234', 'reporter_user_id' => $owner->id, 'source' => 'contacts',
        ]);
    }

    public function test_admin_can_block_and_unblock_directly(): void
    {
        Artisan::call('spam:block', ['number' => '+15550001234']);
        $this->assertTrue(app(SpamReportService::class)->isBlocked('+15550001234'));

        Artisan::call('spam:block', ['number' => '+15550001234', '--unblock' => true]);
        $this->assertFalse(app(SpamReportService::class)->isBlocked('+15550001234'));
    }
}
