@props(['class' => 'h-4 w-4'])
{{-- The one app-wide spinner (owner request). Soft dual-tone rotating ring in
     currentColor — drops in wherever the spinning refresh icon used to be, with
     the same size classes. Respects prefers-reduced-motion (pulses instead). --}}
<span {{ $attributes->merge(['class' => 'nx-spinner '.$class]) }} role="status" aria-label="Loading"></span>
