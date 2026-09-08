{{-- Swappable HEADER section — "default" style family (the original,
     unmodified mobile brand header). Resolved by
     ThemePreset::sectionStyle('header') from components/app-shell.blade.php;
     variables are inherited from that parent view's scope (Blade @include
     shares the caller's data). Any other style family placed alongside this
     file must accept the same inherited variables: $brandRoute, $headerBrand,
     $brandIcon, $headerActions (optional slot). This is the STANDARD brand
     header only — the /numbers/* wallet-bar header is page-specific chrome,
     not part of this swappable section.

     `data-header-root` (header editor, 2026-09-07): every header style
     partial marks its actual background-carrying element with this
     attribute — ThemePreset::headerStyleCss() targets it to apply an
     admin's colour/corner-radius/glass-depth override, scoped to exclude
     /adminmaster (see that method's docblock). --}}
<header data-header-root class="nx-header-fade sticky top-0 z-30 flex items-center justify-between px-4 py-3 lg:hidden">
    <a href="{{ $brandRoute ?? '#' }}" wire:navigate class="flex items-center">
        <x-brand-logo :variant="$headerBrand['variant']" :label="$headerBrand['label']" size="md" :fallback-icon="$brandIcon" />
    </a>
    <div class="flex items-center gap-1">
        {{ $headerActions ?? '' }}
        {{-- Layouts that supply headerActions (customer) place the toggle
             themselves in the bell → toggle → hamburger order; only add one
             here for layouts that don't (admin), so it's never doubled. --}}
        @unless (isset($headerActions))<x-theme-toggle />@endunless
    </div>
</header>
