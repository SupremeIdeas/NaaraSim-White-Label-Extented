<?php

namespace App\Services\Crypto\Deposits;

use App\Jobs\AlertAdminJob;
use App\Models\Crypto\CryptoDeposit;
use App\Models\Crypto\CryptoLinkedWallet;
use App\Models\User;
use App\Services\Crypto\Chain\AbiDecoder;
use App\Services\Crypto\Chain\ChainRegistry;
use App\Services\Crypto\Support\DepositStatus;
use App\Services\Wallet\WalletService;

/**
 * Moves a recorded deposit forward: wait for finality, re-read the chain to verify it, attribute it to the member who linked the sending wallet, apply the limits,
 * then credit the wallet exactly once. Every step is a state-machine transition (one audited event each) and the wallet reference comes ONLY from the chain event,
 * so a replay, a double scan or a concurrent worker can never credit twice. Money-safety: nothing is credited unless the transfer is verified on a finalized block.
 */
class DepositProcessor
{
    public function __construct(private readonly ChainRegistry $registry, private readonly WalletService $wallet) {}

    /** Advance one deposit as far as it can go right now. */
    public function process(CryptoDeposit $dep): CryptoDeposit
    {
        $dep = $dep->refresh();
        if ($dep->state() === DepositStatus::Detected || $dep->state() === DepositStatus::Confirming) {
            $dep = $this->verify($dep);
        }
        if (in_array($dep->state(), [DepositStatus::Verified, DepositStatus::Unclaimed, DepositStatus::PausedHold], true)) {
            $dep = $this->decide($dep);
        }
        if ($dep->state() === DepositStatus::CreditPending) {
            $dep = $this->credit($dep);
        }

        return $dep;
    }

    private function verify(CryptoDeposit $dep): CryptoDeposit
    {
        $pool = $this->registry->pool((int) $dep->chain_id);
        if ($pool === null || ! $this->registry->watching((int) $dep->chain_id)) {
            return $dep;
        }
        $chain = \App\Models\Crypto\CryptoChain::where('chain_id', $dep->chain_id)->first();
        $client = $pool->primary();
        $head = $client->blockNumber();
        $safe = $chain->finality_mode === 'finalized' ? (int) \App\Services\Crypto\Chain\Units::hexToDec((string) ($client->getBlockByNumber('finalized')['number'] ?? '0x0')) : $head - (int) $chain->confirmations;
        if ((int) $dep->block_number > $safe) {
            return $dep->state() === DepositStatus::Detected ? $dep->transition(DepositStatus::Confirming, 'system', 'waiting for finality', ['block' => $dep->block_number, 'safe' => $safe]) : $dep;
        }
        // Re-read from the chain on every endpoint we have: the SAME transfer must still be in the SAME block.
        $read = function ($c) use ($dep) {
            $r = $c->getTransactionReceipt($dep->tx_hash);
            if ($r === null || strtolower((string) ($r['blockHash'] ?? '')) !== $dep->block_hash || hexdec((string) ($r['status'] ?? '0x0')) !== 1) {
                return null;
            }
            foreach ((array) ($r['logs'] ?? []) as $log) {
                $t = is_array($log) ? AbiDecoder::decodeTransfer($log) : null;
                if ($t !== null && $t['log_index'] === (int) $dep->log_index && $t['contract'] === strtolower((string) $dep->token->contract) && $t['to'] === $dep->to_address
                    && $t['from'] === $dep->from_address && $t['raw_amount'] === $dep->raw_amount) {
                    return 'ok';
                }
            }

            return null;
        };
        $check = $pool->crossCheck($read);
        if ($check['value'] === null) {
            return $dep->transition(DepositStatus::Reorged, 'system', 'the transfer is no longer on the canonical chain');
        }
        if ($check['agreed'] === false) {
            return $dep->state() === DepositStatus::Detected ? $dep->transition(DepositStatus::Confirming, 'system', 'endpoints disagree, will re-check') : $dep;
        }

        return $dep->transition(DepositStatus::Verified, 'system', 'finalized and verified', ['agreed' => $check['agreed']], ['verified_at' => now()]);
    }

