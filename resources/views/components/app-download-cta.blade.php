@props(['placement', 'variant' => 'button'])
@php
    // Admin-assignable download CTA (App Export §1). Renders ONLY where the admin
    // has turned this placement on; the label is admin-editable per placement.
    // Points at the shared /download page (never an iOS direct-install path).
    $active = \App\Support\AppExport::placementActive($placement);
    $label = \App\Support\AppExport::placementLabel($placement);
@endphp
@if ($active)
    @if ($variant === 'banner')
        <a href="{{ route('download') }}"
            {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm font-semibold text-primary transition hover:bg-primary/10 dark:border-teal-300/20 dark:text-teal-300']) }}>
            <x-icon name="download" class="h-5 w-5 shrink-0" />
            <span class="min-w-0 flex-1">{{ $label }}</span>
            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 opacity-60" />
        </a>
    @elseif ($variant === 'nav')
        <a href="{{ route('download') }}"
            {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5']) }}>
            <x-icon name="download" class="h-4 w-4 text-primary dark:text-teal-300" />
            <span>{{ $label }}</span>
        </a>
    @else
        <a href="{{ route('download') }}"
            {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark']) }}>
            <x-icon name="download" class="h-4 w-4" /> {{ $label }}
        </a>
    @endif
@endif
