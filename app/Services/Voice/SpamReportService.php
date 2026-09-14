<?php

namespace App\Services\Voice;

use App\Models\Setting;
use App\Models\SpamBlockedCaller;
use App\Models\SpamReport;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Support\Carbon;

/**
 * Spam-report + auto-block (Prompt 11). A user can report a number as spam
 * from Contacts or the Dialer; once enough DISTINCT users report the same
 * number within a configurable window, it's blocked from being dialed
 * platform-wide — protecting wallets from expensive scam/premium-rate numbers
 * (the international dialer bills per minute, so a spam number is a real
 * money risk, not just an annoyance).
 *
 * No money path itself: reporting and blocking never touch the wallet. The
 * only money-adjacent effect is refusing a call BEFORE any hold is placed
 * (checked in VoiceDialerService::begin, defence in depth behind the Dialer's
 * own pre-check).
 */
class SpamReportService
{
    public function normalize(string $number): string
    {
        return (string) preg_replace('/\D/', '', $number);
    }

    private function threshold(): int
    {
        return max(1, (int) Setting::getValue('spam.report_threshold', 3));
    }

    private function windowDays(): int
    {
        return max(1, (int) Setting::getValue('spam.report_window_days', 30));
    }

    /**
     * Record a report (idempotent per reporter+number — reporting twice just
     * refreshes the reason/timestamp, never inflates the count) and auto-block
     * the number once distinct reporters within the window cross the
     * configured threshold.
     */
    public function report(string $number, User $reporter, string $source, ?string $reason = null): void
    {
        $msisdn = $this->normalize($number);
        if ($msisdn === '') {
            return;
        }

        SpamReport::updateOrCreate(
            ['msisdn' => $msisdn, 'reporter_user_id' => $reporter->id],
            ['phone_number' => $number, 'reason' => $reason, 'source' => $source],
        );

        Auditor::log('spam.reported', 'SpamReport', null, ['msisdn' => $msisdn, 'source' => $source]);

        if ($this->isBlocked($number)) {
            return; // already blocked — nothing more to do
        }

        $count = $this->reportCount($number);
        if ($count >= $this->threshold()) {
            SpamBlockedCaller::create([
                'msisdn' => $msisdn,
                'phone_number' => $number,
                'report_count_at_block' => $count,
                'source' => 'auto',
                'blocked_at' => now(),
            ]);
            Auditor::log('spam.auto_blocked', 'SpamBlockedCaller', null, ['msisdn' => $msisdn, 'report_count' => $count]);
        }
    }

    /** Distinct reporters for this number within the configured window. */
    public function reportCount(string $number): int
    {
        return SpamReport::where('msisdn', $this->normalize($number))
            ->where('created_at', '>=', Carbon::now()->subDays($this->windowDays()))
            ->distinct('reporter_user_id')
            ->count('reporter_user_id');
    }

    public function isBlocked(string $number): bool
    {
        return SpamBlockedCaller::where('msisdn', $this->normalize($number))->exists();
    }

    /** Admin: block a number directly, independent of the report threshold. */
    public function blockDirectly(string $number, ?int $adminUserId = null): SpamBlockedCaller
    {
        $msisdn = $this->normalize($number);

        $blocked = SpamBlockedCaller::updateOrCreate(
            ['msisdn' => $msisdn],
            [
                'phone_number' => $number,
                'report_count_at_block' => $this->reportCount($number),
                'source' => 'admin',
                'blocked_at' => now(),
            ],
        );
        Auditor::log('spam.admin_blocked', 'SpamBlockedCaller', null, ['msisdn' => $msisdn, 'admin_user_id' => $adminUserId]);

        return $blocked;
    }

    /** Admin: reverse a false-positive block. Reports stay on file. */
    public function unblock(string $number): void
    {
        $msisdn = $this->normalize($number);
        SpamBlockedCaller::where('msisdn', $msisdn)->delete();
        Auditor::log('spam.unblocked', 'SpamBlockedCaller', null, ['msisdn' => $msisdn]);
    }
}
