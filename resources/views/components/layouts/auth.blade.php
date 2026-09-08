@props(['title' => null, 'heading' => null, 'subheading' => null, 'preloaderType' => 'auth'])

{{-- Two-column auth shell (Module 28). Desktop: an admin-set media panel
     (WebP/JPEG image or a short muted video) on the left, the form on the
     right; the panel collapses on mobile to a compact branded header. Every
     auth page ends with the assignable footer (Supreme Ideas Agency + legal
     links). Dark mode + reduced-motion throughout. --}}
@php($panel = \App\Support\SiteChrome::authPanel())
@php($authStyle = $panel['style'] ?? 'auto')
@php($useMedia = \App\Support\SiteChrome::hasAuthMedia() && $authStyle !== 'webgl')
@php($useWebgl = $authStyle === 'webgl' || ($authStyle === 'auto' && ! \App\Support\SiteChrome::hasAuthMedia()))
<x-layouts.app :title="$title ?? \App\Support\BrandSettings::name()" :page-type="$preloaderType">
    {{-- Swappable LOGIN section (owner request, 2026-09-07): admin can point
         this at any built style family via ThemePreset::sectionStyle('login')
         — 'default' (below) is the original, unmodified markup, so every
         existing theme keeps rendering today's exact login screen. --}}
    @include('components.layouts.theme-sections.login.'.\App\Support\ThemePreset::sectionStyle('login'))
</x-layouts.app>
