<?php

namespace App\Support;

use App\Models\NavSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read/seed helper for the floating navigation bar (Homepage floating-nav §2).
 * Serves the visible, ordered slots for the current auth state and seeds a
 * sensible default set on first use. Cached; flushed when the admin edits.
 */
class NavSlots
{
    private const CACHE_KEY = 'nav.slots.v1';

    /**
     * The default floating-bar set: a deliberately minimal THREE items + the
     * Wizard centrepiece. Everything else lives in the top-header mega menu, so
     * the bottom bar never crowds or blocks content. The admin can swap which
     * three via Admin → Floating nav; the bar renders at most three (see MAX).
     */
    public static function defaults(): array
    {
        return [
            ['position' => 0, 'label' => 'Ask NaaraSim', 'icon' => 'message-circle', 'target' => NavSlot::TARGET_WIZARD, 'visibility' => 'all', 'is_center' => true],
            ['position' => 1, 'label' => 'Home', 'icon' => 'globe', 'target' => 'home', 'visibility' => 'all', 'is_center' => false],
            ['position' => 2, 'label' => 'Plans', 'icon' => 'wifi', 'target' => 'pricing', 'visibility' => 'all', 'is_center' => false],
            ['position' => 3, 'label' => 'Numbers', 'icon' => 'phone', 'target' => 'pricing', 'visibility' => 'all', 'is_center' => false],
        ];
    }

    /** The floating bar shows at most this many regular items (+ the centre). */
    public const MAX_REGULAR = 3;

    /** Seed the default set once (called lazily on first read; also usable by admin). */
    public static function ensureSeeded(): void
    {
        if (NavSlot::count() === 0) {
            foreach (self::defaults() as $slot) {
                NavSlot::create($slot + ['is_active' => true]);
            }
            self::flush();
        }
    }

    /** @return Collection<int, NavSlot> */
    public static function all()
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                self::ensureSeeded();

                return NavSlot::orderBy('position')->orderBy('id')->get();
            });
        } catch (\Throwable) {
            // Table not migrated yet (e.g. a bare test env) — degrade to no bar.
            return collect();
        }
    }

    /**
     * The bar split for rendering: the centerpiece plus the regular slots that
     * apply to this auth state, split evenly left/right of the centre.
     *
     * @return array{center: ?NavSlot, left: array, right: array}
     */
    public static function bar(bool $authed): array
    {
        $active = self::all()->where('is_active', true)->filter(fn (NavSlot $s) => $s->visibleTo($authed));
        $center = $active->firstWhere('is_center', true);
        // Keep the bar minimal — at most MAX_REGULAR items; the rest belong in the
        // top-header mega menu.
        $regular = $active->where('is_center', false)->values()->take(self::MAX_REGULAR);

        $half = (int) ceil($regular->count() / 2);

        return [
            'center' => $center,
            'left' => $regular->slice(0, $half)->values()->all(),
            'right' => $regular->slice($half)->values()->all(),
        ];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
