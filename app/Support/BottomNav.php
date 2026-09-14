<?php

namespace App\Support;

use App\Models\Merchant;
use App\Models\NavItemOverride;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Admin-configurable bottom navigation (owner request): lets an admin reorder
 * or replace which items appear in the main bottom bar / More sheet, and in
 * the Numbers section's own bottom bar, via App\Models\NavItemOverride —
 * without a deploy.
 *
 * Safety rule this class exists to enforce: an override can only reorder or
 * hide an item that is ALREADY eligible for the viewer (their role, active
 * feature flags, entitlement locks). It can never expose a link a viewer
 * isn't actually allowed to use — eligibleMainItems() is the single source
 * of truth for that, and it is a verbatim, behavior-preserving port of what
 * used to be inline @php in customer.blade.php. With no override configured
 * (a fresh install, or before an admin ever visits the new settings screen),
 * resolveMain()/resolveNumbers() return EXACTLY what the platform showed
 * before this feature existed — zero behavior change by default.
 */
class BottomNav
{
    /**
     * Every possible main-nav destination, for the admin settings screen to
     * display and arrange. Eligibility (whether a given viewer actually sees
     * a key) is decided only by eligibleMainItems() above — this list exists
     * purely so an admin can order/hide items they may not personally
     * qualify for (e.g. arranging the merchant items while not a merchant).
     */
    public const MAIN_CATALOG = [
        'catalogue' => ['label' => 'eSIMs', 'icon' => 'globe'],
        'numbers' => ['label' => 'Numbers', 'icon' => 'hash'],
        'gift-cards' => ['label' => 'Gifts', 'icon' => 'gift'],
        'wallet' => ['label' => 'Wallet', 'icon' => 'wallet'],
        'dashboard' => ['label' => 'Home', 'icon' => 'signal'],
        'numbers.contacts' => ['label' => 'Contacts', 'icon' => 'users'],
        'numbers.dialer' => ['label' => 'Internet calls', 'icon' => 'phone'],
        'support' => ['label' => 'Help & Support', 'icon' => 'message-circle'],
        'developer' => ['label' => 'Developer API', 'icon' => 'key'],
        'merchant.clients' => ['label' => 'Clients', 'icon' => 'users'],
        'merchant.invoices' => ['label' => 'Invoices', 'icon' => 'file-text'],
        'merchant.dashboard' => ['label' => 'My Storefront', 'icon' => 'package'],
        'partner.earnings' => ['label' => 'Partner earnings', 'icon' => 'wallet'],
        'rewards' => ['label' => 'Rewards', 'icon' => 'gift'],
        'journey' => ['label' => 'My Journey', 'icon' => 'globe'],
        'receipts' => ['label' => 'Receipts', 'icon' => 'file-text'],
        'brand.get-listed' => ['label' => 'List my brand', 'icon' => 'star'],
        'referrals' => ['label' => 'Referrals', 'icon' => 'users'],
        'data-estimator' => ['label' => 'Estimator', 'icon' => 'signal'],
        'profile' => ['label' => 'Profile', 'icon' => 'id-card'],
        'account' => ['label' => 'Account', 'icon' => 'settings'],
        'security' => ['label' => 'Security', 'icon' => 'shield'],
        'merchant.apply' => ['label' => 'Become a Merchant', 'icon' => 'package'],
        'admin.dashboard' => ['label' => 'Admin', 'icon' => 'id-card'],
        'download' => ['label' => 'Download the app', 'icon' => 'download'],
        'guide' => ['label' => 'Guide & policy', 'icon' => 'help-circle'],
    ];

    /** Numbers section catalog, in its fallback order. */
    public const NUMBERS_CATALOG = [
        'numbers.contacts' => ['label' => 'Contacts', 'icon' => 'users'],
        'numbers.forwarding' => ['label' => 'Forwarding', 'icon' => 'phone-forwarded'],
        'numbers.dialer' => ['label' => 'Dialer', 'icon' => 'phone'],
        'numbers.messages' => ['label' => 'Messages', 'icon' => 'message-circle'],
        'numbers.port-in' => ['label' => 'Port In', 'icon' => 'smartphone'],
    ];

