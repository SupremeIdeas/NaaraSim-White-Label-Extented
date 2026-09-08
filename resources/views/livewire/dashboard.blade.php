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
</div>
