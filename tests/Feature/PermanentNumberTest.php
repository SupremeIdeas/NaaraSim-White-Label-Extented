<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WalletTransaction;
use App\Services\Pricing\PricingEngine;
use App\Services\SMS\PermanentNumberRouter;
use App\Services\Wallet\WalletService;
use App\Support\MailSettings;
use App\Support\ProviderKeys;
use App\Support\ProviderModels;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePermanentProvider;
use Tests\TestCase;

/**
 * Permanent numbers (Naara Line): provisioning money-safety + monthly billing.
 */
class PermanentNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        // Make Twilio "configured" so the lane is live, and bind a fake over it.
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'tok']);
        ProviderKeys::flush();
        // Real mailer (Prompt 10 renewal-notice tests) — Mailer::notify() is a
        // silent no-op while MailSettings::isConfigured() is false (log/array).
        MailSettings::save(['mailer' => 'smtp', 'from_address' => 'a@b.co', 'from_name' => 'N']);
    }

    private function fakeTwilio(FakePermanentProvider $fake): void
    {
        $this->app->instance('number.twilio', $fake);
    }

    public function test_naara_line_goes_live_once_a_permanent_provider_is_configured(): void
    {
        $this->assertSame('live', ProviderModels::status('naara_line'));

        config(['services.twilio.account_sid' => null, 'services.twilio.auth_token' => null,
            'services.telnyx.api_key' => null]);
        ProviderKeys::flush();
        $this->assertSame('needs_key', ProviderModels::status('naara_line'));
    }

    public function test_provision_charges_the_first_month_and_creates_the_subscription(): void
    {
        $this->fakeTwilio(new FakePermanentProvider(cost: 1.00));
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        $retail = round(app(PricingEngine::class)->calculateSmsRetail(1.00, 'twilio'), 4);
        $vn = app(PermanentNumberRouter::class)->provision($user, 'usa', '+15550001234', 'twilio');

        $this->assertSame('active', $vn->status);
        $this->assertSame('+15550001234', $vn->phone_number);
        $this->assertNotNull($vn->next_billing_date);
        // Wallet charged exactly the retail (first month); never the cost.
        $this->assertSame(number_format(20 - $retail, 4, '.', ''), (string) $user->wallet->fresh()->usd_balance);
        $this->assertGreaterThanOrEqual(1.00 + 0.01, (float) $vn->monthly_retail); // cost + min profit
        // The raw supplier is never serialised.
        $this->assertArrayNotHasKey('provider', $vn->fresh()->toArray());
        $this->assertArrayNotHasKey('monthly_cost', $vn->fresh()->toArray());
    }

    public function test_search_matches_numbers_by_the_neutral_spec(): void
    {
        $this->fakeTwilio(new FakePermanentProvider(cost: 1.00, results: [
            ['number' => '+15550001234', 'locality' => 'NY'],
            ['number' => '+15559991234', 'locality' => 'CA'],
            ['number' => '+15558887777', 'locality' => 'TX'],
        ]));

        $router = app(PermanentNumberRouter::class);

        // ends-with: only the two ending in 1234.
        $ends = $router->search('usa', ['digits' => '1234', 'position' => 'ends']);
        $this->assertSame(['+15550001234', '+15559991234'], array_column($ends['numbers'], 'number'));

        // contains: 777 hits only the last number.
        $contains = $router->search('usa', ['digits' => '777', 'position' => 'contains']);
        $this->assertSame(['+15558887777'], array_column($contains['numbers'], 'number'));

        // no spec: everything, priced at retail (never cost).
        $any = $router->search('usa');
        $this->assertCount(3, $any['numbers']);
        $this->assertGreaterThanOrEqual(1.01, (float) $any['numbers'][0]['monthly_retail']);
    }

    public function test_a_provider_failure_refunds_the_wallet_and_saves_nothing(): void
    {
        $this->fakeTwilio(new FakePermanentProvider(throwOnBuy: true));
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        try {
            app(PermanentNumberRouter::class)->provision($user, 'usa', '+15550001234', 'twilio');
            $this->fail('expected SmsException');
        } catch (SmsException) {
            // expected
        }

        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance); // refunded
        $this->assertSame(0, VirtualNumber::count());
    }

    public function test_orphan_charge_guard_releases_and_refunds_when_the_save_fails(): void
    {
        $fake = new FakePermanentProvider(cost: 1.00);
        $this->fakeTwilio($fake);
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');

        // Pre-occupy the number so the unique(phone_number) save throws.
        VirtualNumber::create([
            'user_id' => $user->id, 'provider' => 'twilio', 'phone_number' => '+15550001234',
            'monthly_cost' => 1, 'monthly_retail' => 1.5, 'status' => 'active',
        ]);

        try {
            app(PermanentNumberRouter::class)->provision($user, 'usa', '+15550001234', 'twilio');
            $this->fail('expected failure');
        } catch (SmsException) {
            // expected
        }

        $this->assertSame('20.0000', (string) $user->wallet->fresh()->usd_balance); // refunded
        $this->assertNotEmpty($fake->released); // the just-bought number was released
    }

    public function test_insufficient_balance_blocks_provisioning(): void
    {
        $this->fakeTwilio(new FakePermanentProvider);
        $user = User::factory()->create(); // no funds

        $this->expectException(InsufficientBalanceException::class);
        try {
            app(PermanentNumberRouter::class)->provision($user, 'usa', '+15550001234', 'twilio');
        } finally {
            $this->assertSame(0, VirtualNumber::count());
        }
    }

    // ---- monthly billing (virtual:renew) -----------------------------------

    private function subscription(User $u, array $extra = []): VirtualNumber
    {
        return VirtualNumber::create(array_merge([
            'user_id' => $u->id, 'provider' => 'twilio', 'phone_number' => '+1555000'.rand(1000, 9999),
            'sid' => 'SID-'.uniqid(), 'monthly_cost' => 1.00, 'monthly_retail' => 1.50,
            'status' => 'active', 'next_billing_date' => today()->toDateString(), 'provisioned_at' => now(),
        ], $extra));
    }

    public function test_renew_charges_a_due_number_and_advances_the_date(): void
    {
        $u = User::factory()->create();
        app(WalletService::class)->credit($u, 10, 'USD');
        $vn = $this->subscription($u);

        $this->artisan('virtual:renew')->assertSuccessful();

        $this->assertSame('8.5000', (string) $u->wallet->fresh()->usd_balance); // 10 - 1.50
        $vn->refresh();
        $this->assertSame('active', $vn->status);
        $this->assertTrue($vn->next_billing_date->isFuture());
    }

    public function test_renew_marks_past_due_when_the_wallet_is_short(): void
    {
        $u = User::factory()->create(); // no funds
        $vn = $this->subscription($u);

        $this->artisan('virtual:renew')->assertSuccessful();

        $vn->refresh();
        $this->assertSame('past_due', $vn->status);
        $this->assertNotNull($vn->expires_at);
    }

    public function test_running_renew_twice_in_the_same_month_charges_each_overdue_period_once(): void
    {
        // A subscription 2 months behind (e.g. the scheduler missed a run).
        // Money-safety: catching up must charge BOTH overdue periods, never
        // silently advance next_billing_date on the second run without a
        // matching debit just because it's still the same calendar month.
        $u = User::factory()->create();
        app(WalletService::class)->credit($u, 10, 'USD');
        $vn = $this->subscription($u, ['next_billing_date' => today()->subMonths(2)->toDateString()]);

        $this->artisan('virtual:renew')->assertSuccessful();
        $this->assertSame('8.5000', (string) $u->wallet->fresh()->usd_balance);
        $vn->refresh();
        $this->assertTrue($vn->next_billing_date->lte(today()), 'still overdue after only one month advanced');

        $this->artisan('virtual:renew')->assertSuccessful();

        $this->assertSame('7.0000', (string) $u->wallet->fresh()->usd_balance);
        $this->assertSame(2, WalletTransaction::where('user_id', $u->id)->where('type', 'debit')->count());
    }

    public function test_a_lapsed_past_due_number_is_released_and_expired(): void
    {
        $fake = new FakePermanentProvider;
        $this->fakeTwilio($fake);
        $u = User::factory()->create();
        $vn = $this->subscription($u, [
            'status' => 'past_due', 'expires_at' => now()->subDay(), 'sid' => 'SID-lapsed',
        ]);

        $this->artisan('virtual:renew')->assertSuccessful();

        $vn->refresh();
        $this->assertSame('expired', $vn->status);
        $this->assertContains('SID-lapsed', $fake->released);
    }

    // ---- Prompt 10: auto-renewal opt-out + advance notice ------------------

    public function test_an_opted_out_number_ends_cleanly_with_no_charge_when_due(): void
    {
        $fake = new FakePermanentProvider;
        $this->fakeTwilio($fake);
        $u = User::factory()->create();
        app(WalletService::class)->credit($u, 10, 'USD'); // funds available — must NOT be touched
        $vn = $this->subscription($u, ['auto_renew' => false, 'sid' => 'SID-optout']);

        $this->artisan('virtual:renew')->assertSuccessful();

        $vn->refresh();
        $this->assertSame('expired', $vn->status);
        $this->assertContains('SID-optout', $fake->released);
        // Opting out is a choice, not a payment failure — never past_due, never charged.
        $this->assertSame('10.0000', (string) $u->wallet->fresh()->usd_balance);
        $this->assertSame(0, WalletTransaction::where('user_id', $u->id)->where('type', 'debit')->count());
    }

    public function test_a_successful_renewal_resets_the_notice_marker_for_the_next_cycle(): void
    {
        $u = User::factory()->create();
        app(WalletService::class)->credit($u, 10, 'USD');
        $vn = $this->subscription($u, ['renewal_notice_sent_at' => now()->subDays(2)]);

        $this->artisan('virtual:renew')->assertSuccessful();

        $this->assertNull($vn->refresh()->renewal_notice_sent_at);
    }

    public function test_a_renewing_number_due_soon_gets_an_advance_notice_and_is_not_charged_yet(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $u = User::factory()->create();
        app(WalletService::class)->credit($u, 10, 'USD');
        $vn = $this->subscription($u, ['next_billing_date' => today()->addDays(2)->toDateString()]);

        $this->artisan('virtual:renew')->assertSuccessful();

        \Illuminate\Support\Facades\Notification::assertSentTo($u, \App\Notifications\VirtualNumberRenewalNotification::class,
            fn ($n) => $n->line->is($vn) && $n->line->auto_renew === true);
        $this->assertNotNull($vn->refresh()->renewal_notice_sent_at);
        // Not due today — no charge yet, just the notice.
        $this->assertSame('10.0000', (string) $u->wallet->fresh()->usd_balance);
    }

    public function test_an_opted_out_number_due_soon_gets_an_ending_notice(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $u = User::factory()->create();
        $vn = $this->subscription($u, [
            'auto_renew' => false, 'next_billing_date' => today()->addDays(1)->toDateString(),
        ]);

        $this->artisan('virtual:renew')->assertSuccessful();

        \Illuminate\Support\Facades\Notification::assertSentTo($u, \App\Notifications\VirtualNumberRenewalNotification::class,
            fn ($n) => $n->line->is($vn) && $n->line->auto_renew === false);
    }

    public function test_the_advance_notice_is_sent_only_once_per_cycle(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $u = User::factory()->create();
        app(WalletService::class)->credit($u, 10, 'USD');
        $this->subscription($u, ['next_billing_date' => today()->addDays(2)->toDateString()]);

        $this->artisan('virtual:renew')->assertSuccessful();
        $this->artisan('virtual:renew')->assertSuccessful();

        \Illuminate\Support\Facades\Notification::assertSentToTimes($u, \App\Notifications\VirtualNumberRenewalNotification::class, 1);
    }
}
