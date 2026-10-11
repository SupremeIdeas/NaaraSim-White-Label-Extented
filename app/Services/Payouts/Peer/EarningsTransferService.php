<?php

namespace App\Services\Payouts\Peer;

use App\Models\EarningsTransfer;
use App\Models\Merchant;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Notifications\EarningsTransferNotice;
use App\Services\Kyc\KycService;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\Payouts\Hardening\PayoutFreeze;
use App\Services\Payouts\PayoutException;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\Rail\PayoutRailRegistry;
use App\Services\Payouts\Rail\RailAdvisor;
use App\Services\Referrals\ReferralEarningsService;
use App\Support\Auditor;
use App\Support\PayoutSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Member-to-member earnings transfer: for someone whose country has no payout rail yet to move withdrawable earnings
 * to a trusted member who CAN be paid out (who accepts, then cashes out through the normal payout engine).
 *
 * Safety model:
 *  - ESCROW: the sender's earnings leave their ledger the moment the transfer is created (`transfer_out`), and reach the
 *    receiver's ledger only when the receiver ACCEPTS (`transfer_in`). Decline / cancel / expiry returns them
 *    (`release`). Each ledger write is idempotent on a reference; every state change is a compare-and-set on the
 *    status, inside one DB transaction with its ledger write, so a replay or a race can move the money at most once.
 *  - Neither movement is an `accrual`, so platform margin / admin withdrawable maths never double-counts it.
 *  - No chaining: a member who received in the last 30 days cannot send, and one who sent cannot receive.
 *  - The receiver must be identity-verified, active, not frozen and have a payout account that works RIGHT NOW.
 *  - Limits per transfer / sender / receiver / open count, all admin-editable. The Guardian also sends payouts that are
 *    mostly received money to a person for review (RiskScorer S_peer_funds).
 *  - Lookup is by exact email and every refusal reads the same ("cannot receive"), so it cannot be used to probe who
 *    has an account or who is verified.
 */
class EarningsTransferService
{
    public const BUCKETS = ['referral', 'merchant'];

    public function __construct(
        private ReferralEarningsService $referral,
        private MerchantEarningsService $merchant,
        private PayoutService $payouts,
        private PayoutRailRegistry $registry,
        private KycService $kyc,
    ) {}

    /** Withdrawable USD the member could send from a bucket. */
    public function available(User $user, string $bucket): float
    {
        return match ($bucket) {
            'merchant' => ($m = $this->merchantOf($user)) ? max(0.0, round($this->merchant->balance($m), 2)) : 0.0,
            default => max(0.0, round($this->referral->balance($user), 2)),
        };
    }

    /** Can this member use the feature at all (and if not, why)? @return null|string reason key */
    public function senderBlock(User $sender): ?string
    {
        if (! PayoutSettings::enabled() || ! PayoutSettings::peerEnabled()) {
            return 'unavailable';
        }
        if (! $sender->is_active || PayoutFreeze::isFrozen($sender->id)) {
            return 'account_restricted';
        }
        if ($sender->created_at !== null && $sender->created_at->gt(now()->subDays(PayoutSettings::peerSenderAgeDays()))) {
            return 'account_too_new';
        }
        if ($this->recentlyReceived($sender)) {
            return 'received_recently';
        }
        if (PayoutSettings::peerOnlyWhenUnsupported() && ! $this->hasNoRail($sender)) {
            return 'has_own_rail';
        }

        return null;
    }

    /** True when payouts cannot reach this member's country today (the people this feature is for). */
    public function hasNoRail(User $user): bool
    {
        $country = strtoupper((string) $user->country_code);
        if ($country === '') {
            return false;
        }

        return app(RailAdvisor::class)->advise($user, $country)['verdict'] === 'none_available';
    }

    /** Receiver must be able to actually be paid out. A single answer: true/false, never a reason. */
    public function canReceive(User $recipient, ?User $from = null): bool
    {
        if (! $recipient->is_active || ($from !== null && $recipient->id === $from->id) || PayoutFreeze::isFrozen($recipient->id)) {
            return false;
        }
        if ($this->kyc->currentLevel($recipient) < PayoutSettings::peerRecipientKyc()) {
            return false;
        }
        if ($this->recentlySent($recipient)) {
            return false;
        }

        return $this->usableAccount($recipient) !== null;
    }

    /** A verified account on a rail that is switched on and healthy for its country right now. */
    public function usableAccount(User $user): ?PayoutAccount
    {
        return PayoutAccount::where('user_id', $user->id)->where('is_verified', true)->get()->first(function (PayoutAccount $a) {
            $rail = PayoutRailRegistry::railForProvider($a->provider);

            return $rail !== null && $this->payouts->gatewayFor($a->provider) !== null
                && $this->registry->isEnabled(strtoupper((string) $a->country), $rail);
        });
    }

