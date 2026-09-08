@props(['provider', 'field'])
{{-- Opens the single ApiGuideModal for this provider+field (blueprint 15.2). --}}
<button type="button"
        wire:click="$dispatch('open-api-guide', { provider: '{{ $provider }}', field: '{{ $field }}' })"
        class="ml-1.5 rounded-full p-1 text-primary transition-colors hover:bg-primary/10 dark:hover:bg-primary/20"
        aria-label="Setup guide for {{ $field }}">
    <x-icon name="help-circle" class="h-4 w-4" />
</button>
