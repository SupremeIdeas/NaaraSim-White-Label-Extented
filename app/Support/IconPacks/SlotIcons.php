<?php

namespace App\Support\IconPacks;

use App\Support\IconOverrides;
use Illuminate\Support\Facades\Route;

/**
 * Menu icon slots for this install: every menu item that has a duotone icon in the pack set, mapped to its art. Only features this install has
 * are listed (the set is a strict subset of the Naara one). Resolution: an admin override for the slot, then the active icon pack, then null (the
 * caller keeps its generic sprite icon, so a missing file never leaves a hole).
 */
final class SlotIcons
{
    /** Dashboard "More" sheet and bottom bar: BottomNav route key => file slot. */
    public const MORE = [
        'catalogue' => 'esims',
        'numbers' => 'numbers',
        'gift-cards' => 'gifts',
        'wallet' => 'wallet',
        'dashboard' => 'home',
        'numbers.contacts' => 'contacts',
        'numbers.dialer' => 'internet-calls',
        'developer' => 'developer-api',
        'merchant.clients' => 'clients',
        'merchant.invoices' => 'invoices',
        'merchant.dashboard' => 'my-storefront',
        'partner.earnings' => 'partner-earnings',
        'rewards' => 'rewards',
        'journey' => 'my-journey',
        'receipts' => 'receipts',
        'brand.get-listed' => 'list-my-brand',
        'referrals' => 'referrals',
        'data-estimator' => 'estimator',
        'profile' => 'profile',
        'account.appearance' => 'appearance',
        'account' => 'account',
        'security' => 'security',
        'merchant.apply' => 'become-merchant',
        'admin.dashboard' => 'admin',
        'download' => 'download-app',
        'guide' => 'guide-policy',
        'numbers.forwarding' => 'forwarding',
        'numbers.messages' => 'messages',
        'numbers.port-in' => 'port-in',
        'support' => 'help-support',
    ];

    /** Admin menus: route name => file slot. */
    public const ADMIN = [
        'admin.dashboard' => 'overview',
        'admin.pricing' => 'pricing',
        'admin.staff' => 'staff',
        'admin.errors' => 'errors',
        'admin.security' => 'security',
        'admin.esim' => 'esim-control-center',
        'admin.pricing-architect' => 'pricing-architect',
        'admin.coupons' => 'coupons',
        'admin.announcements' => 'announcements',
        'admin.notices' => 'login-notices',
        'admin.guides' => 'user-guides',
        'admin.banners' => 'banners',
        'admin.banner-placements' => 'banner-placements',
        'admin.esim-hero' => 'esim-hero',
        'admin.numbers-hero' => 'numbers-hero',
        'admin.numbers-cards' => 'numbers-cards',
        'admin.bento-icons' => 'bento-icons',
        'admin.credits' => 'naara-credits',
        'admin.journey-goals' => 'journey-goals',
        'admin.social-hunt' => 'social-hunt',
        'admin.brand-directory' => 'brand-directory',
        'admin.gift-cards' => 'naara-gift',
        'admin.gift-hero' => 'gift-hero',
        'admin.analytics' => 'analytics',
        'admin.payouts' => 'payouts',
        'admin.payout-health' => 'payout-health',
        'admin.payout-settings' => 'payout-settings',
        'admin.global-payout-rail' => 'global-payout-rail',
        'admin.refunds' => 'refunds-disputes',
        'admin.reconciliation' => 'reconciliation',
        'admin.exchange-rates' => 'exchange-rates',
        'admin.tax-rates' => 'tax-rates',
        'admin.gateways' => 'payment-gateways',
        'admin.kyc' => 'identity-kyc',
        'admin.merchants' => 'merchants',
        'admin.partners' => 'partners',
        'admin.developer-api' => 'developer-api',
        'admin.builder' => 'page-builder',
        'admin.copy-studio' => 'copy-studio',
        'admin.nav' => 'floating-nav',
        'admin.bottom-nav' => 'bottom-nav',
        'admin.app-builder' => 'app-builder',
        'admin.site' => 'marketing-site',
        'admin.product-lines' => 'product-lines',
        'admin.email-studio' => 'email-studio',
        'admin.email-broadcast' => 'email-broadcast',
        'admin.home-media' => 'homepage-media',
        'admin.sidebar-menu' => 'sidebar-menu',
        'admin.pages' => 'custom-pages',
        'admin.trustpilot' => 'trustpilot-reviews',
        'admin.blog' => 'blog',
        'admin.blog-reader' => 'blog-reader',
        'admin.tours' => 'guided-tours',
        'admin.legal' => 'legal',
        'admin.cookies' => 'cookies',
        'admin.incidents' => 'status-incidents',
        'admin.branding' => 'branding',
        'admin.link-previews' => 'link-previews',
        'admin.chrome' => 'auth-footer',
        'admin.appearance' => 'splash',
        'admin.preloader-studio' => 'preloader-studio',
        'admin.theme-toggle-studio' => 'theme-toggle-studio',
        'admin.welcome-settings' => 'welcome-animation',
        'admin.theme' => 'theme',
        'admin.dashboard-theme' => 'dashboard-theme',
        'admin.user-appearance' => 'user-appearance',
        'admin.service-icons' => 'service-icons',
        'admin.integrations' => 'integrations',
        'admin.features' => 'features',
        'admin.users' => 'users',
        'admin.support-agent' => 'support-agent',
        'admin.deletions' => 'deletions',
        'admin.legal-hold' => 'legal-hold-records',
        'admin.port-in-requests' => 'port-in-requests',
        'admin.tickets' => 'tickets',
        'admin.nci.registry' => 'provider-registry',
        'admin.nci.health' => 'health-monitor',
        'admin.nci.routing' => 'routing-console',
        'admin.nci.wallets' => 'provider-wallets',
        'admin.system-health' => 'system-health',
        'admin.api-keys' => 'api-keys',
        'admin.email' => 'email',
        'admin.backups' => 'backups',
        'admin.updater' => 'platform-updater',
        'admin.maintenance' => 'maintenance',
        'admin.ui-kit' => 'ui-kit',
        'admin.account' => 'my-account',
        'admin.my-appearance' => 'my-appearance',
        'dashboard' => 'storefront',
        'download' => 'download-app',
    ];

    public static function slotFor(string $route, ?string $surface = null): ?array
    {
        $surface ??= self::surface();
        if ($surface === 'admin') {
            $k = self::ADMIN[$route] ?? null;

            return $k ? ['admin', 'admin-more-'.$k] : null;
        }
        $k = self::MORE[$route] ?? null;

        return $k ? ['more', 'app-more-'.$k] : null;
    }

    public static function surface(): string
    {
        return str_starts_with((string) (request()->route()?->getName() ?? ''), 'admin.') ? 'admin' : 'app';
    }

    /** @return array{light:string,dark:string,custom:bool}|null */
    public static function resolve(string $route, ?string $surface = null): ?array
    {
        $slot = self::slotFor($route, $surface);
        if (! $slot) {
            return null;
        }
        [$group, $file] = $slot;
        $custom = IconOverrides::for('menu-'.$group.'-'.$file);
        if ($custom) {
            return ['light' => $custom, 'dark' => $custom, 'custom' => true];
        }
        if (! is_file(public_path('images/icon-packs/'.$group.'/svg/'.$file.'.svg')) || ! Route::has('icon-pack.svg')) {
            return null;
        }
        $pack = IconPacks::activeSlug();

        return ['light' => IconPacks::url($group, $file, $pack), 'dark' => IconPacks::url($group, $file.'-dark', $pack), 'custom' => false];
    }
}
