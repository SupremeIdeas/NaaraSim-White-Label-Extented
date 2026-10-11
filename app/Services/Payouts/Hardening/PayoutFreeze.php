<?php

namespace App\Services\Payouts\Hardening;

use App\Jobs\AlertAdminJob;
use App\Models\PayoutRequest;
use App\Models\PayoutUserFreeze;
use App\Models\User;
use App\Services\Payouts\PayoutService;
use App\Support\Auditor;

/**
 * "This wasn't me" and admin freezes (Addendum D-3.5). Freezing a payee stops new payouts at the
 * door (PayoutAdmission), holds anything the Guardian would still approve (gate G1), cancels what is
 * still cancellable, and revokes API tokens. Releasing is an admin action with a note.
 */
class PayoutFreeze
{
    public static function isFrozen(int $userId): bool
    {
        return PayoutUserFreeze::query()->where('user_id', $userId)->whereNull('released_at')->exists();
    }

    /** @return int number of requests cancelled */
    public function freeze(User $user, string $reason = 'not_me', ?User $by = null, ?string $note = null): int
    {
        PayoutUserFreeze::updateOrCreate(
            ['user_id' => $user->id],
            ['reason' => $reason, 'frozen_by' => $by?->id, 'note' => $note, 'frozen_at' => now(), 'released_at' => null],
        );

        $cancelled = 0;
        PayoutRequest::query()->where('user_id', $user->id)->whereIn('status', [PayoutRequest::PENDING, PayoutRequest::AWAITING_FUNDS, PayoutRequest::APPROVED])
            ->get()->each(function (PayoutRequest $r) use (&$cancelled) {
                if (app(PayoutService::class)->cancel($r, 'Cancelled: account protection')) {
                    $cancelled++;
                }
            });

        $user->tokens()->delete();
        Auditor::log('payout.user_frozen', 'User', $user->id, ['reason' => $reason, 'by' => $by?->id, 'cancelled' => $cancelled]);
        AlertAdminJob::dispatch(
            code: 'payout_user_frozen',
            message: "Payouts for user #{$user->id} were frozen ({$reason}); {$cancelled} pending request(s) were cancelled. The user should reset their password.",
            context: ['user_id' => $user->id, 'reason' => $reason],
        );

        return $cancelled;
    }

    public function release(User $user, User $admin, string $note): void
    {
        abort_unless($admin->hasRole('super_admin'), 403);
        PayoutUserFreeze::where('user_id', $user->id)->update(['released_at' => now(), 'note' => $note]);
        Auditor::log('payout.user_unfrozen', 'User', $user->id, ['by' => $admin->id, 'note' => $note]);
    }
}
