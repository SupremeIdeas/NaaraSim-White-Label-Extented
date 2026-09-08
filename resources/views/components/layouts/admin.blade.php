{{-- Admin chrome (blueprint Sections 13, 15, 17, 25, 27). Same premium shell as
     the customer app — Apple-inspired side menu on desktop, mobile bottom nav —
     but role-scoped: staff see only what they may use; admin config is
     super_admin/admin; staff management is super_admin only. --}}
@props(['preloaderType' => 'admin'])
@php
    $u = auth()->user();
    $isPrivileged = $u->hasAnyRole(['super_admin', 'admin']);
    $isSuper = $u->hasRole('super_admin');

    $primary = [['route' => 'admin.dashboard', 'label' => 'Overview', 'icon' => 'signal']];
    $more = [];

    // `More` is grouped with ['heading' => ...] separators so complementary
    // tools sit together; the app-shell renders headings in the desktop sidebar
    // and skips them in the mobile grid.
    if ($isPrivileged) {
        // Bottom-bar core differs slightly by role (super gets Staff, admin gets
        // Errors); the rest live in the "More" sheet / lower sidebar.
        $primary[] = ['route' => 'admin.pricing', 'label' => 'Pricing', 'icon' => 'credit-card'];
        $primary[] = $isSuper
            ? ['route' => 'admin.staff', 'label' => 'Staff', 'icon' => 'id-card']
            : ['route' => 'admin.errors', 'label' => 'Errors', 'icon' => 'file-text'];

        // Store & pricing.
        $more[] = ['heading' => 'Store & pricing'];
        $more[] = ['route' => 'admin.esim', 'label' => 'eSIM Control Center', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.pricing-architect', 'label' => 'Price with Claude', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.coupons', 'label' => 'Coupons', 'icon' => 'gift'];
        $more[] = ['route' => 'admin.announcements', 'label' => 'Announcements', 'icon' => 'bell'];
        $more[] = ['route' => 'admin.notices', 'label' => 'Login notices', 'icon' => 'bell'];
        $more[] = ['route' => 'admin.guides', 'label' => 'User guides', 'icon' => 'help-circle'];
        $more[] = ['route' => 'admin.banners', 'label' => 'Banners', 'icon' => 'image'];
        $more[] = ['route' => 'admin.esim-hero', 'label' => 'eSIM hero', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.numbers-hero', 'label' => 'Numbers hero', 'icon' => 'phone'];
        $more[] = ['route' => 'admin.numbers-cards', 'label' => 'Numbers cards', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.bento-icons', 'label' => 'Bento icons', 'icon' => 'image'];
        $more[] = ['route' => 'admin.credits', 'label' => 'NaaraCredits', 'icon' => 'gift'];
        $more[] = ['route' => 'admin.journey-goals', 'label' => 'Journey Goals', 'icon' => 'star'];
        $more[] = ['route' => 'admin.social-hunt', 'label' => 'Social Hunt', 'icon' => 'star'];
        $more[] = ['route' => 'admin.brand-directory', 'label' => 'Brand Directory', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.gift-cards', 'label' => 'Naara Gift', 'icon' => 'gift'];
        $more[] = ['route' => 'admin.gift-hero', 'label' => 'Gift hero', 'icon' => 'gift'];

        // Money & partners.
        $more[] = ['heading' => 'Money & partners'];
        $more[] = ['route' => 'admin.analytics', 'label' => 'Analytics', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.payouts', 'label' => 'Payouts', 'icon' => 'credit-card'];
        $more[] = ['route' => 'admin.refunds', 'label' => 'Refunds & Disputes', 'icon' => 'refresh'];
        $more[] = ['route' => 'admin.reconciliation', 'label' => 'Reconciliation', 'icon' => 'wallet'];
        $more[] = ['route' => 'admin.exchange-rates', 'label' => 'Exchange rate (NGN)', 'icon' => 'refresh'];
        $more[] = ['route' => 'admin.tax-rates', 'label' => 'Tax / VAT rates', 'icon' => 'wallet'];
        $more[] = ['route' => 'admin.gateways', 'label' => 'Payment Gateways', 'icon' => 'credit-card'];
        $more[] = ['route' => 'admin.kyc', 'label' => 'Identity (KYC)', 'icon' => 'shield'];
        $more[] = ['route' => 'admin.merchants', 'label' => 'Merchants', 'icon' => 'id-card'];
        $more[] = ['route' => 'admin.partners', 'label' => 'Partners', 'icon' => 'users'];
        $more[] = ['route' => 'admin.developer-api', 'label' => 'Developer API', 'icon' => 'key'];
        $more[] = ['route' => 'admin.white-label', 'label' => 'White-Label Oversight', 'icon' => 'users'];

        // Website (public front end + branding).
        $more[] = ['heading' => 'Website'];
        $more[] = ['route' => 'admin.builder', 'label' => 'Page builder', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.copy-studio', 'label' => 'Copy Studio (AI)', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.nav', 'label' => 'Floating nav', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.app-builder', 'label' => 'App Builder', 'icon' => 'package'];
        $more[] = ['route' => 'admin.site', 'label' => 'Marketing site', 'icon' => 'globe'];
        $more[] = ['route' => 'admin.product-lines', 'label' => 'Product lines', 'icon' => 'package'];
        $more[] = ['route' => 'admin.email-studio', 'label' => 'Email Studio', 'icon' => 'mail'];
        $more[] = ['route' => 'admin.email-broadcast', 'label' => 'Email broadcast', 'icon' => 'send'];
        $more[] = ['route' => 'admin.home-media', 'label' => 'Homepage media', 'icon' => 'play'];
        $more[] = ['route' => 'admin.sidebar-menu', 'label' => 'Sidebar menu', 'icon' => 'menu'];
        $more[] = ['route' => 'admin.pages', 'label' => 'Custom pages', 'icon' => 'file-text'];
        $more[] = ['route' => 'admin.blog', 'label' => 'Blog', 'icon' => 'file-text'];
        $more[] = ['route' => 'admin.legal', 'label' => 'Legal', 'icon' => 'shield'];
        $more[] = ['route' => 'admin.incidents', 'label' => 'Status incidents', 'icon' => 'bell'];
        $more[] = ['route' => 'admin.branding', 'label' => 'Branding', 'icon' => 'image'];
        $more[] = ['route' => 'admin.link-previews', 'label' => 'Link previews', 'icon' => 'share'];
        $more[] = ['route' => 'admin.chrome', 'label' => 'Auth & footer', 'icon' => 'image'];
        $more[] = ['route' => 'admin.appearance', 'label' => 'Splash', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.preloader-studio', 'label' => 'Preloader Studio', 'icon' => 'refresh'];
        $more[] = ['route' => 'admin.welcome-settings', 'label' => 'Welcome animation', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.theme', 'label' => 'Theme', 'icon' => 'star'];
        $more[] = ['route' => 'admin.dashboard-theme', 'label' => 'Dashboard theme', 'icon' => 'image'];
        $more[] = ['route' => 'admin.service-icons', 'label' => 'Service icons', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.integrations', 'label' => 'Integrations', 'icon' => 'link'];
        $more[] = ['route' => 'admin.features', 'label' => 'Features', 'icon' => 'zap'];
    }

    // People & support — ticket-workers (staff scope) and admins.
    $support = [];
    if ($isPrivileged) {
        $support[] = ['route' => 'admin.users', 'label' => 'Users', 'icon' => 'id-card'];
        $support[] = ['route' => 'admin.support-agent', 'label' => 'Support agent', 'icon' => 'message-circle'];
        $support[] = ['route' => 'admin.deletions', 'label' => 'Deletions', 'icon' => 'trash'];
    }
    if ($isPrivileged || $u->can('tickets.manage')) {
        $support[] = ['route' => 'admin.tickets', 'label' => 'Tickets', 'icon' => 'message-circle'];
    }
    // Delegated eSIM managers (esim.manage scope) who aren't full admins still
    // reach the Control Center (BUILD-8 §4.7).
    if (! $isPrivileged && $u->can('esim.manage')) {
        $more[] = ['heading' => 'Store & pricing'];
        $more[] = ['route' => 'admin.esim', 'label' => 'eSIM Control Center', 'icon' => 'signal'];
    }
    if ($support) {
        $more[] = ['heading' => 'People & support'];
        $more = array_merge($more, $support);
    }

    // NCI Operations Center (BUILD-17) — admin/super_admin, plus staff with nci.view.
    if ($isPrivileged || $u->can('nci.view')) {
        $more[] = ['heading' => 'Operations (NCI)'];
        $more[] = ['route' => 'admin.nci.registry', 'label' => 'Provider Registry', 'icon' => 'package'];
        $more[] = ['route' => 'admin.nci.health', 'label' => 'Health Monitor', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.nci.routing', 'label' => 'Routing Console', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.nci.wallets', 'label' => 'Wallets', 'icon' => 'wallet'];
    }

    // System — super-admin only.
    if ($isSuper) {
        $more[] = ['heading' => 'System'];
        $more[] = ['route' => 'admin.system-health', 'label' => 'System health', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.api-keys', 'label' => 'API keys', 'icon' => 'key'];
        $more[] = ['route' => 'admin.email', 'label' => 'Email', 'icon' => 'mail'];
        $more[] = ['route' => 'admin.errors', 'label' => 'Error log', 'icon' => 'file-text'];
        $more[] = ['route' => 'admin.backups', 'label' => 'Backups', 'icon' => 'package'];
        $more[] = ['route' => 'admin.updater', 'label' => 'Platform updater', 'icon' => 'upload'];
        $more[] = ['route' => 'admin.maintenance', 'label' => 'Maintenance', 'icon' => 'refresh'];
        $more[] = ['route' => 'admin.ui-kit', 'label' => 'UI Kit', 'icon' => 'grid'];
    }

    $primary[] = ['route' => 'admin.account', 'label' => 'My account', 'icon' => 'id-card'];
    $primary[] = ['route' => 'admin.security', 'label' => 'Security', 'icon' => 'shield'];
    // Everyone in the panel can hop back to the end-user app.
    $more[] = ['heading' => 'Shortcuts'];
    $more[] = ['route' => 'dashboard', 'label' => 'Storefront', 'icon' => 'globe'];
    // Admin-assignable "Download the app" slot (App Export §1).
    if (\App\Support\AppExport::placementActive('admin_menu')) {
        $more[] = ['route' => 'download', 'label' => \App\Support\AppExport::placementLabel('admin_menu'), 'icon' => 'download'];
    }
@endphp

<x-layouts.app :title="($title ?? 'Admin').' — NaaraSim'" :page-type="$preloaderType" :body-class="\App\Support\PlatformTheme::bodyClass()">
    <x-app-shell :primary="$primary" :more="$more" brand-label="NaaraSim Admin" brand-icon="settings" :brand-route="route('admin.dashboard')">
        {{ $slot }}
    </x-app-shell>

    {{-- One modal engine for every API-key help icon (Section 15.1). --}}
    <livewire:admin.api-guide-modal />
</x-layouts.app>
