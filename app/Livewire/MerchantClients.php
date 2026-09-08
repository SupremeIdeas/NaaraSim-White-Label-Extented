<?php

namespace App\Livewire;

use App\Jobs\EvaluateJourneyGoalsJob;
use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\MerchantClientSubscription;
use App\Services\Merchants\MerchantClientService;
use App\Services\Merchants\MerchantException;
use App\Services\Wallet\WalletService;
use App\Support\CountryPickerSources;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Merchant V2 — premium client eSIM control. A V2 merchant manages clients who
 * never log in: register their device, check eSIM compatibility, subscribe an
 * eSIM (Naara Data or Naara Connect) from the merchant wallet, watch each
 * client's validity countdown, lock funds for auto-renewal, disable/re-provision
 * on (non-)payment, message clients on WhatsApp, and invoice them. 404s for a
 * non-V2 merchant.
 */
#[Layout('components.layouts.customer')]
class MerchantClients extends Component
{
    use WithPagination;

    public string $search = '';

    // Add/edit form.
    public ?int $editingId = null;

    public string $name = '';

    public string $contact = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $device = '';

    public string $device_os = '';

    public string $notes = '';

    // Assign-eSIM buffer.
    public ?int $assignClientId = null;

    public string $assignType = 'data';   // data | connect

    public ?int $assignPlanId = null;

    public bool $assignForce = false;

    // Assign-eSIM plan picker: search + country filter (owner request — the
    // flat 200-row name dropdown made finding a specific country's plan slow).
    public string $assignSearch = '';

    public string $assignCountry = '';

    // Invoice buffer.
    public ?int $invoiceClientId = null;

    public string $invoiceDesc = '';

    public $invoiceAmount = null;

    public ?string $invoiceLink = null;

    public ?string $invoiceText = null;

    // Deliver-eSIM buffer.
    public ?int $deliverSubId = null;

    public string $deliverChannel = 'email';   // email | whatsapp | both

    public ?string $deliverWaLink = null;

    // Auto-renew reserve buffer (how many cycles to pre-fund up front).
    public ?int $reserveSubId = null;

    public int $reserveCycles = 3;

    public bool $reserveIndefinite = false;

    public ?string $error = null;

    private function merchant(): Merchant
    {
        $merchant = Auth::user()?->merchantAccount;
        abort_unless($merchant !== null && $merchant->isActive() && $merchant->isV2(), 404);

        return $merchant;
    }