    /** @throws PayoutException */
    public function send(User $sender, string $recipientEmail, float $usd, string $bucket, ?string $note = null): EarningsTransfer
    {
        $usd = round($usd, 2);
        if (! in_array($bucket, self::BUCKETS, true)) {
            throw new PayoutException(__('payouts.peer.err.bucket'));
        }
        if ($reason = $this->senderBlock($sender)) {
            throw new PayoutException(__('payouts.peer.block.'.$reason));
        }
        $key = 'peer-send:'.$sender->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new PayoutException(__('payouts.peer.err.slow_down'));
        }
        RateLimiter::hit($key, 3600);

        $recipient = User::query()->where('email', mb_strtolower(trim($recipientEmail)))->first();
        if ($recipient === null || ! $this->canReceive($recipient, $sender)) {
            throw new PayoutException(__('payouts.peer.err.cannot_receive'));
        }
        if ($usd < PayoutSettings::peerMinUsd()) {
            throw new PayoutException(__('payouts.peer.err.min', ['amount' => number_format(PayoutSettings::peerMinUsd(), 2)]));
        }
        if ($usd > PayoutSettings::peerMaxUsd()) {
            throw new PayoutException(__('payouts.peer.err.max', ['amount' => number_format(PayoutSettings::peerMaxUsd(), 2)]));
        }
        if ($usd > $this->available($sender, $bucket)) {
            throw new PayoutException(__('payouts.peer.err.balance'));
        }

        $transfer = Cache::lock('peer-user:'.$sender->id, 10)->block(5, fn () => DB::transaction(function () use ($sender, $recipient, $usd, $bucket, $note) {
            // Limits are re-read under the sender's lock so two parallel sends cannot both squeeze under a cap.
            if (EarningsTransfer::where('sender_id', $sender->id)->where('status', EarningsTransfer::PENDING)->count() >= PayoutSettings::peerMaxPending()) {
                throw new PayoutException(__('payouts.peer.err.too_many_open'));
            }
            $sent30 = (float) EarningsTransfer::where('sender_id', $sender->id)->whereIn('status', [EarningsTransfer::PENDING, EarningsTransfer::ACCEPTED])
                ->where('created_at', '>=', now()->subDays(30))->sum('amount_usd');
            if ($sent30 + $usd > PayoutSettings::peerSender30dUsd() + 0.00001) {
                throw new PayoutException(__('payouts.peer.err.sender_limit'));
            }
            $received30 = (float) EarningsTransfer::where('recipient_id', $recipient->id)->whereIn('status', [EarningsTransfer::PENDING, EarningsTransfer::ACCEPTED])
                ->where('created_at', '>=', now()->subDays(30))->sum('amount_usd');
            if ($received30 + $usd > PayoutSettings::peerRecipient30dUsd() + 0.00001) {
                throw new PayoutException(__('payouts.peer.err.cannot_receive')); // same words: do not reveal the receiver's totals
            }

            $transfer = EarningsTransfer::create([
                'reference' => 'peer-'.Str::lower(Str::random(20)),
                'sender_id' => $sender->id, 'recipient_id' => $recipient->id,
                'source_bucket' => $bucket, 'amount_usd' => $usd,
                'note' => $note !== null ? Str::limit(strip_tags(trim($note)), 200, '') : null,
                'status' => EarningsTransfer::PENDING,
                'expires_at' => now()->addHours(PayoutSettings::peerExpiryHours()),
            ]);
            $this->debitSender($transfer, $sender);

            return $transfer;
        }));

        Auditor::log('payout.peer_created', 'EarningsTransfer', $transfer->id, ['sender' => $sender->id, 'recipient' => $recipient->id, 'usd' => $usd, 'bucket' => $bucket]);
        $recipient->notify(new EarningsTransferNotice($transfer->id, 'requested'));

