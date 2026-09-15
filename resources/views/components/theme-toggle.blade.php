{{-- Theme toggle (blueprint Section 4.3 · BLUEPRINT-batch1-sections §2 ·
     Theme Toggle Studio). Resolves the admin-selected visual style and
     includes that preset's partial — the single choke point every header/
     login/UI-kit usage renders through, so changing the style in Admin →
     Theme Toggle changes it everywhere at once. Zero regression: an
     unconfigured install renders exactly the original sun-moon markup. --}}
@include('components.theme-toggle.'.\App\Support\ThemeToggleSettings::current())
