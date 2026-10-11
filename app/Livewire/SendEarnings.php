<?php

namespace App\Livewire;

use App\Models\EarningsTransfer;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\Peer\EarningsTransferService;
use App\Support\PayoutSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Send earnings to a member who can cash out" — for people whose country has no payout rail yet. Every rule lives in
 * EarningsTransferService; this page only collects input and shows state.
 */
#[Layout('components.layouts.customer')]
class SendEarnings extends Component
{
    public string $email = '';

    public $amount = '';

    public string $bucket = 'referral';

    public string $note = '';

    public ?string $notice = null;

    public ?string $error = null;

    public function send(EarningsTransferService $transfers): void
    {
        $this->reset(['notice', 'error']);
        $this->validate([
            'email' => 'required|email|max:190',
            'amount' => 'required|numeric|gt:0',
            'bucket' => 'required|in:referral,merchant',
            'note' => 'nullable|string|max:200',
        ]);
        try {
            $transfers->send(Auth::user(), $this->email, (float) $this->amount, $this->bucket, $this->note ?: null);
        } catch (PayoutException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->reset(['email', 'amount', 'note']);
        $this->notice = (string) __('payouts.peer.sent');
    }

    public function accept(int $id, EarningsTransferService $transfers): void
    {
        $this->act(fn () => $transfers->accept(Auth::user(), EarningsTransfer::findOrFail($id)), 'accepted');
    }

    public function decline(int $id, EarningsTransferService $transfers): void
    {
        $this->act(fn () => $transfers->decline(Auth::user(), EarningsTransfer::findOrFail($id)), 'declined');
    }

    public function cancel(int $id, EarningsTransferService $transfers): void
    {
        $this->act(fn () => $transfers->cancel(Auth::user(), EarningsTransfer::findOrFail($id)), 'cancelled');
    }

    private function act(callable $fn, string $done): void
    {
        $this->reset(['notice', 'error']);
        try {
            $fn();
            $this->notice = (string) __('payouts.peer.done_'.$done);
        } catch (PayoutException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(EarningsTransferService $transfers)
    {
        $user = Auth::user();
        $block = $transfers->senderBlock($user);

        return view('livewire.send-earnings', [
            'enabled' => PayoutSettings::enabled() && PayoutSettings::peerEnabled(),
            'block' => $block,
            'balances' => ['referral' => $transfers->available($user, 'referral'), 'merchant' => $transfers->available($user, 'merchant')],
            'canReceive' => $transfers->canReceive($user),
            'incoming' => EarningsTransfer::with('sender:id,name')->where('recipient_id', $user->id)->latest('id')->limit(20)->get(),
            'outgoing' => EarningsTransfer::with('recipient:id,name')->where('sender_id', $user->id)->latest('id')->limit(20)->get(),
            'min' => PayoutSettings::peerMinUsd(), 'max' => PayoutSettings::peerMaxUsd(),
            'expiryHours' => PayoutSettings::peerExpiryHours(),
        ]);
    }
}
