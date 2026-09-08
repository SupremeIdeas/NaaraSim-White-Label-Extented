<?php

namespace Tests\Feature;

use App\Jobs\CreditWalletJob;
use App\Livewire\Checkout;
use App\Livewire\GetNumber;
use App\Models\EsimPlan;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\RefundNotification;
use App\Notifications\TopUpReceiptNotification;
use App\Services\Wallet\WalletService;
use App\Support\MailSettings;
use Database\Seeders\PricingSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

/**
 * Transactional emails for the money paths: order confirmation, top-up receipt,
 * refund notice. All are best-effort and gated on a configured mailer.
 */
class TransactionalEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSettingsSeeder::class);
        \App\Models\Setting::setValue('pricing.ngn_rate_source', 'manual');
        \App\Models\Setting::setValue('pricing.manual_ngn_rate', 1500);
    }

    private function configureMail(): void
    {
        MailSettings::save([
            'mailer' => 'smtp', 'host' => 'smtp.test', 'port' => 587,
            'username' => 'u', 'password' => 'p',
            'from_address' => 'no-reply@naarasim.test', 'from_name' => 'NaaraSim',
        ]);
    }

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'em-1', 'name' => 'USA 3GB 30D',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 3.77, 'computed_retail_usd' => 10.00,
        ])->fresh();
    }

    public function test_esim_purchase_sends_an_order_confirmation_when_mail_is_configured(): void
    {
        $this->configureMail();
        Notification::fake();

        $plan = $this->plan();
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: ['iccid' => '8944', 'orderReference' => 'O1']));

        Livewire::actingAs($user)->test(Checkout::class, ['plan' => $plan])
            ->set('deviceConfirmed', true)
            ->call('purchase')
            ->assertSet('done', true);

        Notification::assertSentTo($user, OrderPlacedNotification::class,
            fn ($n) => $n->product === 'esim' && (float) $n->amount === 10.0);
    }

    public function test_number_order_sends_a_confirmation(): void
    {
        $this->configureMail();
        Notification::fake();
        Queue::fake();

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        app()->instance('number.fivesim', new FakeSmsProvider(
            price: 0.20,
            buyResponse: ['provider_ref' => '5S', 'number' => '2348010000000', 'cost' => 0.20, 'status' => 'pending'],
        ));

        Livewire::actingAs($user)->test(GetNumber::class)
            ->set('country', 'nigeria')->set('service', 'whatsapp')->call('order')
            ->assertSet('error', null);

        Notification::assertSentTo($user, OrderPlacedNotification::class, fn ($n) => $n->product === 'number');
    }

    public function test_topup_credit_sends_a_receipt_once(): void
    {
        $this->configureMail();
        Notification::fake();

        $user = User::factory()->create();
        (new CreditWalletJob('paystack', 'ref-1', $user->id, 5000, 'NGN'))->handle(app(WalletService::class));
        // A duplicate (idempotent) credit must NOT re-email.
        (new CreditWalletJob('paystack', 'ref-1', $user->id, 5000, 'NGN'))->handle(app(WalletService::class));

        Notification::assertSentToTimes($user, TopUpReceiptNotification::class, 1);
    }

    public function test_refund_sends_a_notice_and_can_be_suppressed(): void
    {
        $this->configureMail();
        Notification::fake();

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 20, 'USD');
        app(WalletService::class)->debit($user, 10, 'USD', ['reference' => 'buy-1']);

        app(WalletService::class)->refund($user, 10, 'USD', ['reference' => 'refund-1', 'description' => 'Order failed']);
        Notification::assertSentToTimes($user, RefundNotification::class, 1);

        // notify=false suppresses the email (e.g. an internal reversal).
        app(WalletService::class)->refund($user, 5, 'USD', ['reference' => 'refund-2', 'notify' => false]);
        Notification::assertSentToTimes($user, RefundNotification::class, 1);
    }

    public function test_nothing_is_sent_when_mail_is_not_configured(): void
    {
        // No configureMail() — mailer stays log/array.
        Notification::fake();

        $user = User::factory()->create();
        (new CreditWalletJob('paystack', 'ref-x', $user->id, 5000, 'NGN'))->handle(app(WalletService::class));
        app(WalletService::class)->credit($user, 20, 'USD');
        app(WalletService::class)->refund($user, 5, 'USD', ['reference' => 'r-x']);

        Notification::assertNothingSent();
    }
}
