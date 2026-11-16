@props(['size' => 64])
{{-- Hand-drawn two-colour fingerprint: ridge lines in the skin's brand ink over an offset soft fingertip in the accent. Skin tokens only. --}}
<svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" aria-hidden="true" {{ $attributes }} style="overflow:visible;flex:none">
    <g transform="translate(1 1.200)" fill="rgb(var(--nx-teal, 10 155 148))" fill-opacity=".30" stroke="none"><path d="M7 12.200a5 5 0 0 1 10 0c0 3.200-.4 5.600-1.100 8.300H8.100C7.400 17.800 7 15.400 7 12.200z"/></g>
    <g stroke="rgb(var(--nx-teal-ink, 10 110 110))" stroke-width="1.500" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 10a2 2 0 0 0-2 2c0 1.020-.1 2.510-.26 4"/>
        <path d="M14 13.120c0 2.380 0 6.380-1 8.880"/>
        <path d="M17.290 21.020c.12-.6.43-2.300.5-3.020"/>
        <path d="M2 12a10 10 0 0 1 18-6"/>
        <path d="M2 16h.01"/>
        <path d="M21.800 16c.2-2 .131-5.354 0-6"/>
        <path d="M5 19.500C5.500 18 6 15 6 12a6 6 0 0 1 .34-2"/>
        <path d="M8.650 22c.21-.66.45-1.320.57-2"/>
        <path d="M9 6.800a6 6 0 0 1 9 5.200v2"/>
    </g>
</svg>
