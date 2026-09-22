@props(['placement', 'sectionKey' => null])
{{--
    Frontend-UX-fix blueprint Phase G — the one banner-carousel partial every
    placement renders through. Reuses the existing `<x-storytelling-carousel>`
    component and `App\Support\BannerPlacements`' shared slide set; only the
    admin-configured enabled flag and display style (via `BannerPlacements::
    config()`) vary per placement. Guarded the same way `LinkPreviewSettings`/
    `NumbersBento` already are: this can render on a page without
    RefreshDatabase (an unmigrated `settings` table), so a lookup failure
    degrades to "off" rather than 500ing the page.
--}}
@php
    try {
        $__bannerConfig = \App\Support\BannerPlacements::config($placement);
    } catch (\Throwable) {
        // Same fallback the homepage's own pre-Phase-G partial always used
        // for `marketing_home` (shown by default) — every other placement
        // is brand new, so it degrades to "off" like the rest of the
        // platform's on/off switches do on a lookup failure.
        $__bannerConfig = ['enabled' => $placement === 'marketing_home', 'display_style' => 'banner_with_description'];
    }
@endphp
@if ($__bannerConfig['enabled'])
    @php
        $__bannerSlides = \App\Support\BannerPlacements::slides($__bannerConfig['display_style']);
    @endphp
    <section data-reveal data-banner-placement="{{ $placement }}" class="px-4 py-16 sm:py-20">
        <div class="mx-auto w-full max-w-5xl">
            <div class="mb-10 text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">More from Naara</p>
                <h2 class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Everything else you can do here</h2>
            </div>
            <x-storytelling-carousel :slides="$__bannerSlides" :section-key="$sectionKey ?? 'banners-'.$placement" height="aspect-[16/9]" />
        </div>
    </section>
@endif
