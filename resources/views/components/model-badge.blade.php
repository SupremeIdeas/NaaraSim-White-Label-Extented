@props(['provider' => null, 'type' => null, 'esim' => false])
@php
    // Resolve the PUBLIC Model (never the raw supplier). Prefer the number type
    // (deterministic: otp→Verify, rental→Rent, permanent→Line); fall back to the
    // provider's lane; eSIM is always Naara Data.
    $model = $esim
        ? \App\Support\ProviderModels::esim()
        : ($type ? \App\Support\ProviderModels::forNumberType($type)
            : ($provider ? \App\Support\ProviderModels::forProvider($provider) : null));
@endphp
@if ($model)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary dark:bg-primary/20 dark:text-teal-300']) }}
          title="{{ $model['tagline'] }}">
        <x-icon :name="$model['icon']" class="h-3 w-3" /> {{ $model['name'] }}
    </span>
@endif