    /** The Numbers bar always renders exactly 4 slots (2 left + centre + 2 right). */
    private const NUMBERS_SLOTS = 4;

    /**
     * The eligible main-nav items for this viewer, split exactly as the
     * platform has always split them: up to 4 in the bottom bar (`primary`),
     * the rest in the "More" sheet (`more`). A verbatim port of
     * customer.blade.php's former inline @php block — every conditional
     * below reproduces that logic unchanged.
     *
     * @return array{primary: list<array>, more: list<array>}
     */
    public static function eligibleMainItems(?User $u): array
    {
        $primary = [
            ['route' => 'catalogue', 'label' => 'eSIMs', 'icon' => 'globe'],
            ['route' => 'numbers', 'label' => 'Numbers', 'icon' => 'hash'],
        ];
        if (FeatureFlags::adminEnabled('naara_gift') && ! FeatureEntitlements::locked(FeatureLocks::F_GIFT_CARDS)) {
            $primary[] = ['route' => 'gift-cards', 'label' => 'Gifts', 'icon' => 'gift',
                'badge' => FeatureFlags::configured('naara_gift') ? null : 'Soon'];
        }
        $primary[] = ['route' => 'wallet', 'label' => 'Wallet', 'icon' => 'wallet'];

        $more = [
            ['route' => 'numbers.contacts', 'label' => 'Contacts', 'icon' => 'users'],
            ['route' => 'support', 'label' => 'Help & Support', 'icon' => 'message-circle'],
            ['route' => 'rewards', 'label' => 'Rewards', 'icon' => 'gift'],
            ['route' => 'journey', 'label' => 'My Journey', 'icon' => 'globe'],
            ['route' => 'receipts', 'label' => 'Receipts', 'icon' => 'file-text'],
            ['route' => 'brand.get-listed', 'label' => 'List my brand', 'icon' => 'star'],
            ['route' => 'referrals', 'label' => 'Referrals', 'icon' => 'users'],
            ['route' => 'data-estimator', 'label' => 'Estimator', 'icon' => 'signal'],
            ['route' => 'profile', 'label' => 'Profile', 'icon' => 'id-card'],
            ['route' => 'account', 'label' => 'Account', 'icon' => 'settings'],
            ['route' => 'security', 'label' => 'Security', 'icon' => 'shield'],
        ];

        if (FeatureEntitlements::locked(FeatureLocks::F_BRAND_HUNT)) {
            $more = array_values(array_filter($more, fn ($item) => ($item['route'] ?? null) !== 'brand.get-listed'));
        }

        if (ProviderStatus::isActive('twilio')) {
            array_splice($more, 1, 0, [['route' => 'numbers.dialer', 'label' => 'Internet calls', 'icon' => 'phone']]);
        }

        if (Setting::getValue('developer_api.enabled', false)) {
            array_splice($more, 4, 0, [['route' => 'developer', 'label' => 'Developer API', 'icon' => 'key']]);
        }

        if ($u && $u->merchantAccount && $u->merchantAccount->isActive()) {
            array_unshift($more, ['route' => 'merchant.dashboard', 'label' => 'My Storefront', 'icon' => 'package']);
            if ($u->merchantAccount->isV2()) {
                array_unshift($more, ['route' => 'merchant.invoices', 'label' => 'Invoices', 'icon' => 'file-text']);
                array_unshift($more, ['route' => 'merchant.clients', 'label' => 'Clients', 'icon' => 'users']);
            }
        }

        if ($u && $u->partnerAccount) {
            array_unshift($more, ['route' => 'partner.earnings', 'label' => 'Partner earnings', 'icon' => 'wallet']);
        }

        if ($u && ($u->merchantAccount === null || $u->merchantAccount->status === Merchant::REJECTED) && MerchantSettings::enabled()) {
            $more[] = ['route' => 'merchant.apply', 'label' => 'Become a Merchant', 'icon' => 'package'];
        }

        if ($u && $u->hasAnyRole(['super_admin', 'admin', 'staff'])) {
            $more[] = ['route' => 'admin.dashboard', 'label' => 'Admin', 'icon' => 'id-card'];
        }

        if (AppExport::placementActive('customer_menu')) {
            $more[] = ['route' => 'download', 'label' => AppExport::placementLabel('customer_menu'), 'icon' => 'download'];
        }

        $more[] = ['route' => 'guide', 'label' => 'Guide & policy', 'icon' => 'help-circle'];

        array_unshift($more, ['route' => 'dashboard', 'label' => 'Home', 'icon' => 'signal']);

        return ['primary' => $primary, 'more' => $more];
    }