    private function decide(CryptoDeposit $dep): CryptoDeposit
    {
        $token = $dep->token;
        if (! $this->registry->crediting((int) $dep->chain_id) || ! $token->enabled) {
            return $dep->state() === DepositStatus::PausedHold ? $dep : $dep->transition(DepositStatus::PausedHold, 'system', 'crediting is off for this chain or token', set: ['hold_reason' => 'crediting_off']);
        }
        $usd = (float) $dep->usd_amount;
        $uid = $dep->attributed_user_id ?: $this->ownerOf($dep);
        if ($uid === null) {
            return $dep->state() === DepositStatus::Unclaimed ? $dep : $dep->transition(DepositStatus::Unclaimed, 'system', 'the sending wallet is not linked to a member yet');
        }
        $dep = $dep->attributeTo((int) $uid);
        $hold = match (true) {
            $usd < (float) $token->min_usd => 'below_minimum',
            $usd > (float) $token->max_single_usd => 'above_max_single',
            $usd >= (float) $token->review_above_usd => 'review_threshold',
            $this->creditedLast24h((int) $uid) + $usd > (float) $token->user_daily_cap_usd => 'daily_cap',
            default => null,
        };
        if ($hold !== null) {
            if ($hold !== 'below_minimum') {   // an admin decision is needed: tell them now instead of waiting for someone to open the page
                AlertAdminJob::dispatch(code: 'crypto_deposit_held', message: 'A crypto deposit of $'.number_format($usd, 2).' ('.$token->symbol.') is waiting for review: '.str_replace('_', ' ', $hold).'. Open Admin, Crypto top-up.', context: ['deposit_id' => $dep->id, 'user_id' => (int) $uid]);
            }

            return $dep->transition(DepositStatus::HeldReview, 'system', 'needs an admin decision: '.$hold, set: ['hold_reason' => $hold]);
        }

        return $dep->transition(DepositStatus::CreditPending, 'system', 'attributed and within limits', set: ['credit_reference' => CryptoDeposit::referenceFor((int) $dep->chain_id, $dep->tx_hash, (int) $dep->log_index)]);
    }

    /** An admin releases a held deposit to be credited (audited: the actor is on the event). */
    public function approveHeld(CryptoDeposit $dep, int $adminId): CryptoDeposit
    {
        if ($dep->attributed_user_id === null) {
            throw new \DomainException('Attribute this deposit to a member before approving it.');
        }
        $dep = $dep->transition(DepositStatus::CreditPending, 'admin:'.$adminId, 'approved after review', set: ['credit_reference' => CryptoDeposit::referenceFor((int) $dep->chain_id, $dep->tx_hash, (int) $dep->log_index)]);

        return $this->credit($dep);
    }

    private function credit(CryptoDeposit $dep): CryptoDeposit
    {
        $user = User::find($dep->attributed_user_id);
        if ($user === null) {
            return $dep;
        }
        try {
            // Idempotent on the reference: a retry after a crash returns the existing ledger row instead of paying twice.
            $txn = $this->wallet->creditTopUp($user, (float) $dep->usd_amount, 'USD', ['reference' => $dep->credit_reference, 'description' => 'Crypto top-up ('.$dep->token->symbol.')']);
        } catch (\Throwable $e) {
            AlertAdminJob::dispatch(code: 'crypto_credit_failed', message: "On-chain deposit {$dep->id} verified but could not be credited: {$e->getMessage()}", context: ['deposit_id' => $dep->id, 'user_id' => $user->id]);

            return $dep;
        }

        // Receipt email, only for a genuinely new credit (a replay returns the existing ledger row). Best-effort: never blocks or undoes the credit.
        if ($txn->wasRecentlyCreated) {
            try {
                \App\Support\Mailer::notify($user, new \App\Notifications\TopUpReceiptNotification((float) $dep->usd_amount, 'USD', 'Crypto ('.$dep->token->symbol.')', (float) $txn->balance_after));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $dep->transition(DepositStatus::Credited, 'system', 'wallet credited', set: ['credited_wallet_txn_id' => $txn->id, 'credited_at' => now()]);
    }

    private function ownerOf(CryptoDeposit $dep): ?int
    {
        $w = CryptoLinkedWallet::active()->where('address', $dep->from_address)->first();

        return $w?->user_id;
    }

    private function creditedLast24h(int $userId): float
    {
        return (float) CryptoDeposit::where('attributed_user_id', $userId)->where('status', DepositStatus::Credited->value)->where('credited_at', '>=', now()->subDay())->sum('usd_amount');
    }
}
