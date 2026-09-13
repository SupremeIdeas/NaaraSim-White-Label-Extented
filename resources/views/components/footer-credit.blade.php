@props(['linkClass' => 'text-slate-300', 'yearClass' => ''])
{{--
    Shared footer attribution line (theme-integrity blueprint §1.4/§2). Used by
    site-footer.blade.php's default footer AND every theme-sections/footer/*
    variant — previously this exact sentence was hand-duplicated in 13 places,
    which is why fixing the missing link, or changing the phrasing, once meant
    editing 13 files. Now it's one component; every variant just passes its own
    text-color class for the agency name so the persona's palette is preserved.
    `yearClass` is a rare second escape hatch (fintra-clean's own fintech-ledger
    monospaced-year styling) — empty everywhere else.

    "Supreme Ideas Agency" is a brand constant and always rendered, always
    linked to supremeideas.agency (new tab) — only the surrounding PHRASING is
    admin-configurable (App\Support\SiteChrome::footerCreditParts()).
--}}
@php($parts = \App\Support\SiteChrome::footerCreditParts())
<span>@if ($yearClass)<span class="{{ $yearClass }}">&copy; {{ date('Y') }}</span>@else&copy; {{ date('Y') }}@endif {{ \App\Support\BrandSettings::name() }}. {{ $parts['prefix'] }}<a
        href="{{ \App\Support\SiteChrome::AGENCY_URL }}" target="_blank" rel="noopener"
        class="{{ $linkClass }} hover:underline">{{ \App\Support\SiteChrome::AGENCY_NAME }}</a>{{ $parts['suffix'] }} All rights reserved.</span>
