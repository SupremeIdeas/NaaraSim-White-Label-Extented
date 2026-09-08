<?php

namespace App\Livewire\Admin;

use App\Models\PaymentDispute;
use App\Models\PaymentRefund;
use App\Models\WalletTransaction;
use App\Services\Payments\RefundException;
use App\Services\Payments\RefundService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Payments → Refunds & Disputes (BUILD-2 §7). Refund a wallet top-up
 * (reverses the gateway charge + the wallet ledger, money-safely, via
 * RefundService) and watch card disputes (frozen while contested). Super-admin /
 * admin only; every refund is audited.
 */
#[Layout('components.layouts.admin')]
class Refunds extends Component
{
    use WithPagination;

    public string $search = '';

    /** Refund confirmation buffer. */
    public ?int $refundTxnId = null;

    public string $refundReason = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage('topups');
    }

    public function openRefund(int $txnId): void
    {
        $this->refundTxnId = $txnId;
        $this->refundReason = '';
    }

    public function refund(RefundService $refunds): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $txn = WalletTransaction::with('user')->findOrFail($this->refundTxnId);
        try {
            $result = $refunds->refund($txn, null, trim($this->refundReason), Auth::user());
        } catch (RefundException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->reset('refundTxnId', 'refundReason');
        $this->dispatch('close-refund');
        $this->dispatch('nx-toast', type: 'success', message: $result->status === PaymentRefund::STATUS_MANUAL
            ? 'This rail has no refund API — logged as a manual refund and an admin was alerted.'
            : 'Refunded and the wallet was reversed.');
    }

    public function render()
    {
        // Recent wallet top-ups (the refundable rows), newest first, with their
        // refund state joined in.
        $topups = WalletTransaction::query()
            ->with('user:id,name,email')
            ->where('type', 'credit')
            ->where('reference', 'like', 'topup:%')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term)));
            })
            ->latest()->paginate(15, pageName: 'topups');

        // Map each visible top-up to its refund (if any), keyed by transaction id,
        // so the view needs no reference parsing.
        $parsed = collect($topups->items())->mapWithKeys(function ($t) {
            [, $gw, $ref] = array_pad(explode(':', (string) $t->reference, 3), 3, '');

            return [$t->id => $gw.':'.$ref];
        });
        $refundsByKey = PaymentRefund::query()
            ->get()->keyBy(fn ($r) => $r->gateway.':'.$r->reference);
        $refundByTxn = $parsed->mapWithKeys(fn ($key, $txnId) => [$txnId => $refundsByKey->get($key)]);

        $disputes = PaymentDispute::query()
            ->with('user:id,email')
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->latest()->limit(30)->get();

        $refundTxn = $this->refundTxnId
            ? WalletTransaction::with('user:id,email')->find($this->refundTxnId)
            : null;

        return view('livewire.admin.refunds', compact('topups', 'refundByTxn', 'disputes', 'refundTxn'));
    }
}
