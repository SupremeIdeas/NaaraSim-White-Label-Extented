<div>
    {{--
        Dashboard home (Theme Batch 2 §2). The page's composition is chosen by the
        active theme via ThemePreset::layoutVariant('dashboard_home') — one of the
        (at most) three structural partials, each assembling the SAME shared block
        partials in a different order. It never changes a Livewire property, a
        query, or a business rule; only presentational arrangement varies. Defaults
        to variant-a (the extracted, unchanged baseline) for any theme without an
        explicit choice, so a missing/mid-migration preset never breaks the page.
    --}}
    @include('livewire.partials.dashboard-home-'.\App\Support\ThemePreset::layoutVariant('dashboard_home'))

    {{-- Frontend-UX-fix blueprint Phase G — the `dashboard_footer` banner
         placement (App\Support\BannerPlacements). Rendered once here, after
         all 3 layout variants' shared content, so it always sits at the true
         bottom of the dashboard regardless of which variant is active — and
         is OFF by default, so an unconfigured install renders nothing new
         here at all. --}}
    @include('partials.banner-carousel', ['placement' => 'dashboard_footer'])
</div>
