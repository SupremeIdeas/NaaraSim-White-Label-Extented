<?php

namespace App\Support;

use App\Models\NumbersBentoCard;
use Illuminate\Support\Facades\Cache;

/**
 * The Numbers landing bento content (Numbers V6 §1–2). Ships six code-default
 * cards so the landing renders before any admin edit, and merges admin overrides
 * (NumbersBentoCard rows) on top — copy, badge, icon/image, bullets, order and
 * visibility, all editable without a deploy. The asymmetric bento layout (each
 * card's column span) is fixed here per key; the view lays them out.
 */
class NumbersBento
{
    private const CACHE = 'numbers.bento.v2'; // v2: display_mode toggle added

    /**
     * Cards whose entrance is admin-choosable between a modal and a dedicated
     * page (owner request, 2026-09-08). The other three keys already land on
     * their own route in every install and are never modal — not togglable.
     *
     * @var list<string>
     */
    public const TOGGLABLE = ['verify', 'rent', 'line'];

    /**
     * Fixed order — the bento rhythm (on a 6-column grid):
     *   Row 1: Verify (span 4) + Rent (span 2)
     *   Row 2: Naara Line — FULL WIDTH (span 6, the featured hero card)
     *   Row 3: Internet Calls (span 3) + Call Forwarding (span 3)
     *   Row 4: Contact Management — FULL WIDTH (span 6)
     */
    public const ORDER = ['verify', 'rent', 'line', 'internet_calls', 'call_forwarding', 'contact_management'];

    /** key => [span (of 6), tall (taller hero height)]. */
    public const LAYOUT = [
        'verify' => ['span' => 4, 'tall' => true],
        'rent' => ['span' => 2, 'tall' => true],
        'line' => ['span' => 6, 'tall' => true],
        'internet_calls' => ['span' => 3, 'tall' => false],
        'call_forwarding' => ['span' => 3, 'tall' => false],
        'contact_management' => ['span' => 6, 'tall' => false],
    ];

