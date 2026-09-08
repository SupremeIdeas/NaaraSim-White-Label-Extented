{{-- Alert card with a colored rail (Module 32). Variants: info | warning | danger. --}}
@props(['variant' => 'info', 'title' => null, 'icon' => 'info'])
<div {{ $attributes->merge(['class' => 'nx-alert nx-alert--'.$variant]) }} role="alert">
    <x-icon :name="$icon" class="mt-0.5 h-4 w-4 shrink-0" />
    <div class="min-w-0 text-sm">
        @if ($title)<p class="mb-0.5 font-semibold">{{ $title }}</p>@endif
        {{ $slot }}
    </div>
</div>