    /**
     * The main bottom nav to actually render for this viewer: the eligible
     * items above, reordered/filtered by any admin override. Falls back to
     * the natural primary/more split untouched when no override exists.
     *
     * @return array{primary: list<array>, more: list<array>}
     */
    public static function resolveMain(?User $u): array
    {
        $eligible = self::eligibleMainItems($u);
        $overrides = self::overrides('main');

        if ($overrides->isEmpty()) {
            return $eligible;
        }

        $pool = collect(array_merge($eligible['primary'], $eligible['more']))->keyBy('route');
        $ordered = $overrides->where('is_active', true)->pluck('item_key')
            ->filter(fn ($key) => $pool->has($key))->values();
        // Items never mentioned by the override at all (e.g. a new route
        // shipped after the admin last configured this) — kept, appended in
        // their natural order. A key the admin explicitly turned off is
        // mentioned (just inactive), so it is correctly excluded here too.
        $remaining = $pool->keys()->diff($overrides->pluck('item_key'))->values();
        $items = $ordered->concat($remaining)->map(fn ($key) => $pool->get($key))->values()->all();

        return [
            'primary' => array_slice($items, 0, 4),
            'more' => array_slice($items, 4),
        ];
    }

    /**
     * The Numbers section's own bottom bar: always exactly 4 items (the
     * template renders fixed slots 0-3 either side of the centre "My Lines"
     * button). Default order/set matches what the platform always showed;
     * an override can reorder them or swap in Port In. `$unreadBadge` is
     * live per-request data, never stored in the override.
     *
     * @return list<array>
     */
    public static function resolveNumbers(int $unreadBadge = 0): array
    {
        $defaultOrder = array_keys(self::NUMBERS_CATALOG);
        $overrides = self::overrides('numbers');

        if ($overrides->isEmpty()) {
            $order = collect(array_slice($defaultOrder, 0, self::NUMBERS_SLOTS));
        } else {
            $active = $overrides->where('is_active', true)->pluck('item_key')
                ->filter(fn ($key) => isset(self::NUMBERS_CATALOG[$key]))->values();
            // A key the admin turned off is mentioned (just inactive) and so
            // is correctly excluded from both $active and this fallback pool.
            $order = $active->concat(collect($defaultOrder)->diff($overrides->pluck('item_key')));

            // The bar always shows exactly 4: if hiding several defaults left
            // fewer than 4 selected with no replacement configured, pull in
            // whatever catalog keys remain (regardless of hidden status)
            // rather than ever rendering short.
            if ($order->count() < self::NUMBERS_SLOTS) {
                $order = $order->concat(collect(array_keys(self::NUMBERS_CATALOG))->diff($order));
            }
        }
        $order = array_slice($order->values()->all(), 0, self::NUMBERS_SLOTS);

        return array_map(function (string $key) use ($unreadBadge) {
            $meta = self::NUMBERS_CATALOG[$key];
            $item = ['route' => $key, 'label' => $meta['label'], 'icon' => $meta['icon']];
            if ($key === 'numbers.messages') {
                $item['badge'] = $unreadBadge;
            }

            return $item;
        }, $order);
    }

    /** Every configured row for this nav (active AND inactive) — see callers. */
    private static function overrides(string $nav): Collection
    {
        return NavItemOverride::where('nav', $nav)->orderBy('position')->get();
    }
}
