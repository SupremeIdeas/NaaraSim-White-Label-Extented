{{-- A referral link is the homepage itself with a ?ref= query string
     (App\Livewire\Referrals::mount()), so the referral-specific preview image
     only applies here, gated on that query param — every other visit keeps
     the site-wide default. --}}
<x-layouts.marketing :og-image="request()->filled('ref') ? \App\Support\LinkPreviewSettings::resolve('referral') : null">
    {{-- Per-theme custom landing page (owner request, 2026-09-07): a theme
         with its OWN hand-built landing layout (see LandingHeroLibrary)
         takes over the whole homepage content area — 'default' (every
         theme unless an admin deliberately assigns one) leaves the
         Section Builder / SiteContent flow below completely untouched. --}}
    @php($themeLandingStyle = \App\Support\ThemePreset::sectionStyle('landing_hero'))
    @if ($themeLandingStyle !== 'default' && \App\Support\LandingHeroLibrary::has($themeLandingStyle))
        @include(\App\Support\LandingHeroLibrary::bladeFor($themeLandingStyle), ['content' => \App\Support\ThemePreset::landingContent()])
    @else
    {{-- Section Builder: if an admin has published built sections for this page,
         render them through the shared renderer. Empty = fall back to the
         existing content below, so an untouched page is unchanged (BUILD-6 §B). --}}
    @php($builtSections = \App\Support\PageSections::live('home'))
    @if (! empty($builtSections))
        @include('partials.sections.render', ['sections' => $builtSections])
    @else
    @foreach ($sections as $key => $s)
        {{-- Page-specific partial wins; a portable/reused section falls back to
             the shared `marketing.sections.*` partial so it renders anywhere. --}}
        @includeFirst(['marketing.home.'.$key, 'marketing.sections.'.$key], ['s' => $s])

        {{-- Decorative, non-CMS sections injected at fixed anchors so the admin's
             section ordering stays intact. --}}
        @if ($key === 'hero')
            @include('marketing.home._flags')
            {{-- Homepage video (BUILD-3 §8) then story (§9), after the flag
                 carousel — both admin-editable, and self-hiding when unset. --}}
            @include('marketing.home._video')
            @include('marketing.home._story')
        @endif
        @if ($key === 'how')
            @include('marketing.home._wizard')
        @endif
    @endforeach
    @endif
    @endif

    {{-- Always the last section on the homepage, regardless of which content
         path above is active (theme landing page, Section Builder, or the
         legacy CMS loop) — see the partial's own docblock. --}}
    @include('marketing.home._banner_carousel')
</x-layouts.marketing>
