<?php

namespace App\Livewire;

use App\Models\WalletTransaction;
use App\Notifications\PurchaseReceiptNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Customer receipts / order history (BUILD-7 §1). A document-shaped record of
 * every purchase the user can save, re-view or resend to email — built directly
 * on the authoritative wallet_transactions ledger (debits), so the wizard
 * convenience fee shows as its own line exactly as it was charged. Read-only.
 */
#[Layout('components.layouts.customer')]
class Receipts extends Component
{
    use WithPagination;

    public function resend(int $id): void
    {
        $tx = WalletTransaction::where('user_id', Auth::id())->where('type', 'debit')->find($id);
        if (! $tx) {
            return;
        }
        Auth::user()->notify(new PurchaseReceiptNotification(
            $tx->description ?: 'Purchase',
            abs((float) $tx->amount),
            $tx->reference ?: ('TX-'.$tx->id),
        ));
        $this->dispatch('nx-toast', type: 'success', message: 'Receipt resent to your email.');
    }

    public function render()
    {
        return view('livewire.receipts', [
            'receipts' => WalletTransaction::where('user_id', Auth::id())
                ->where('type', 'debit')
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
