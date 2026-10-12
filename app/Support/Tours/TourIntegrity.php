<?php

namespace App\Support\Tours;

use App\Models\Tour;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Tour Integrity (Phase 10.7): tours must not rot when the UI changes. Pure static analysis of the views, so it runs anywhere (CLI, admin button, tests):
 * every active step's `data-tour` anchor must exist in a view (literal, or a `data-tour="prefix.{{ ... }}"` pattern), its entry route must exist,
 * its copy must be present, `before_click` anchors must exist, and defaults must be seeded. A step that fails here would silently skip for users.
 */
final class TourIntegrity
{
    /** @return array{literal: array<string,int>, prefixes: list<string>} */
    public static function anchors(): array
    {
        $literal = [];
        $prefixes = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }
            $src = (string) file_get_contents($f->getPathname());
            if (! str_contains($src, 'data-tour=')) {
                continue;
            }
            preg_match_all('/data-tour="([a-z0-9._-]+)(\{\{[^"]*)?"/', $src, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                if (isset($hit[2]) && $hit[2] !== '') {
                    $prefixes[] = $hit[1];
                } else {
                    $literal[$hit[1]] = ($literal[$hit[1]] ?? 0) + 1;
                }
            }
        }

        // Anchors drawn by JavaScript (the shared composer builds its own DOM): the convention is a `TOUR = { ref: 'name' }` map plus
        // `dataset.tour = 'prefix.' + TOUR[ref]`, which this reads so a renamed or dropped composer control fails the check too.
        $js = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js'), \FilesystemIterator::SKIP_DOTS));
        foreach ($js as $f) {
            if (! str_ends_with($f->getFilename(), '.js')) {
                continue;
            }
            $src = (string) file_get_contents($f->getPathname());
            if (! preg_match("/dataset\.tour = '([a-z0-9._-]+)' \+ TOUR\[/", $src, $pm) || ! preg_match('/const TOUR = \{([^}]*)\}/', $src, $map)) {
                continue;
            }
            preg_match_all("/\w+:\s*'([a-z0-9-]+)'/", $map[1], $vals);
            foreach ($vals[1] as $v) {
                $literal[$pm[1].$v] = ($literal[$pm[1].$v] ?? 0) + 1;
            }
        }

        return ['literal' => $literal, 'prefixes' => array_values(array_unique($prefixes))];
    }

    public static function exists(string $anchor, array $found): bool
    {
        if (isset($found['literal'][$anchor])) {
            return true;
        }
        foreach ($found['prefixes'] as $p) {
            if (str_starts_with($anchor, $p)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> human-readable problems (empty = healthy) */
    public static function problems(): array
    {
        if (! Schema::hasTable('tours')) {
            return ['The tours tables are not installed yet (run the migrations).'];
        }
        $out = [];
        $found = self::anchors();
        foreach (array_keys(TourRegistry::defaults()) as $key) {
            if (! Tour::where('key', $key)->exists()) {
                $out[] = "$key: not seeded (run the sync)";
            }
        }
        foreach (Tour::where('is_active', true)->with('steps')->get() as $t) {
            if (! Route::has($t->entry_route)) {
                $out[] = "{$t->key}: entry route {$t->entry_route} does not exist";
            }
            $steps = $t->steps->where('is_active', true);
            if ($steps->isEmpty()) {
                $out[] = "{$t->key}: has no active steps";
            }
            foreach ($steps as $s) {
                if (trim($s->title) === '' || trim($s->body) === '') {
                    $out[] = "{$t->key} / {$s->anchor}: missing copy";
                }
                if (! self::exists($s->anchor, $found)) {
                    $out[] = "{$t->key} / {$s->anchor}: no element carries this data-tour anchor any more";
                }
                if ($s->before_click && ! self::exists($s->before_click, $found)) {
                    $out[] = "{$t->key} / {$s->anchor}: before_click anchor {$s->before_click} is gone";
                }
                if ($s->requires && $s->requires !== 'optional' && ! str_starts_with((string) $s->requires, 'route:')) {
                    $out[] = "{$t->key} / {$s->anchor}: unknown requirement {$s->requires}";
                }
                if (str_starts_with((string) $s->requires, 'route:') && ! Route::has(substr($s->requires, 6))) {
                    $out[] = "{$t->key} / {$s->anchor}: required route {$s->requires} does not exist";
                }
            }
            $dupes = $steps->groupBy('anchor')->filter(fn ($g) => $g->count() > 1)->keys();
            foreach ($dupes as $d) {
                $out[] = "{$t->key}: anchor $d is used by more than one step";
            }
        }
        // Duplicate literal anchors inside one page would make "first visible" ambiguous: report across the whole tree.
        foreach ($found['literal'] as $a => $n) {
            if ($n > 2) {   // two is normal (mobile + desktop copy of the same control); more means a copy-paste
                $out[] = "anchor $a appears $n times in the views";
            }
        }

        return $out;
    }
}
