<?php

namespace App\Support\Tours;

use App\Models\Tour;
use App\Models\TourStep;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Tours as data. `defaults()` is the reviewable-in-git source (config/tours.php plus the master-only tour files that exist in this install);
 * `sync()` upserts it into the tables without ever overwriting a tour an admin has customised; `reset()` puts one back to the defaults.
 */
final class TourRegistry
{
    /** Config files that carry tours. Master-only ones are simply absent in a white-label install. */
    private const FILES = ['tours', 'tours_n2n', 'tours_business_suite'];

    /** @return array<string, array> key => definition */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::FILES as $f) {
            foreach ((array) config("$f.tours", []) as $key => $def) {
                $out[$key] = $def;
            }
        }

        return $out;
    }

    /** @return int number of tours created or updated */
    public static function sync(): int
    {
        if (! Schema::hasTable('tours')) {
            return 0;
        }
        $n = 0;
        foreach (self::defaults() as $key => $def) {
            $tour = Tour::where('key', $key)->first();
            if ($tour && $tour->is_customized) {
                continue;
            }
            self::write($key, $def, $tour);
            $n++;
        }
        // A tour removed from code (and not customised) is retired, never deleted: progress rows keep their history.
        Tour::whereNotIn('key', array_keys(self::defaults()))->where('is_customized', false)->update(['is_active' => false]);

        return $n;
    }

    public static function reset(string $key): bool
    {
        $def = self::defaults()[$key] ?? null;
        if (! $def) {
            return false;
        }
        self::write($key, $def, Tour::where('key', $key)->first(), true);

        return true;
    }

    private static function write(string $key, array $def, ?Tour $tour, bool $force = false): void
    {
        $version = (int) ($def['version'] ?? 1);
        $attrs = [
            'section' => $def['section'], 'title' => $def['title'], 'summary' => $def['summary'] ?? null, 'icon' => $def['icon'] ?? 'compass',
            'entry_route' => $def['entry_route'], 'audience' => $def['audience'] ?? 'all', 'auto_start' => $def['auto_start'] ?? true,
            'quick_enabled' => $def['quick_enabled'] ?? true, 'sort' => $def['sort'] ?? 0, 'is_customized' => false,
        ];
        if (! $tour) {
            $tour = Tour::create($attrs + ['key' => $key, 'version' => $version, 'is_active' => true]);
        } else {
            // The code's version only ever moves forward; an admin "Publish new version" is kept.
            $tour->update($attrs + ['version' => max($tour->version, $version), 'is_active' => $force ? true : $tour->is_active]);
        }
        $keep = [];
        foreach (array_values((array) $def['steps']) as $i => $s) {
            TourStep::updateOrCreate(['tour_id' => $tour->id, 'anchor' => $s['anchor']], [
                'position' => $i + 1, 'title' => $s['title'], 'body' => $s['body'], 'placement' => $s['placement'] ?? 'auto',
                'viewport' => $s['viewport'] ?? 'all', 'before_click' => $s['before_click'] ?? null, 'requires' => $s['requires'] ?? null,
                'quick' => (bool) ($s['quick'] ?? false), 'is_active' => true,
            ]);
            $keep[] = $s['anchor'];
        }
        $tour->steps()->whereNotIn('anchor', $keep)->delete();
    }

    /** Tours this person may see, in order (active, audience matches, entry route exists). */
    public static function eligible(User $user)
    {
        if (! Schema::hasTable('tours')) {
            return collect();
        }

        return Tour::where('is_active', true)->orderBy('sort')->get()->filter(fn (Tour $t) => self::audienceMatches($t, $user) && Route::has($t->entry_route))->values();
    }

    public static function audienceMatches(Tour $t, User $user): bool
    {
        $a = (string) $t->audience;
        if ($a === 'all') {
            return true;
        }
        if ($a === 'new_users') {
            return $user->created_at && $user->created_at->gt(now()->subDays(max(1, TourWindow::days() ?: 30)));
        }
        if (str_starts_with($a, 'role:')) {
            return $user->hasRole(substr($a, 5));
        }

        return false;
    }

    /** The JSON the browser engine runs: active steps, quick-filtered, copy resolved for the locale. */
    public static function payload(Tour $tour, string $mode, string $locale): array
    {
        $steps = $tour->steps()->where('is_active', true)->get()->when($mode === 'quick', fn ($c) => $c->where('quick', true))->values();

        return [
            'key' => $tour->key, 'version' => $tour->version, 'title' => $tour->title, 'mode' => $mode, 'entry' => Route::has($tour->entry_route) ? route($tour->entry_route) : null,
            'hub' => Route::has('account.tours') ? route('account.tours') : null,
            'steps' => $steps->map(fn (TourStep $s) => [
                'anchor' => $s->anchor, 'placement' => $s->placement, 'viewport' => $s->viewport, 'before_click' => $s->before_click,
                'optional' => $s->requires === 'optional', 'requires' => $s->requires,
            ] + $s->copy($locale))->all(),
        ];
    }
}
