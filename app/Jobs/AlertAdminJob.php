<?php

namespace App\Jobs;

use App\Models\ErrorLog;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Support\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Records a high-severity operational alert (blueprint money-safety: failed
 * money actions "refund + alert") AND delivers it to admins in near-real-time
 * (BUILD-5 §3). The durable sink is the error_logs row; on top of that we fan
 * out to every admin/super-admin via web push (instant) and email (the minimum
 * guaranteed channel), so an alert nobody sees for hours is no longer a false
 * sense of security.
 *
 * Delivery is best-effort (never throws — this job runs on money paths) and
 * throttled per alert code so a flapping provider can't storm admins: the log
 * row is always written, but the push/email fan-out fires at most once per code
 * within a short cooldown.
 */
class AlertAdminJob implements ShouldQueue
{
    use Queueable;

    /** How long to suppress repeat push/email for the SAME alert code. */
    private const COOLDOWN_MINUTES = 10;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $code,
        public string $message,
        public array $context = [],
        public string $severity = 'critical',
    ) {}

    public function handle(): void
    {
        // Durable sink — always written, every time, un-throttled.
        ErrorLog::create([
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
            'severity' => $this->severity,
        ]);

        Log::critical("[$this->code] $this->message", $this->context);

        // Near-real-time fan-out — throttled per code so a flapping alert can't
        // spam admins, and best-effort so a delivery failure never breaks a
        // money path.
        if (Cache::add('alert-fanout:'.$this->code, true, now()->addMinutes(self::COOLDOWN_MINUTES))) {
            $this->notifyAdmins();
        }
    }

    private function notifyAdmins(): void
    {
        try {
            $admins = User::role(['super_admin', 'admin'])->get();
        } catch (\Throwable) {
            return; // roles table unavailable in a bare context — nothing to do
        }

        $payload = [
            'title' => ucfirst($this->severity).': '.$this->code,
            'body' => $this->message,
            'url' => route('admin.dashboard'),
        ];

        foreach ($admins as $admin) {
            // Instant channel (web push).
            try {
                SendWebPushJob::dispatch($admin->id, $payload);
            } catch (\Throwable) {
                // best-effort
            }
            // Guaranteed channel (queued mail).
            try {
                Mailer::notify($admin, new AdminAlertNotification($this->code, $this->message, $this->context, $this->severity));
            } catch (\Throwable) {
                // best-effort
            }
        }
    }
}
