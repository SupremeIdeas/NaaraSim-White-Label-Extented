<?php

namespace App\Console\Commands;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Setting;
use App\Models\VirtualNumber;
use App\Notifications\VirtualNumberRenewalNotification;
use App\Services\Wallet\WalletService;
use App\Services\WhatsApp\WhatsAppAutopilot;
use App\Support\Mailer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Monthly billing for permanent numbers (Naara Line). Run daily by the scheduler.
 *   - Charges every auto_renew subscription whose next_billing_date has arrived,
 *     from the user's wallet, then advances the date one month (idempotent per
 *     month) and resets the renewal-notice marker for the new cycle.
 *   - If the wallet is short, the number enters a grace period (past_due); if the
 *     user tops up before grace ends, the next run charges it and reactivates.
 *   - A number with auto_renew OFF is never charged: it simply ends cleanly on
 *     its next_billing_date (Prompt 10 — the customer's own opt-out, not a
 *     billing failure, so no grace period applies).
 *   - Once grace lapses (auto-renew path) — or a non-renewing number's date
 *     arrives — the number is released at the provider and marked expired (it
 *     moves to the dashboard Archive) — so we never keep paying a provider for
 *     a number the user isn't paying us for.
 *   - Advance notice (Prompt 10): once per billing cycle, every number due
 *     within the notice window gets a real heads-up — the actual charge date
 *     and amount for a renewing line, or the actual end date for one that
 *     opted out — via email and (if the user opted in) WhatsApp Autopilot's
 *     `renewal_reminder` template.
 * Money-safety: every charge is idempotent by "vnum-renew:{id}:{YYYY-MM}" and
 * runs through WalletService; no cost is ever exposed.
 */
class RenewVirtualNumbersCommand extends Command
{
    protected $signature = 'virtual:renew {--notice-days=3 : Advance-notice window in days}';

    protected $description = 'Charge due virtual-number subscriptions, release lapsed/opted-out ones, and send renewal notices.';

    public function handle(WalletService $wallet, WhatsAppAutopilot $whatsapp): int
    {
        $graceDays = (int) Setting::getValue('numbers.renewal_grace_days', 3);
        $noticeDays = max(1, (int) $this->option('notice-days'));
        $billed = 0;
        $endedByChoice = 0;
        $lapsed = 0;
        $notified = 0;

        // 1) Auto-renewing subscriptions due today: bill and roll the date forward.
        VirtualNumber::whereIn('status', ['active', 'past_due'])
            ->where('auto_renew', true)
            ->whereDate('next_billing_date', '<=', today())
            ->with('user')
            ->chunkById(100, function ($numbers) use ($wallet, $graceDays, &$billed) {
                foreach ($numbers as $vn) {
                    if (! $vn->user) {
                        continue;
                    }
                    // Keyed on the PERIOD being charged (next_billing_date), not the
                    // calendar month the command happens to run in — if a run is ever
                    // missed and this fires late (or twice) in the same real month, a
                    // now()-keyed reference would make WalletService's idempotency
                    // return the existing transaction with no new debit, while the
                    // code below still advances next_billing_date — silently forgiving
                    // a month's charge. Keying on the actual due date guarantees each
                    // distinct billing period is charged exactly once, however late.
                    $ref = "vnum-renew:{$vn->id}:".Carbon::parse($vn->next_billing_date)->format('Y-m');
                    try {
                        $wallet->debit($vn->user, (float) $vn->monthly_retail, 'USD', [
                            'reference' => $ref,
                            'description' => 'Virtual number monthly renewal',
                        ]);
                        $vn->forceFill([
                            'status' => 'active',
                            'expires_at' => null,
                            'next_billing_date' => Carbon::parse($vn->next_billing_date)->addMonthNoOverflow()->toDateString(),
                            // A new cycle needs a new notice — never let one paid month
                            // silently reuse the marker from the month before.
                            'renewal_notice_sent_at' => null,
                        ])->save();
                        $billed++;
                    } catch (InsufficientBalanceException) {
                        if ($vn->status !== 'past_due') {
                            $vn->forceFill([
                                'status' => 'past_due',
                                'expires_at' => now()->addDays($graceDays),
                            ])->save();
                        }
                    } catch (\Throwable $e) {
                        Log::warning("virtual:renew failed for {$vn->id}: ".$e->getMessage());
                    }
                }
            });

        // 2) Opted-out subscriptions whose paid period has run out: end cleanly,
        // no charge attempted, no grace — this is the customer's own choice, not
        // a payment failure.
        VirtualNumber::where('status', 'active')
            ->where('auto_renew', false)
            ->whereDate('next_billing_date', '<=', today())
            ->chunkById(100, function ($numbers) use (&$endedByChoice) {
                foreach ($numbers as $vn) {
                    try {
                        app("number.{$vn->provider}")->releaseNumber((string) $vn->sid);
                    } catch (\Throwable) {
                        // best-effort; the line ends regardless
                    }
                    $vn->forceFill(['status' => 'expired'])->save();
                    $endedByChoice++;
                }
            });

        // 3) Release numbers whose grace period has lapsed (auto-renew path only —
        // the opted-out path above never enters past_due at all).
        VirtualNumber::where('status', 'past_due')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($numbers) use (&$lapsed) {
                foreach ($numbers as $vn) {
                    try {
                        app("number.{$vn->provider}")->releaseNumber((string) $vn->sid);
                    } catch (\Throwable) {
                        // best-effort; we stop billing regardless
                    }
                    $vn->forceFill(['status' => 'expired'])->save();
                    $lapsed++;
                }
            });

        // 4) Advance notice — once per cycle, for anything due within the window,
        // whichever way it's headed (renewing and about to be charged, or opted
        // out and about to end).
        VirtualNumber::where('status', 'active')
            ->whereNull('renewal_notice_sent_at')
            ->whereNotNull('next_billing_date')
            ->whereBetween('next_billing_date', [today(), today()->addDays($noticeDays)])
            ->with('user')
            ->chunkById(100, function ($numbers) use ($whatsapp, &$notified) {
                foreach ($numbers as $vn) {
                    if (! $vn->user) {
                        continue;
                    }
                    Mailer::notify($vn->user, new VirtualNumberRenewalNotification($vn));
                    $whatsapp->notify($vn->user, 'renewal_reminder', [
                        $vn->user->name ?: 'there',
                        $vn->phone_number,
                        $vn->next_billing_date->format('M j'),
                    ]);
                    $vn->forceFill(['renewal_notice_sent_at' => now()])->save();
                    $notified++;
                }
            });

        $this->info("Renewed {$billed}, ended by choice {$endedByChoice}, released {$lapsed} lapsed, notified {$notified}.");

        return self::SUCCESS;
    }
}