        return $transfer;
    }

    /** @throws PayoutException */
    public function accept(User $recipient, EarningsTransfer $transfer): EarningsTransfer
    {
        if ($transfer->recipient_id !== $recipient->id) {
            throw new PayoutException(__('payouts.peer.err.not_yours'));
        }
        if (! $this->canReceive($recipient)) {
            throw new PayoutException(__('payouts.peer.err.accept_blocked'));
        }

        DB::transaction(function () use ($transfer, $recipient) {
            $moved = EarningsTransfer::where('id', $transfer->id)->where('status', EarningsTransfer::PENDING)->where('expires_at', '>', now())
                ->update(['status' => EarningsTransfer::ACCEPTED, 'resolved_at' => now()]);
            if ($moved !== 1) {
                throw new PayoutException(__('payouts.peer.err.not_open'));
            }
            $this->referral->transferIn($recipient, (float) $transfer->amount_usd, 'peer-in:'.$transfer->reference, 'Received from a member', $transfer->sender);
        });

        $transfer->refresh();
        Auditor::log('payout.peer_accepted', 'EarningsTransfer', $transfer->id, ['recipient' => $recipient->id, 'usd' => (float) $transfer->amount_usd]);
        $transfer->sender?->notify(new EarningsTransferNotice($transfer->id, 'accepted'));

        return $transfer;
    }

    /** The receiver says no. @throws PayoutException */
    public function decline(User $recipient, EarningsTransfer $transfer): EarningsTransfer
    {
        abort_unless($transfer->recipient_id === $recipient->id, 403);

        return $this->close($transfer, EarningsTransfer::DECLINED, 'declined');
    }

    /** The sender takes it back before the receiver has accepted. @throws PayoutException */
    public function cancel(User $sender, EarningsTransfer $transfer): EarningsTransfer
    {
        abort_unless($transfer->sender_id === $sender->id, 403);

        return $this->close($transfer, EarningsTransfer::CANCELLED, 'cancelled');
    }

    /** Return every transfer nobody answered in time. @return int number returned */
    public function expireDue(): int
    {
        $n = 0;
        EarningsTransfer::where('status', EarningsTransfer::PENDING)->where('expires_at', '<=', now())->orderBy('id')->limit(500)->get()
            ->each(function (EarningsTransfer $t) use (&$n) {
                try {
                    $this->close($t, EarningsTransfer::EXPIRED, 'expired');
                    $n++;
                } catch (PayoutException) {
                    // answered a moment ago by someone else: nothing to do
                } catch (\Throwable $e) {
                    // One bad transfer (e.g. the sender's merchant account was removed) must never stop the rest from being returned.
                    report($e);
                }
            });

        return $n;
    }

    /** Pending -> terminal state + give the money back to the sender, atomically and at most once. */
    private function close(EarningsTransfer $transfer, string $to, string $notice): EarningsTransfer
    {
        DB::transaction(function () use ($transfer, $to) {
            $moved = EarningsTransfer::where('id', $transfer->id)->where('status', EarningsTransfer::PENDING)->update(['status' => $to, 'resolved_at' => now()]);
            if ($moved !== 1) {
                throw new PayoutException(__('payouts.peer.err.not_open'));
            }
            $this->returnToSender($transfer);
        });

        $transfer->refresh();
        Auditor::log('payout.peer_'.$notice, 'EarningsTransfer', $transfer->id, ['usd' => (float) $transfer->amount_usd]);
        $notice !== 'cancelled' && $transfer->sender?->notify(new EarningsTransferNotice($transfer->id, $notice));

        return $transfer;
    }

    private function debitSender(EarningsTransfer $t, User $sender): void
    {
        $desc = 'Transfer to a member (held until accepted)';
        try {
            if ($t->source_bucket === 'merchant') {
                $m = $this->merchantOf($sender) ?? throw new PayoutException(__('payouts.peer.err.balance'));
                $this->merchant->transferOut($m, (float) $t->amount_usd, 'peer-out:'.$t->reference, $desc);
            } else {
                $this->referral->transferOut($sender, (float) $t->amount_usd, 'peer-out:'.$t->reference, $desc);
            }
        } catch (\RuntimeException $e) {
            if ($e instanceof PayoutException) {
                throw $e;
            }
            throw new PayoutException(__('payouts.peer.err.balance'));
        }
    }

    private function returnToSender(EarningsTransfer $t): void
    {
        $desc = 'Transfer returned';
        if ($t->source_bucket === 'merchant') {
            $m = $this->merchantOf($t->sender) ?? throw new \RuntimeException('Merchant account missing for a transfer return.');
            $this->merchant->release($m, (float) $t->amount_usd, 'peer-return:'.$t->reference, $desc);
        } else {
            $this->referral->release($t->sender, (float) $t->amount_usd, 'peer-return:'.$t->reference, $desc);
        }
    }

    private function merchantOf(?User $user): ?Merchant
    {
        return $user?->merchantAccount;
    }

    private function recentlyReceived(User $u): bool
    {
        return EarningsTransfer::where('recipient_id', $u->id)->where('status', EarningsTransfer::ACCEPTED)->where('resolved_at', '>=', now()->subDays(30))->exists();
    }

    private function recentlySent(User $u): bool
    {
        return EarningsTransfer::where('sender_id', $u->id)->whereIn('status', [EarningsTransfer::PENDING, EarningsTransfer::ACCEPTED])->where('created_at', '>=', now()->subDays(30))->exists();
    }

    /** Masked display of a member for the other side of a transfer ("Ada O."). */
    public static function maskedName(?User $u): string
    {
        $parts = preg_split('/\s+/', trim((string) $u?->name)) ?: [];
        $first = $parts[0] ?? '';

        return $first === '' ? __('payouts.peer.a_member') : $first.(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : '');
    }
}
