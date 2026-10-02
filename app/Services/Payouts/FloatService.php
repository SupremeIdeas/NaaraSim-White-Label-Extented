<?php

namespace App\Services\Payouts;

use App\Jobs\AlertAdminJob;
use App\Jobs\SendPayoutJob;
use App\Models\PayoutFloatBalance;
use App\Models\PayoutFloatMovement;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Treasury (Global Payout Layer Phase 4). Tracks the platform's pre-funded balance
 * at each provider so a payout is never sent into an empty account. Every change is
 * an append-only movement, idempotent on its reference; balances are only ever
 * changed under a row lock.
 *
 * An UNTRACKED rail (no balance row) behaves exactly as before — float tracking is
 * opt-in per provider+currency.
 */
class FloatService
{
    public function tracked(string $provider, string $currency): ?PayoutFloatBalance
    {
        return PayoutFloatBalance::where('provider', $provider)->where('currency', strtoupper($currency))->first();
    }

    /** Begin tracking a rail (idempotent). */
    public function track(string $provider, string $currency, float $openingBalance = 0, float $lowThreshold = 0, ?User $by = null): PayoutFloatBalance
    {
        $currency = strtoupper($currency);

        return DB::transaction(function () use ($provider, $currency, $openingBalance, $lowThreshold, $by) {
            $row = PayoutFloatBalance::firstOrCreate(['provider' => $provider, 'currency' => $currency], ['balance' => 0, 'low_threshold' => $lowThreshold]);
            if ($row->wasRecentlyCreated && $openingBalance > 0) {
                $this->move($row, 'adjustment', $openingBalance, 'opening:'.$provider.':'.$currency, 'Opening balance', null, $by);
            }

            return $row->refresh();
        });
    }

    /**
     * Money the admin put into the provider account. Releases `awaiting_funds`
     * payouts, oldest first, as far as the new balance reaches.
     */
    public function recordTopUp(string $provider, string $currency, float $amount, User $by, string $note, ?string $reference = null): PayoutFloatMovement
    {
        if ($amount <= 0) {
            throw new PayoutException('A top-up must be positive.');
        }
        $row = $this->tracked($provider, $currency) ?? $this->track($provider, $currency, 0, 0, $by);

        $movement = DB::transaction(fn () => $this->move($row, 'topup', $amount, 'topup:'.($reference ?? uniqid('', true)), $note, null, $by));
        Auditor::log('payout.float_topup', 'PayoutFloatBalance', $row->id, ['provider' => $provider, 'currency' => $currency, 'amount' => $amount, 'note' => $note]);

        $this->releaseAwaiting($provider, $currency);

        return $movement;
    }

    /** Admin correction (signed). A note is mandatory — it is the audit trail. */
    public function adjust(string $provider, string $currency, float $delta, User $by, string $note): PayoutFloatMovement
    {
        if (trim($note) === '' || $delta == 0.0) {
            throw new PayoutException('An adjustment needs a non-zero amount and a note.');
        }
        $row = $this->tracked($provider, $currency) ?? throw new PayoutException('That rail is not tracked.');

        $movement = DB::transaction(fn () => $this->move($row, 'adjustment', $delta, 'adj:'.uniqid('', true), $note, null, $by));
        Auditor::log('payout.float_adjusted', 'PayoutFloatBalance', $row->id, ['delta' => $delta, 'note' => $note]);
        $delta > 0 && $this->releaseAwaiting($provider, $currency);

        return $movement;
    }

    /** Align the tracked balance with what the provider itself reports (one `sync` movement for the difference). */
    public function syncTo(PayoutFloatBalance $row, float $providerBalance): ?PayoutFloatMovement
    {
        $movement = DB::transaction(function () use ($row, $providerBalance) {
            $locked = PayoutFloatBalance::whereKey($row->id)->lockForUpdate()->first();
            $delta = round($providerBalance - (float) $locked->balance, 4);
            $locked->forceFill(['last_synced_at' => now()])->save();

            return $delta == 0.0 ? null : $this->move($locked, 'sync', $delta, 'sync:'.$locked->id.':'.now()->format('YmdHis'), 'Provider-reported balance', null, null);
        });
        $movement !== null && $movement->amount > 0 && $this->releaseAwaiting($row->provider, $row->currency);

        return $movement;
    }

    /**
     * Use float for a payout that is about to be submitted. Idempotent per request.
     * Called inside send()'s claim transaction, so it can never be debited twice.
     */
    public function debitForPayout(PayoutRequest $request): void
    {
        $row = $this->tracked((string) $request->provider, $request->currency);
        if ($row === null) {
            return;
        }
        $locked = PayoutFloatBalance::whereKey($row->id)->lockForUpdate()->first();
        $this->move($locked, 'payout', -abs((float) $request->amount), 'payout:'.$request->id, null, $request->id, null);

        if ((float) $locked->refresh()->balance < (float) $locked->low_threshold) {
            $this->alertOnce("float-low:{$locked->provider}:{$locked->currency}", 'payout_float_low',
                "Payout float at {$locked->provider} ({$locked->currency}) is {$locked->balance}, below the {$locked->low_threshold} threshold. Top up to keep payouts flowing.",
                ['provider' => $locked->provider, 'currency' => $locked->currency, 'balance' => (float) $locked->balance]);
        }
    }

