<?php

namespace Tests\Feature;

use App\Livewire\MerchantInvoices;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\MerchantInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Merchant V2 invoice dashboard — a merchant's own client-billing bookkeeping.
 * No NaaraSim wallet ever moves here (the client pays the merchant directly),
 * so these tests assert the invoice lifecycle + stat math, not money movement.
 */
class MerchantInvoiceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function merchant(): Merchant
    {
        $owner = User::factory()->create(['is_active' => true]);

        return Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Kedu Telecom', 'slug' => 'kedu-'.$owner->id,
            'status' => 'active', 'tier' => Merchant::TIER_V2, 'reseller_margin_pct' => 10,
        ]);
    }

    private function client(Merchant $merchant, string $whatsapp = '2348012345678'): MerchantClient
    {
        return MerchantClient::create([
            'merchant_id' => $merchant->id, 'name' => 'Amaka', 'whatsapp' => $whatsapp, 'is_active' => true,
        ]);
    }

    public function test_a_v2_merchant_can_create_send_and_mark_an_invoice_paid(): void
    {
        $merchant = $this->merchant();
        $client = $this->client($merchant);

        Livewire::actingAs($merchant->owner)->test(MerchantInvoices::class)
            ->set('clientId', $client->id)
            ->set('description', '1-month eSIM renewal')
            ->set('amount', 15)
            ->set('dueAt', now()->addDays(7)->toDateString())
            ->call('create')
            ->assertSet('error', null);

        $invoice = MerchantInvoice::firstOrFail();
        $this->assertSame(MerchantInvoice::DRAFT, $invoice->status);
        $this->assertSame('15.00', (string) $invoice->amount);
        $this->assertNotEmpty($invoice->public_token);

        Livewire::actingAs($merchant->owner)->test(MerchantInvoices::class)
            ->call('send', $invoice->id);
        $invoice->refresh();
        $this->assertSame(MerchantInvoice::SENT, $invoice->status);
        $this->assertNotNull($invoice->sent_at);

        Livewire::actingAs($merchant->owner)->test(MerchantInvoices::class)
            ->call('markPaid', $invoice->id);
        $invoice->refresh();
        $this->assertSame(MerchantInvoice::PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_a_draft_invoice_can_be_voided_but_a_paid_one_cannot(): void
    {
        $merchant = $this->merchant();
        $client = $this->client($merchant);
        $invoice = MerchantInvoice::create([
            'merchant_id' => $merchant->id, 'merchant_client_id' => $client->id,
            'description' => 'Renewal', 'amount' => 10, 'status' => MerchantInvoice::DRAFT,
            'reference' => 'inv:test:1', 'public_token' => 'tok1',
        ]);

        Livewire::actingAs($merchant->owner)->test(MerchantInvoices::class)->call('void', $invoice->id);
        $this->assertSame(MerchantInvoice::VOID, $invoice->fresh()->status);

        $paid = MerchantInvoice::create([
            'merchant_id' => $merchant->id, 'merchant_client_id' => $client->id,
            'description' => 'Renewal 2', 'amount' => 10, 'status' => MerchantInvoice::PAID, 'paid_at' => now(),
            'reference' => 'inv:test:2', 'public_token' => 'tok2',
        ]);
        Livewire::actingAs($merchant->owner)->test(MerchantInvoices::class)->call('void', $paid->id);
        $this->assertSame(MerchantInvoice::PAID, $paid->fresh()->status);
    }

    public function test_overdue_and_due_soon_totals_are_computed_correctly(): void
    {
        $merchant = $this->merchant();
        $client = $this->client($merchant);

        MerchantInvoice::create([
            'merchant_id' => $merchant->id, 'merchant_client_id' => $client->id,
            'description' => 'Overdue one', 'amount' => 25, 'status' => MerchantInvoice::SENT,
            'sent_at' => now()->subDays(20), 'due_at' => now()->subDays(5),
            'reference' => 'inv:test:overdue', 'public_token' => 'tokA',
        ]);
        MerchantInvoice::create([
            'merchant_id' => $merchant->id, 'merchant_client_id' => $client->id,
            'description' => 'Due soon', 'amount' => 40, 'status' => MerchantInvoice::SENT,
            'sent_at' => now(), 'due_at' => now()->addDays(10),
            'reference' => 'inv:test:duesoon', 'public_token' => 'tokB',
        ]);
        MerchantInvoice::create([
            'merchant_id' => $merchant->id, 'merchant_client_id' => $client->id,
            'description' => 'Already paid', 'amount' => 99, 'status' => MerchantInvoice::PAID,
            'sent_at' => now()->subDays(10), 'paid_at' => now()->subDays(6),
            'reference' => 'inv:test:paid', 'public_token' => 'tokC',
        ]);

        Livewire::actingAs($merchant->owner)->test(MerchantInvoices::class)
            ->assertViewHas('overdueTotal', 25.0)
            ->assertViewHas('dueSoonTotal', 40.0)
            ->assertViewHas('avgDaysToPay', 4);
    }

    public function test_public_invoice_link_shows_only_sent_invoices_and_records_a_view(): void
    {
        $merchant = $this->merchant();
        $client = $this->client($merchant);
        $sent = MerchantInvoice::create([
            'merchant_id' => $merchant->id, 'merchant_client_id' => $client->id,
            'description' => 'Renewal', 'amount' => 15, 'status' => MerchantInvoice::SENT, 'sent_at' => now(),
            'reference' => 'inv:test:pub', 'public_token' => 'pubtok1',
        ]);
        $draft = MerchantInvoice::create([
            'merchant_id' => $merchant->id, 'merchant_client_id' => $client->id,
            'description' => 'Draft', 'amount' => 5, 'status' => MerchantInvoice::DRAFT,
            'reference' => 'inv:test:draft', 'public_token' => 'pubtok2',
        ]);

        $this->assertNull($sent->viewed_at);
        $this->get(route('invoice.public', 'pubtok1'))->assertOk()->assertSee('Amaka')->assertSee('15.00');
        $this->assertNotNull($sent->fresh()->viewed_at);

        $this->get(route('invoice.public', 'pubtok2'))->assertNotFound();
        $this->assertSame(0, MerchantInvoice::whereKey($draft->id)->whereNotNull('viewed_at')->count());
        $this->get(route('invoice.public', 'nonexistent-token'))->assertNotFound();
    }

    public function test_a_non_v2_merchant_cannot_reach_the_invoice_dashboard(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $merchant = Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Standard Co', 'slug' => 'std-'.$owner->id,
            'status' => 'active', 'tier' => Merchant::TIER_STANDARD,
        ]);

        Livewire::actingAs($owner)->test(MerchantInvoices::class)->assertStatus(404);
    }

    public function test_average_days_to_pay_is_null_when_nothing_is_paid_yet(): void
    {
        $merchant = $this->merchant();
        Livewire::actingAs($merchant->owner)->test(MerchantInvoices::class)
            ->assertViewHas('avgDaysToPay', null);
    }
}