    public function mount(): void
    {
        $this->merchant();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    private function formData(): array
    {
        return [
            'name' => $this->name, 'contact' => $this->contact, 'whatsapp' => $this->whatsapp,
            'email' => $this->email, 'device' => $this->device, 'device_os' => $this->device_os,
            'notes' => $this->notes,
        ];
    }

    public function save(MerchantClientService $service): void
    {
        $this->error = null;
        $this->validate([
            'name' => 'required|string|max:120',
            'contact' => 'nullable|string|max:120',
            'whatsapp' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:190',
            'device' => 'nullable|string|max:120',
            'device_os' => 'nullable|in:ios,android,other',
            'notes' => 'nullable|string|max:1000',
        ]);
        $merchant = $this->merchant();

        try {
            if ($this->editingId) {
                $client = MerchantClient::where('merchant_id', $merchant->id)->findOrFail($this->editingId);
                $service->updateClient($merchant, $client, $this->formData());
            } else {
                $service->addClient($merchant, $this->formData());
                // My Journey goals (loyalty expansion) — a "clients connected"
                // goal can unlock the instant a new client is added.
                EvaluateJourneyGoalsJob::dispatch($merchant->owner_user_id);
            }
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('editingId', 'name', 'contact', 'whatsapp', 'email', 'device', 'device_os', 'notes');
        $this->dispatch('nx-toast', type: 'success', message: 'Client saved.');
        $this->dispatch('close-client-sheet');
    }

    public function edit(int $id): void
    {
        $client = MerchantClient::where('merchant_id', $this->merchant()->id)->findOrFail($id);
        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->contact = (string) $client->contact;
        $this->whatsapp = (string) $client->whatsapp;
        $this->email = (string) $client->email;
        $this->device = (string) $client->device;
        $this->device_os = (string) $client->device_os;
        $this->notes = (string) $client->notes;
    }

    public function newClient(): void
    {
        $this->reset('editingId', 'name', 'contact', 'whatsapp', 'email', 'device', 'device_os', 'notes', 'error');
    }

    public function toggleActive(int $id, MerchantClientService $service): void
    {
        $merchant = $this->merchant();
        $client = MerchantClient::where('merchant_id', $merchant->id)->findOrFail($id);
        $service->setActive($merchant, $client, ! $client->is_active);
    }

    public function openAssign(int $clientId): void
    {
        $this->reset('assignPlanId', 'assignForce', 'error', 'assignSearch', 'assignCountry');
        $this->assignClientId = $clientId;
        $this->assignType = 'data';
    }

    /** Any filter change invalidates the currently-selected plan (it may no
     *  longer be in the filtered list). */
    public function updatedAssignSearch(): void
    {
        $this->assignPlanId = null;
    }

    public function updatedAssignCountry(): void
    {
        $this->assignPlanId = null;
    }

    public function updatedAssignType(): void
    {
        $this->assignPlanId = null;
        $this->assignCountry = ''; // data/connect cover different countries
    }

    public function assign(MerchantClientService $service): void
    {
        $this->error = null;
        $merchant = $this->merchant();
        $client = MerchantClient::where('merchant_id', $merchant->id)->findOrFail($this->assignClientId);
        $plan = EsimPlan::findOrFail($this->assignPlanId);

        try {
            $service->assignEsim($merchant, $client, $plan, force: $this->assignForce);
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->reset('assignClientId', 'assignPlanId', 'assignForce');
        $this->dispatch('nx-toast', variant: 'hero', type: 'success',
            title: 'eSIM assigned', message: "It's provisioning now and will show under the client.");
    }

    /** Open the auto-renew reserve sheet for a subscription. */
    public function openReserve(int $subscriptionId): void
    {
        $merchant = $this->merchant();
        $sub = MerchantClientSubscription::where('merchant_id', $merchant->id)->findOrFail($subscriptionId);
        $this->reserveSubId = $sub->id;
        $this->reserveCycles = 3;
        $this->reserveIndefinite = false;
    }

    public function enableAutoRenew(MerchantClientService $service): void
    {
        $merchant = $this->merchant();
        $sub = MerchantClientSubscription::where('merchant_id', $merchant->id)->findOrFail($this->reserveSubId);
        $this->validate([
            'reserveCycles' => 'required|integer|min:1|max:'.MerchantClientSubscription::MAX_RESERVE_CYCLES,
        ]);
        try {
            $service->enableAutoRenew($merchant, $sub, $this->reserveCycles, $this->reserveIndefinite);
            $this->reset('reserveSubId', 'reserveCycles', 'reserveIndefinite');
            $this->dispatch('close-reserve-sheet');
            $this->dispatch('nx-toast', type: 'success', message: $this->reserveIndefinite
                ? 'Auto-renew locked for life — one cycle is reserved and tops up after each renewal.'
                : 'Auto-renewal locked. The reserved cycles are set aside from your wallet.');
        } catch (MerchantException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());
        }
    }

    public function disableEsim(int $subscriptionId, MerchantClientService $service): void
    {
        $merchant = $this->merchant();
        $sub = MerchantClientSubscription::where('merchant_id', $merchant->id)->findOrFail($subscriptionId);
        try {
            $service->disableEsim($merchant, $sub);
            $this->dispatch('nx-toast', type: 'success', message: 'Client eSIM disabled.');
        } catch (MerchantException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());
        }
    }

    /** Open the deliver-eSIM sheet for a client's current subscription. */
    public function openDeliver(int $subscriptionId): void
    {
        $merchant = $this->merchant();
        $sub = MerchantClientSubscription::with('client')
            ->where('merchant_id', $merchant->id)->findOrFail($subscriptionId);
        $this->reset('deliverWaLink', 'error');
        $this->deliverSubId = $sub->id;
        // Default to whatever contact the client has (prefer email).
        $this->deliverChannel = filled($sub->client?->email) ? 'email'
            : (filled($sub->client?->whatsapp) ? 'whatsapp' : 'email');
    }

    /** Send the eSIM (QR + code + steps) to the client over the chosen channel(s). */
    public function deliver(MerchantClientService $service): void
    {
        $this->error = null;
        $merchant = $this->merchant();
        $sub = MerchantClientSubscription::where('merchant_id', $merchant->id)->findOrFail($this->deliverSubId);

        try {
            $res = $service->deliverEsim($merchant, $sub, $this->deliverChannel);
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->deliverWaLink = $res['whatsapp_link'];
        $this->dispatch('nx-toast', type: 'success',
            message: $res['email_sent'] ? 'eSIM emailed to the client.' : 'Ready to forward on WhatsApp.');
    }

    /** Open the invoice builder for a client. */
    public function openInvoice(int $clientId): void
    {
        $this->reset('invoiceDesc', 'invoiceAmount', 'invoiceLink', 'invoiceText');
        $this->invoiceClientId = $clientId;
    }

    /** Build a branded invoice message + WhatsApp forward link. */
    public function generateInvoice(): void
    {
        $merchant = $this->merchant();
        $client = MerchantClient::where('merchant_id', $merchant->id)->findOrFail($this->invoiceClientId);
        $this->validate([
            'invoiceAmount' => 'required|numeric|min:0.01|max:1000000',
            'invoiceDesc' => 'required|string|max:200',
        ]);

        $brand = $merchant->business_name ?: 'Your provider';
        $amount = number_format((float) $this->invoiceAmount, 2);
        $this->invoiceText = "*{$brand} — Invoice*\n\n".
            "Client: {$client->name}\n".
            "Service: {$this->invoiceDesc}\n".
            "Amount due: \${$amount}\n".
            'Date: '.now()->format('M j, Y')."\n\n".
            'Thank you for your business.';

        $this->invoiceLink = $client->whatsappLink($this->invoiceText);
    }

    /**
     * eSIM plans for the assign picker, scoped by line (data/connect) and the
     * merchant's search/country filters — mirrors the customer-facing
     * catalogue's country query (`whereJsonContains('countries', ...)`) so a
     * merchant searching "France" sees every plan that actually reaches it,
     * sorted fast, instead of scanning a flat 200-name dropdown.
     *
     * @return Collection<int, EsimPlan>
     */
    private function assignPlans(bool $hasVoice)
    {
        return EsimPlan::where('is_active', true)->where('has_voice', $hasVoice)
            ->when($this->assignSearch !== '', fn ($q) => $q->where('name', 'like', '%'.$this->assignSearch.'%'))
            ->when($this->assignCountry !== '', fn ($q) => $q->whereJsonContains('countries', $this->assignCountry))
            ->orderBy('name')->limit(200)->get(['id', 'name']);
    }

    public function render()
    {
        $merchant = $this->merchant();
        $wallet = app(WalletService::class);

        $clients = MerchantClient::where('merchant_id', $merchant->id)
            ->withCount('esimOrders')
            ->with(['subscriptions' => fn ($q) => $q->where('status', '!=', MerchantClientSubscription::STATUS_DISABLED)->latest('id')->limit(1)->with('order')])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('device', 'like', $term)->orWhere('contact', 'like', $term)
                    ->orWhere('whatsapp', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(12);

        $deliverSub = $this->deliverSubId
            ? MerchantClientSubscription::with(['client', 'plan', 'order'])
                ->where('merchant_id', $merchant->id)->find($this->deliverSubId)
            : null;

        $reserveSub = $this->reserveSubId
            ? MerchantClientSubscription::with('client')
                ->where('merchant_id', $merchant->id)->find($this->reserveSubId)
            : null;

        return view('livewire.merchant-clients', [
            'clients' => $clients,
            'deliverSub' => $deliverSub,
            'reserveSub' => $reserveSub,
            'maxReserveCycles' => MerchantClientSubscription::MAX_RESERVE_CYCLES,
            'dataPlans' => $this->assignPlans(false),
            'connectPlans' => $this->assignPlans(true),
            // Real, live country list for the currently-active line (data vs
            // connect cover different footprints) — same source the customer
            // catalogue's country picker uses, so it's never a stale/static list.
            'assignCountryOptions' => CountryPickerSources::options('esim', ['has_voice' => $this->assignType === 'connect']),
            'walletUsd' => round((float) ($merchant->owner->wallet?->usd_balance ?? 0), 2),
            'reservedUsd' => $wallet->reservedUsd($merchant->owner),
            'spendableUsd' => $wallet->spendableUsd($merchant->owner),
        ]);
    }
}