    /** The provider definitively did not pay: give the float back (idempotent; no-op if it was never used). */
    public function reverseDebit(PayoutRequest $request): void
    {
        $debit = PayoutFloatMovement::where('reference', 'payout:'.$request->id)->first();
        if ($debit === null || PayoutFloatMovement::where('reference', 'payout-reversal:'.$request->id)->exists()) {
            return;
        }
        $row = $this->tracked($debit->provider, $debit->currency);
        if ($row === null) {
            return;
        }
        DB::transaction(function () use ($row, $debit, $request) {
            $locked = PayoutFloatBalance::whereKey($row->id)->lockForUpdate()->first();
            $this->move($locked, 'payout_reversal', abs((float) $debit->amount), 'payout-reversal:'.$request->id, 'Payout did not complete', $request->id, null);
        });
        $this->releaseAwaiting($debit->provider, $debit->currency);
    }

    /**
     * May this request be sent now? Balance must cover it AND no OLDER request on the
     * same rail is still waiting for funds (first come, first served).
     */
    public function covers(PayoutRequest $request): bool
    {
        $row = $this->tracked((string) $request->provider, $request->currency);
        if ($row === null) {
            return true; // untracked rail
        }
        if (PayoutFloatMovement::where('reference', 'payout:'.$request->id)->exists()) {
            return true; // already funded
        }

        return (float) $row->balance >= (float) $request->amount && ! $this->olderWaiting($request);
    }

    public function olderWaiting(PayoutRequest $request): bool
    {
        return PayoutRequest::where('status', PayoutRequest::AWAITING_FUNDS)->where('provider', $request->provider)
            ->where('currency', $request->currency)->where('id', '<', $request->id)->exists();
    }

    /** Float that is already spoken for by older approved/waiting requests that have not drawn it yet. */
    public function committedAhead(PayoutRequest $request): float
    {
        return (float) PayoutRequest::whereIn('status', [PayoutRequest::APPROVED, PayoutRequest::AWAITING_FUNDS])
            ->where('provider', $request->provider)->where('currency', $request->currency)->where('id', '<', $request->id)->sum('amount');
    }

    /** Park a request that cannot be funded yet, and tell the admin (once an hour per rail). */
    public function markAwaiting(PayoutRequest $request): void
    {
        $request->forceFill(['status' => PayoutRequest::AWAITING_FUNDS])->save();
        $this->alertOnce("float-short:{$request->provider}:{$request->currency}", 'payout_awaiting_funds',
            "Payouts are waiting for funds at {$request->provider} ({$request->currency}). Top up the float; they resume automatically, oldest first.",
            ['provider' => $request->provider, 'currency' => $request->currency, 'payout_id' => $request->id]);
    }

    /**
     * Resume waiting payouts, oldest first, while the balance can fund them. Moves
     * awaiting_funds → approved with one compare-and-set per row, then queues the send
     * (which re-checks the float itself, so this can only ever under-release).
     *
     * @return int released
     */
    public function releaseAwaiting(string $provider, string $currency): int
    {
        $row = $this->tracked($provider, $currency);
        if ($row === null) {
            return 0;
        }
        $available = (float) $row->balance;
        $released = 0;

        PayoutRequest::where('status', PayoutRequest::AWAITING_FUNDS)->where('provider', $provider)->where('currency', strtoupper($currency))
            ->orderBy('id')->get()->each(function (PayoutRequest $r) use (&$available, &$released) {
                if ($available < (float) $r->amount) {
                    return false; // FIFO: do not let a smaller, younger one jump the queue
                }
                $won = PayoutRequest::whereKey($r->id)->where('status', PayoutRequest::AWAITING_FUNDS)->update(['status' => PayoutRequest::APPROVED, 'updated_at' => now()]);
                if ($won === 1) {
                    $available -= (float) $r->amount;
                    $released++;
                    SendPayoutJob::dispatch($r->id)->afterCommit();
                }
            });

        return $released;
    }

    /** One append-only movement + the balance update, idempotent on `$reference`. Caller holds the row lock / transaction. */
    private function move(PayoutFloatBalance $row, string $type, float $amount, string $reference, ?string $note, ?int $payoutId, ?User $by): PayoutFloatMovement
    {
        if (($existing = PayoutFloatMovement::where('reference', $reference)->first()) !== null) {
            return $existing;
        }
        $after = round((float) $row->balance + $amount, 4);
        $row->forceFill(['balance' => $after])->save();

        return PayoutFloatMovement::create([
            'provider' => $row->provider, 'currency' => $row->currency, 'type' => $type, 'amount' => $amount, 'balance_after' => $after,
            'payout_request_id' => $payoutId, 'reference' => $reference, 'note' => $note, 'created_by' => $by?->id,
        ]);
    }

    private function alertOnce(string $key, string $code, string $message, array $context): void
    {
        if (Cache::add("payout-alert:{$key}", 1, now()->addHour())) {
            AlertAdminJob::dispatch(code: $code, message: $message, context: $context);
        }
    }
}
