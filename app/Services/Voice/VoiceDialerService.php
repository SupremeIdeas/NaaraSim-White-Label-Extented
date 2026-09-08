<?php

namespace App\Services\Voice;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Jobs\LogVoiceCdrJob;
use App\Models\Setting;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\Pricing\PricingEngine;
use App\Services\SMS\VoiceProviderInterface;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * VoiceDialerService — the money spine of the in-browser dialer (Live Voice —
 * Part B). It follows the SAME discipline as every other money path
 * (money-safety rules 1–7):
 *
 *   1. Quote the LIVE per-minute retail through PricingEngine before connecting
 *      — no price literals, cost never exposed.
 *   2. Pre-authorise (hold) a funded block of minutes with an ATOMIC wallet
 *      debit (WalletService → lockForUpdate + balance_before/after), so the
 *      wallet can never go negative mid-call. The funded seconds also become the
 *      TwiML <Dial timeLimit>, a hard server-side hangup Twilio enforces even if
 *      the browser misbehaves.
 *   3. Settle on hang-up: bill only the minutes actually used and REFUND the
 *      rest — never charge without delivering (a call that never connects is
 *      refunded in full). Settlement is idempotent (guarded by settled_at under
 *      a row lock) so the status webhook and a client hang-up can't double-bill.
 */
class VoiceDialerService
{
    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly WalletService $wallet,
    ) {
    }

    /** Hard ceiling on a single call's funded block (admin-tunable). */
    private function maxMinutes(): int
    {
        return max(1, (int) Setting::getValue('voice.max_call_minutes', 60));
    }

    private function voice(): VoiceProviderInterface
    {
        return app('number.twilio');
    }

    /**
     * Live quote for a destination WITHOUT charging: the retail per-minute rate
     * and how many whole minutes the user's wallet can currently fund. Cost is
     * NEVER included in the response (rule 1.2).
     *
     * @return array{retail_per_min: float, funded_minutes: int, balance: float}
     */
    public function quote(User $user, string $destination): array
    {
        $cost = $this->voice()->voiceRate($destination);
        $retail = $this->pricing->calculateVoiceRetail($cost, 'twilio');

        $balance = round((float) ($user->wallet?->usd_balance ?? 0), 4);
        // + epsilon so float noise (1.40 / 0.14 = 9.999…) doesn't shave a minute.
        $funded = $retail > 0 ? (int) floor($balance / $retail + 1e-9) : 0;
        $funded = min($funded, $this->maxMinutes());

        return [
            'retail_per_min' => $retail,
            'funded_minutes' => $funded,
            'balance' => $balance,
        ];
    }

    /**
     * Begin a call: quote live, then pre-authorise the funded block with an
     * atomic wallet debit and open a VoiceCall row. Throws on a bad number or a
     * balance too low to fund even one minute — WITHOUT charging.
     */
    public function begin(User $user, string $destination): VoiceCall
    {
        $destination = trim($destination);
        if (! preg_match('/^\+[1-9]\d{6,14}$/', $destination)) {
            throw new SmsException('Enter a valid number in international format, e.g. +2348012345678.');
        }

        $cost = $this->voice()->voiceRate($destination);
        $retail = $this->pricing->calculateVoiceRetail($cost, 'twilio');

        $balance = round((float) ($user->wallet?->usd_balance ?? 0), 4);
        // + epsilon so float noise (1.40 / 0.14 = 9.999…) doesn't shave a minute.
        $funded = $retail > 0 ? (int) floor($balance / $retail + 1e-9) : 0;
        $funded = min($funded, $this->maxMinutes());

        if ($funded < 1) {
            throw new InsufficientBalanceException($user->id, 'USD', $retail, $balance);
        }

        $hold = round($funded * $retail, 4);
        $ref = 'voice-hold:'.$user->id.':'.Str::uuid();

        // Atomic authorization hold — wallet can't go negative during the call.
        $this->wallet->debit($user, $hold, 'USD', [
            'reference' => $ref,
            'description' => 'Call authorization ('.$funded.' min)',
        ]);

        return VoiceCall::create([
            'user_id' => $user->id,
            'destination' => $destination,
            'provider' => 'twilio',
            'status' => VoiceCall::STATUS_CONNECTING,
            'retail_per_min' => $retail,
            'provider_rate' => $cost, // hidden from users
            'minutes_authorized' => $funded,
            'amount_held' => $hold,
            'hold_reference' => $ref,
        ]);
    }

    /**
     * The funded ceiling in SECONDS — what the TwiML <Dial timeLimit> is set to
     * so Twilio hard-stops the call at exactly what the hold covers.
     */
    public function fundedSeconds(VoiceCall $call): int
    {
        return $call->minutes_authorized * 60;
    }

    /**
     * Settle a finished call: bill the minutes used (min 1 once connected, capped
     * at the funded block) and refund the rest. Idempotent — a row lock + the
     * settled_at guard mean the status webhook and a client hang-up can both call
     * this safely. A call that never connected is refunded in full.
     */
    public function settle(VoiceCall $call, int $durationSeconds, string $status): VoiceCall
    {
        return DB::transaction(function () use ($call, $durationSeconds, $status) {
            /** @var VoiceCall $locked */
            $locked = VoiceCall::whereKey($call->getKey())->lockForUpdate()->first();

            if ($locked->settled_at !== null) {
                return $locked; // already settled — never double-bill
            }

            $connected = in_array($status, [
                VoiceCall::STATUS_COMPLETED, VoiceCall::STATUS_IN_PROGRESS, 'answered',
            ], true) && $durationSeconds > 0;

            $billed = 0;
            if ($connected) {
                $billed = max(1, (int) ceil($durationSeconds / 60));
                $billed = min($billed, $locked->minutes_authorized);
            }

            $charged = round($billed * (float) $locked->retail_per_min, 4);
            $refund = round((float) $locked->amount_held - $charged, 4);

            if ($refund > 0) {
                $this->wallet->refund($locked->user, $refund, 'USD', [
                    'reference' => 'voice-refund:'.$locked->id,
                    'description' => 'Unused call minutes refunded',
                    // The upfront debit was an internal hold, not a purchase the
                    // user knowingly made — don't email a "refund" for the change.
                    'notify' => false,
                ]);
            }

            $locked->update([
                'status' => $connected ? VoiceCall::STATUS_COMPLETED : ($status === 'failed' ? VoiceCall::STATUS_FAILED : VoiceCall::STATUS_NO_ANSWER),
                'minutes_billed' => $billed,
                'amount_charged' => $charged,
                'refunded' => max(0, $refund),
                'duration_seconds' => $durationSeconds,
                'settled_at' => now(),
            ]);

            LogVoiceCdrJob::dispatch($locked->id);

            return $locked;
        });
    }
}
