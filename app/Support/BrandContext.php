<?php

namespace App\Support;

/**
 * Route-aware header branding (owner request). The dashboard chrome wears the
 * umbrella "Naara" family mark almost everywhere — but on a product's OWN
 * surface it swaps to that product's mark so the surface stands out:
 *
 *   - NaaraSim (product)  → the eSIM/data surface AND the number surfaces
 *                           (call + SMS/message), which are all NaaraSim.
 *   - Naara Gift (gift)   → the gift-card storefront.
 *   - Naara family        → everywhere else on the dashboard (home, wallet,
 *                           rewards, account, support, …).
 *
 * This lives in the header (the desktop sidebar brand + the mobile top bar), not
 * inside the page body — the mark is chrome, not content.
 */
class BrandContext
{
    /**
     * Each sub-brand's surface, as route-name patterns (Str::is globs, so
     * `numbers.*` covers the dialer, forwarding, contacts, …). First match wins;
     * order therefore matters only if patterns overlap (they don't here).
     *
     * @var array<string, array{label: string, routes: list<string>}>
     */
    private const SURFACES = [
        'product' => [
            'label' => 'NaaraSim',
            // eSIM/data + every number surface (call + message/SMS live here).
            'routes' => ['catalogue', 'numbers', 'numbers.*'],
        ],
        'gift' => [
            'label' => 'Naara Gift',
            'routes' => ['gift-cards', 'gift-cards.*'],
        ],
    ];

    /**
     * The brand mark the header should show for the current route.
     *
     * @return array{variant: string, label: ?string}
     */
    public static function headerLogo(): array
    {
        foreach (self::SURFACES as $variant => $surface) {
            if (request()->routeIs(...$surface['routes'])) {
                return ['variant' => $variant, 'label' => $surface['label']];
            }
        }

        return ['variant' => 'family', 'label' => null];
    }
}
