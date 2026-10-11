<?php

namespace App\Services\Payouts\Rail;

use App\Models\PayoutAccount;
use App\Models\PayoutGuideEvent;
use Illuminate\Support\Facades\DB;

/** Admin funnel for the Rail Guide (Addendum B §7). Ids only; no PII. */
class RailGuideReport
{
    /** @return array<string, mixed> */
    public function build(int $days = 30): array
    {
        $since = now()->subDays($days);
        $events = PayoutGuideEvent::where('created_at', '>=', $since);

        $viewers = (clone $events)->where('event', 'guide_viewed')->whereNotNull('user_id')->distinct()->pluck('user_id');
        $recommended = (clone $events)->where('event', 'rail_recommended')->get(['user_id', 'rail', 'created_at'])->groupBy('user_id')->map->last();
        $selected = (clone $events)->where('event', 'rail_selected')->get(['user_id', 'rail', 'country']);

        // "Followed the recommendation" = picked the rail we recommended to them.
        $followed = $selected->filter(fn ($s) => isset($recommended[$s->user_id]) && $recommended[$s->user_id]->rail === $s->rail)->pluck('user_id')->unique()->count();
        $withRec = $recommended->count();

        $verified = $viewers->isEmpty() ? 0 : PayoutAccount::whereIn('user_id', $viewers)->where('is_verified', true)->distinct()->count('user_id');

        return [
            'days' => $days,
            'viewers' => $viewers->count(),
            'recommended_to' => $withRec,
            'selections_by_rail' => $selected->groupBy('rail')->map->count()->sortDesc()->all(),
            'selections_by_country' => $selected->groupBy('country')->map->count()->sortDesc()->take(10)->all(),
            'followed_pct' => $withRec > 0 ? round($followed / $withRec * 100, 1) : null,
            'global_acks' => (clone $events)->where('event', 'global_ack_confirmed')->count(),
            'conversion_pct' => $viewers->count() > 0 ? round($verified / $viewers->count() * 100, 1) : null,
            'notify_by_country' => (clone $events)->where('event', 'blocked_notify_requested')->select('country', DB::raw('COUNT(DISTINCT user_id) as n'))->groupBy('country')->orderByDesc('n')->pluck('n', 'country')->all(),
        ];
    }
}
