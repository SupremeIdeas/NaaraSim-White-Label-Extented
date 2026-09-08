<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertView;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Picks the single most-important login notice a given user should see right
 * now, honouring audience targeting, the per-user view cap and dismissal. Also
 * records a view (atomically) and a dismissal.
 */
class AlertService
{
    public function nextFor(User $user): ?Alert
    {
        $candidates = Alert::where('is_active', true)
            ->orderByDesc('priority')->orderByDesc('id')
            ->get()
            ->filter(fn (Alert $a) => $a->isLive());

        $views = AlertView::where('user_id', $user->id)
            ->whereIn('alert_id', $candidates->pluck('id'))
            ->get()->keyBy('alert_id');

        foreach ($candidates as $alert) {
            if (! $this->audienceMatches($alert, $user)) {
                continue;
            }
            if ($alert->trigger === 'first_registration' && ! $this->isNew($alert, $user)) {
                continue;
            }

            $view = $views->get($alert->id);
            if ($view && $view->dismissed_at) {
                continue; // dismissed → never again
            }
            $seen = $view->views ?? 0;
            if ($alert->max_views > 0 && $seen >= $alert->max_views) {
                continue; // hit the cap
            }

            return $alert;
        }

        return null;
    }

    private function audienceMatches(Alert $alert, User $user): bool
    {
        return match ($alert->audience) {
            'new' => $this->isNew($alert, $user),
            'old' => ! $this->isNew($alert, $user),
            default => true,
        };
    }

    private function isNew(Alert $alert, User $user): bool
    {
        return $user->created_at && $user->created_at->gte(now()->subDays(max(1, $alert->new_days)));
    }

    /** Count one view (atomic upsert). */
    public function recordView(Alert $alert, User $user): void
    {
        DB::transaction(function () use ($alert, $user) {
            $view = AlertView::lockForUpdate()->firstOrNew(['alert_id' => $alert->id, 'user_id' => $user->id]);
            $view->views = ($view->views ?? 0) + 1;
            $view->save();
        });
    }

    public function dismiss(Alert $alert, User $user): void
    {
        AlertView::updateOrCreate(
            ['alert_id' => $alert->id, 'user_id' => $user->id],
            ['dismissed_at' => now()],
        );
    }
}
