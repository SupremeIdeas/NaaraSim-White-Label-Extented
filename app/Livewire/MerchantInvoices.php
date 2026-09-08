<?php

namespace App\Livewire;

use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\MerchantInvoice;
use App\Services\Merchants\MerchantException;
use App\Services\Merchants\MerchantInvoiceService;
use App\Services\Merchants\MerchantWithdrawalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Merchant V2 invoice dashboard — a merchant's own client billing ledger
 * (overdue / due-soon / average time to get paid, filterable list + detail
 * panel). Purely bookkeeping: a client pays the merchant directly, so nothing
 * here moves a NaaraSim wallet. "Available for instant payout" reuses the SAME
 * MerchantWithdrawalService figure shown on the storefront dashboard — never a
 * second, possibly-drifting number.
 */
#[Layout('components.layouts.customer')]
class MerchantInvoices extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public string $search = '';

    public ?int $selectedId = null;

    // Create-invoice buffer.
    public bool $showCreate = false;

    #[Url(as: 'client')]
    public ?int $clientId = null;

    public string $description = '';

    public $amount = null;

    public string $dueAt = '';

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
        if ($this->clientId) {
            $this->showCreate = true;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset('description', 'amount', 'dueAt', 'error');
        $this->dueAt = now()->addDays(7)->toDateString();
        $this->showCreate = true;
    }

    public function select(int $id): void
    {
        $merchant = $this->merchant();
        $this->selectedId = MerchantInvoice::where('merchant_id', $merchant->id)->where('id', $id)->value('id');
    }

    public function create(MerchantInvoiceService $invoices): void
    {
        $this->error = null;
        $merchant = $this->merchant();
        $this->validate([
            'clientId' => 'required|integer',
            'description' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'dueAt' => 'nullable|date',
        ]);

        $client = MerchantClient::where('merchant_id', $merchant->id)->find($this->clientId);
        if ($client === null) {
            $this->error = 'Choose a client.';

            return;
        }

        try {
            $invoice = $invoices->create(
                $merchant, $client, $this->description, (float) $this->amount,
                $this->dueAt !== '' ? Carbon::parse($this->dueAt)->endOfDay() : null,
            );
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('showCreate', 'clientId', 'description', 'amount', 'dueAt');
        $this->selectedId = $invoice->id;
        $this->dispatch('nx-toast', type: 'success', message: 'Invoice created as a draft.');
    }

    private function owned(int $id): MerchantInvoice
    {
        return MerchantInvoice::with('client')->where('merchant_id', $this->merchant()->id)->findOrFail($id);
    }

    public function send(int $id, MerchantInvoiceService $invoices): void
    {
        $invoice = $invoices->send($this->owned($id));
        $this->selectedId = $invoice->id;
        $this->dispatch('nx-toast', type: 'success', message: 'Invoice marked sent.');
    }

    public function markPaid(int $id, MerchantInvoiceService $invoices): void
    {
        try {
            $invoice = $invoices->markPaid($this->owned($id));
        } catch (MerchantException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return;
        }
        $this->selectedId = $invoice->id;
        $this->dispatch('nx-toast', type: 'success', message: 'Invoice marked paid.');
    }

    public function void(int $id, MerchantInvoiceService $invoices): void
    {
        try {
            $invoice = $invoices->void($this->owned($id));
        } catch (MerchantException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return;
        }
        $this->selectedId = $invoice->id;
        $this->dispatch('nx-toast', type: 'success', message: 'Invoice voided.');
    }

    public function render(MerchantWithdrawalService $withdrawals)
    {
        $merchant = $this->merchant();
        $now = now();

        $base = MerchantInvoice::where('merchant_id', $merchant->id);

        $overdueTotal = (clone $base)->where('status', MerchantInvoice::SENT)->where('due_at', '<', $now)->sum('amount');
        $dueSoonTotal = (clone $base)->where('status', MerchantInvoice::SENT)
            ->whereBetween('due_at', [$now, $now->copy()->addDays(30)])->sum('amount');
        $avgDaysToPay = (clone $base)->where('status', MerchantInvoice::PAID)
            ->whereNotNull('sent_at')->whereNotNull('paid_at')
            ->get(['sent_at', 'paid_at'])
            ->map(fn ($i) => $i->sent_at->diffInDays($i->paid_at))
            ->avg();

        $invoices = (clone $base)->with('client')
            ->when($this->statusFilter === 'draft', fn ($q) => $q->where('status', MerchantInvoice::DRAFT))
            ->when($this->statusFilter === 'unpaid', fn ($q) => $q->where('status', MerchantInvoice::SENT))
            ->when($this->statusFilter === 'paid', fn ($q) => $q->where('status', MerchantInvoice::PAID))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('description', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->latest('id')
            ->paginate(10);

        $selected = $this->selectedId
            ? MerchantInvoice::with('client')->where('merchant_id', $merchant->id)->find($this->selectedId)
            : null;

        return view('livewire.merchant-invoices', [
            'invoices' => $invoices,
            'selected' => $selected,
            'overdueTotal' => round((float) $overdueTotal, 2),
            'dueSoonTotal' => round((float) $dueSoonTotal, 2),
            'avgDaysToPay' => $avgDaysToPay !== null ? round($avgDaysToPay) : null,
            'payoutAvailable' => $withdrawals->availableUsd($merchant),
            'clients' => MerchantClient::where('merchant_id', $merchant->id)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
