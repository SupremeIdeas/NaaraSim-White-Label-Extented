<?php

namespace App\Support\Tours;

use App\Models\Tour;
use App\Models\TourProgress;
use App\Models\User;
use App\Support\Notifications\Notify;
use Illuminate\Support\Facades\DB;

final class TourProgressService
{
    public static function for(User $u, Tour $t): ?TourProgress
    {
        return TourProgress::where('user_id', $u->id)->where('tour_key', $t->key)->first();
    }

    /** The tour to offer on this page, or null: not seen/finished at the current version, window open, auto tips on, auto-start allowed. */
    public static function candidate(User $u, string $routeName): ?Tour
    {
        if (! TourWindow::autoEnabled($u)) {
            return null;
        }
        foreach (TourRegistry::eligible($u) as $t) {
            if ($t->entry_route !== $routeName || ! $t->auto_start) {
                continue;
            }
            $p = self::for($u, $t);
            // Never re-prompt a finished, skipped or dismissed tour unless an admin published a newer version.
            if ($p && $p->tour_version >= $t->version && in_array($p->status, ['completed', 'skipped', 'dismissed'], true)) {
                continue;
            }

            return $t;
        }

        return null;
    }

    public static function start(User $u, Tour $t, string $mode): void
    {
        $mode = $mode === 'quick' ? 'quick' : 'full';
        $p = self::for($u, $t) ?? new TourProgress(['user_id' => $u->id, 'tour_key' => $t->key]);
        $replay = $p->exists && $p->status !== 'not_started';
        $p->fill(['tour_version' => $t->version, 'status' => 'in_progress', 'last_step' => 0, 'last_mode' => $mode, 'started_at' => now()]);
        if ($replay) {
            $mode === 'quick' ? $p->quick_replay_count++ : $p->replay_count++;
        }
        $p->save();
        self::count($t->key, '', $replay ? 'replay' : 'started');
    }

    public static function step(User $u, Tour $t, int $index, string $anchor, bool $skipped = false): void
    {
        TourProgress::where('user_id', $u->id)->where('tour_key', $t->key)->update(['last_step' => max(0, $index)]);
        self::count($t->key, $anchor, $skipped ? 'step_skipped' : 'step_viewed');
    }

    public static function missing(Tour $t, string $anchor): void
    {
        self::count($t->key, $anchor, 'step_missing');   // surfaced in Tour Studio; the engine already skipped it
    }

    public static function finish(User $u, Tour $t, string $status): void
    {
        $status = in_array($status, ['completed', 'skipped', 'dismissed'], true) ? $status : 'dismissed';
        TourProgress::updateOrCreate(['user_id' => $u->id, 'tour_key' => $t->key], ['tour_version' => $t->version, 'status' => $status] + ($status === 'completed' ? ['completed_at' => now()] : []));
        self::count($t->key, '', $status === 'completed' ? 'completed' : 'dismissed');
        if ($status === 'completed') {
            Notify::event($u, 'tour.completed', ['tour' => $t->title], dedupe: 'tour-'.$t->key.'-v'.$t->version);
        }
    }

    public static function resetAll(User $u): void
    {
        TourProgress::where('user_id', $u->id)->delete();
    }

    public static function count(string $tourKey, string $anchor, string $event): void
    {
        $key = ['day' => now()->toDateString(), 'tour_key' => $tourKey, 'anchor' => $anchor, 'event' => $event];
        DB::table('tour_events')->insertOrIgnore($key + ['total' => 0]);
        DB::table('tour_events')->where($key)->increment('total');
    }
}