    /**
     * Code defaults (Numbers V6 §2 seed copy, corrected against the real data
     * source). `link` is how a tap resolves: a modal name or a route.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'verify' => [
                'badge_label' => 'FEATURED', 'icon' => 'shield-check', 'image' => '/img/numbers/bento/verify.webp',
                'title' => 'Naara Verify',
                'subtitle' => 'Receive OTPs and verification codes instantly from trusted virtual numbers.',
                'bullets' => ['WhatsApp', 'Telegram', 'Google'], // + live "+N more" appended at render
                'link' => ['modal' => 'verify'],
            ],
            'rent' => [
                'badge_label' => 'POPULAR', 'icon' => 'hash', 'image' => '/img/numbers/bento/rent.webp',
                'title' => 'Naara Rent',
                'subtitle' => 'Rent temporary virtual phone numbers whenever you need one.',
                'bullets' => ['Short-term use', 'Multiple countries', 'Instant activation'],
                'link' => ['modal' => 'rent'],
            ],
            'line' => [
                'badge_label' => 'PREMIUM', 'icon' => 'phone', 'image' => '/img/numbers/bento/line.webp',
                'title' => 'Naara Line',
                'subtitle' => 'Own a permanent international number that works for voice calls and SMS.',
                'bullets' => ['Permanent number', 'Voice calls', 'SMS & more'],
                'link' => ['modal' => 'line'],
            ],
            'call_forwarding' => [
                'badge_label' => 'SMART', 'icon' => 'phone-forwarded', 'image' => '/img/numbers/bento/call-forwarding.webp',
                'title' => 'Call Forwarding',
                'subtitle' => 'Forward calls from your permanent Naara number to any mobile phone worldwide.',
                'bullets' => [],
                'link' => ['route' => 'numbers.forwarding'],
            ],
            'internet_calls' => [
                'badge_label' => 'GLOBAL', 'icon' => 'phone', 'image' => '/img/numbers/bento/internet-calls.webp',
                'title' => 'Make Internet Calls',
                // Copy corrected (source-of-truth note): in-browser calling is funded
                // from wallet balance and does NOT require owning a Naara Line.
                'subtitle' => 'Call any international number right from your browser, funded from your wallet balance.',
                'bullets' => ['Crystal-clear voice', 'Affordable rates', 'Call anywhere'],
                'link' => ['route' => 'numbers.dialer'],
            ],
            'contact_management' => [
                'badge_label' => 'EASY', 'icon' => 'users', 'image' => '/img/numbers/bento/contact-management.webp',
                'title' => 'Contact Management',
                'subtitle' => 'Organize your contacts and call them straight from your address book.',
                'bullets' => [],
                'link' => ['route' => 'numbers.contacts'],
            ],
        ];
    }

    /**
     * The rendered cards, in fixed order, merging admin overrides over defaults.
     * Inactive cards are dropped. The verify card's bullets get a live "+N more".
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cards(): array
    {
        $rows = self::rows();

        $out = [];
        foreach (self::ORDER as $key) {
            $def = self::defaults()[$key];
            $row = $rows->get($key);

            if ($row && ! $row->is_active) {
                continue; // admin toggled this card off
            }

            $out[] = [
                'key' => $key,
                'span' => self::LAYOUT[$key]['span'],
                'tall' => self::LAYOUT[$key]['tall'],
                'badge_label' => $row->badge_label ?? $def['badge_label'],
                'icon' => $def['icon'],
                'image' => $row?->image_path ?: $def['image'],
                'title' => $row->title ?? $def['title'],
                'subtitle' => $row->subtitle ?? $def['subtitle'],
                'bullets' => self::bulletsFor($key, $row?->bullets ?? $def['bullets']),
                'link' => self::resolveLink($key, $def['link']),
            ];
        }

        return $out;
    }

    /**
     * A togglable card's link becomes a real route (to /numbers?modal={key},
     * matching the URL the modal itself already reflects via
     * `#[Url(as: 'modal')]` on GetNumber) once an admin sets its display mode
     * to 'page' — the bento view already renders any `['route' => ...]` link
     * as a real `<a href>` `wire:navigate` anchor, exactly like the three
     * cards that were always dedicated pages. Untouched (still a modal
     * trigger) for every other key/mode.
     *
     * @param  array{modal?:string,route?:string}  $default
     * @return array{modal?:string,route?:string,query?:array}
     */
    private static function resolveLink(string $key, array $default): array
    {
        if (in_array($key, self::TOGGLABLE, true) && self::isPageMode($key)) {
            return ['route' => 'numbers', 'query' => ['modal' => $key]];
        }

        return $default;
    }

    /**
     * True when this togglable key's admin-set display mode is 'page'. A
     * non-togglable key (already always a dedicated page) or an unset
     * override both resolve to the card's own hardcoded default — 'modal'
     * for every currently togglable key, so an untouched install never
     * changes behaviour.
     */
    public static function isPageMode(string $key): bool
    {
        if (! in_array($key, self::TOGGLABLE, true)) {
            return false;
        }

        return self::rows()->get($key)?->display_mode === 'page';
    }

    /** @return \Illuminate\Support\Collection<string, NumbersBentoCard> */
    private static function rows(): \Illuminate\Support\Collection
    {
        return Cache::remember(self::CACHE, now()->addMinutes(10), function () {
            try {
                return NumbersBentoCard::all()->keyBy('key');
            } catch (\Throwable) {
                return collect();
            }
        });
    }

    /** The verify card appends a live "+N more" from the real service catalogue. */
    private static function bulletsFor(string $key, array $bullets): array
    {
        if ($key === 'verify') {
            $total = count(NumberCatalogue::services());
            $more = max(0, $total - count($bullets));
            if ($more > 0) {
                $bullets[] = "+{$more} more";
            }
        }

        return array_slice($bullets, 0, 4);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
    }
}
