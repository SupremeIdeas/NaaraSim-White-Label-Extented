<?php

namespace Tests\Feature;

use App\Livewire\MerchantClients;
use App\Mail\ClientEsimMail;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\MerchantClientSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Services\Merchants\MerchantClientService;
use App\Services\Merchants\MerchantException;
use App\Services\Wallet\WalletService;
use App\Support\MerchantSettings;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\FakeEsimProvider;
use Tests\TestCase;

/**
 * Merchant V2 — delivering a client's eSIM (QR + activation code + install
 * steps) by email / WhatsApp / both, plus the owner-scoped QR image endpoint.
 * The API-parity promise: the merchant gets everything needed to install and
 * validate the eSIM on demand, without ever seeing cost or the raw provider.
 */
class MerchantEsimDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue(MerchantSettings::FLAG, true);
    }

    private function merchant(float $fund = 50): Merchant
    {
        $owner = User::factory()->create(['is_active' => true]);
        app(WalletService::class)->credit($owner, $fund, 'USD', ['reference' => 'seed:'.$owner->id]);

        return Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Ada Telecom', 'slug' => 'ada-'.$owner->id,
            'status' => 'active', 'tier' => Merchant::TIER_V2, 'reseller_margin_pct' => 10,
        ]);
    }

    private function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'USA 3GB',
            'data_mb' => 3072, 'validity_days' => 30, 'countries' => ['US'],
            'cost_price_usd' => 4.00, 'computed_retail_usd' => 10.00,
        ])->fresh();
    }

    /** Assign an eSIM that comes back with a real LPA so it's deliverable. */
    private function assign(Merchant $m, array $clientAttrs = []): array
    {
        $client = $m->clients()->create(array_merge(['name' => 'Chidi', 'is_active' => true,
            'email' => 'chidi@example.com', 'whatsapp' => '+2348010000000'], $clientAttrs));
        app()->instance('esim.esimgo', new FakeEsimProvider(orderResponse: [
            'iccid' => '8944000', 'orderReference' => 'O1', 'lpa' => 'LPA:1$smdp.example.com$MATCH-123',
        ]));
        $order = app(MerchantClientService::class)->assignEsim($m, $client, $this->plan());
        $sub = MerchantClientSubscription::where('merchant_client_id', $client->id)->latest('id')->first();

        return [$client, $order, $sub];
    }

    public function test_the_assigned_esim_carries_the_qr_and_activation_code(): void
    {
        [$client, $order] = $this->assign($this->merchant());

        $this->assertSame('8944000', $order->iccid);
        $this->assertSame('LPA:1$smdp.example.com$MATCH-123', $order->lpa_string);
        $this->assertTrue($order->isDeliverable());
    }

    public function test_qr_endpoint_renders_svg_for_the_owner_but_404s_for_a_stranger(): void
    {
        $m = $this->merchant();
        [$client, $order] = $this->assign($m);

        $this->actingAs($m->owner)->get(route('esim.qr', $order))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

        $stranger = User::factory()->create(['is_active' => true]);
        $this->actingAs($stranger)->get(route('esim.qr', $order))->assertNotFound();
    }

    public function test_qr_endpoint_404s_when_there_is_no_activation_code(): void
    {
        $m = $this->merchant();
        $client = $m->clients()->create(['name' => 'No Code', 'is_active' => true]);
        $order = EsimOrder::create([
            'user_id' => $m->owner_user_id, 'merchant_client_id' => $client->id, 'plan_id' => $this->plan()->id,
            'provider' => 'esimgo', 'status' => 'processing', 'price_charged' => 5, 'currency' => 'USD',
        ]);

        $this->actingAs($m->owner)->get(route('esim.qr', $order))->assertNotFound();
    }

    public function test_delivering_by_email_queues_the_branded_mail(): void
    {
        Mail::fake();
        $m = $this->merchant();
        [$client, $order, $sub] = $this->assign($m);

        $res = app(MerchantClientService::class)->deliverEsim($m, $sub, 'email');

        $this->assertTrue($res['email_sent']);
        $this->assertNull($res['whatsapp_link']);
        Mail::assertQueued(ClientEsimMail::class, fn ($mail) => $mail->hasTo('chidi@example.com') && $mail->brand === 'Ada Telecom');
    }

    public function test_delivering_by_whatsapp_returns_a_link_carrying_the_code(): void
    {
        Mail::fake();
        $m = $this->merchant();
        [$client, $order, $sub] = $this->assign($m);

        $res = app(MerchantClientService::class)->deliverEsim($m, $sub, 'whatsapp');

        $this->assertFalse($res['email_sent']);
        $this->assertStringContainsString('wa.me', $res['whatsapp_link']);
        $this->assertStringContainsString('MATCH-123', rawurldecode($res['whatsapp_link']));
        Mail::assertNothingQueued();
    }

    public function test_both_channels_send_email_and_return_a_link(): void
    {
        Mail::fake();
        $m = $this->merchant();
        [$client, $order, $sub] = $this->assign($m);

        $res = app(MerchantClientService::class)->deliverEsim($m, $sub, 'both');

        $this->assertTrue($res['email_sent']);
        $this->assertStringContainsString('wa.me', $res['whatsapp_link']);
        Mail::assertQueued(ClientEsimMail::class);
    }

    public function test_cannot_deliver_before_the_esim_is_ready(): void
    {
        $m = $this->merchant();
        $client = $m->clients()->create(['name' => 'Pending', 'is_active' => true, 'email' => 'p@example.com']);
        $order = EsimOrder::create([
            'user_id' => $m->owner_user_id, 'merchant_client_id' => $client->id, 'plan_id' => $this->plan()->id,
            'provider' => 'esimgo', 'status' => 'processing', 'price_charged' => 5, 'currency' => 'USD',
        ]);
        $sub = MerchantClientSubscription::create([
            'merchant_id' => $m->id, 'merchant_client_id' => $client->id, 'esim_order_id' => $order->id,
            'plan_id' => $this->plan()->id, 'esim_type' => 'data', 'status' => 'active',
            'activated_at' => now(), 'renewal_price' => 5,
        ]);

        $this->expectException(MerchantException::class);
        app(MerchantClientService::class)->deliverEsim($m, $sub, 'email');
    }

    public function test_email_channel_requires_a_client_email(): void
    {
        $m = $this->merchant();
        [$client, $order, $sub] = $this->assign($m, ['email' => null]);

        $this->expectException(MerchantException::class);
        app(MerchantClientService::class)->deliverEsim($m, $sub, 'email');
    }

    public function test_a_merchant_cannot_deliver_another_merchants_subscription(): void
    {
        $mine = $this->merchant();
        $other = $this->merchant();
        [$client, $order, $sub] = $this->assign($other);

        $this->expectException(MerchantException::class);
        app(MerchantClientService::class)->deliverEsim($mine, $sub, 'email');
    }

    public function test_livewire_deliver_action_sends_and_surfaces_the_whatsapp_link(): void
    {
        Mail::fake();
        $m = $this->merchant();
        [$client, $order, $sub] = $this->assign($m);

        Livewire::actingAs($m->owner)->test(MerchantClients::class)
            ->call('openDeliver', $sub->id)
            ->set('deliverChannel', 'both')
            ->call('deliver')
            ->assertSet('deliverWaLink', fn ($link) => str_contains((string) $link, 'wa.me'));

        Mail::assertQueued(ClientEsimMail::class);
    }
}
