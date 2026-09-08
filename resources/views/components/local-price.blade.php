{{-- Localized price (owner request). Shows a USD amount as the default USD price
     plus the viewer's local-currency equivalent (live FX, display only — the
     charge is always in USD). Reusable anywhere a USD price is shown. --}}
@props(['usd', 'currency' => null, 'class' => '', 'localClass' => 'text-xs text-slate-400 dark:text-slate-500'])
@php
    $cur = $currency ?: \App\Support\LocaleCurrency::resolve(auth()->user());
    $p = app(\App\Services\Pricing\CurrencyService::class)->localPrice((float) $usd, $cur);
@endphp
<span {{ $attributes->merge(['class' => $class]) }}>
    {{ $p['usd'] }}@if ($p['local'])<span class="{{ $localClass }}"> · ≈ {{ $p['local'] }}</span>@endif
</span>
