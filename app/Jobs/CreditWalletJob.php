<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\TopUpReceiptNotification;
use App\Services\Wallet\WalletService;
use App\Support\Mailer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Credits a verified wallet top-up (blueprint Sections 14.2 & 19.3).
 *
 * Exactly-once is guaranteed two ways: ShouldBeUnique keeps duplicate jobs off
 * the queue, and WalletService credits idempotently on the reference — so even
 * if the webhook is delivered twice (or the job runs twice), the money moves
 * once.
 */
class CreditWalletJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public string $gateway,
        public string $reference,
        public int $userId,
        public float $amount,
        public string $currency,
    ) {}

    public function uniqueId(): string
    {
        return "credit:{$this->gateway}:{$this->reference}";
    }

    public function handle(WalletService $wallet): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            return;
        }

        // Unified USD Wallet (Part B): every top-up converts to USD — the one
        // spendable balance — regardless of what currency was actually paid.
        // A verified payment must NEVER silently fail to credit, so a truly
        // unrecognized currency (creditTopUp() only converts CurrencyService's
        // known list) is alerted for manual reconciliation rather than
        // crash-looping the queue or silently guessing a 1:1 rate.
        try {
            $txn = $wallet->creditTopUp($user, $this->amount, $this->currency, [
                'reference' => "topup:{$this->gateway}:{$this->reference}",
                'description' => "Wallet top-up via {$this->gateway}",
            ]);
        } catch (\InvalidArgumentException $e) {
            AlertAdminJob::dispatch(
                code: 'topup_uncreditable_currency',
                message: "Verified {$this->gateway} top-up for user {$this->userId} could not be credited: {$e->getMessage()} — reconcile manually.",
                context: ['user_id' => $this->userId, 'gateway' => $this->gateway, 'reference' => $this->reference, 'amount' => $this->amount, 'currency' => $this->currency],
            );

            return;
        }

        // Receipt email — only on a genuinely new credit (idempotent replays
        // return the existing row and must not re-email). Best-effort.
        if ($txn->wasRecentlyCreated) {
            Mailer::notify($user, new TopUpReceiptNotification(
                $this->amount,
                $this->currency,
                $this->gateway,
                (float) $txn->balance_after,
            ));
        }
    }
}
