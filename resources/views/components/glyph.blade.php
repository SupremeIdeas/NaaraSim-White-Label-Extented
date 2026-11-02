{{-- Premium duotone glyph for marketing surfaces: a soft filled shape, a clean 1.6 stroke and one accent stroke, all on a 24 grid with round caps.
     Drawn for this platform (not a stock set). Colours come from currentColor and --glyph-accent, so it follows the palette in light and dark.
     `tile` puts it on a graphite glass tile. Any name without custom art falls back to the plain sprite icon. --}}
@props(['name', 'size' => 24, 'tile' => false, 'class' => ''])
@php
    $g = [
        'phone' => ['M6.5 3h3l1.5 4-2 1.5a11 11 0 0 0 5.5 5.5L16 12l4 1.5v3a2 2 0 0 1-2 2A15 15 0 0 1 4.5 5 2 2 0 0 1 6.5 3Z', null, 'M14.5 3.5a6 6 0 0 1 6 6M14.5 6.5a3 3 0 0 1 3 3'],
        'message-circle' => ['M21 11.5a8.5 8.5 0 0 1-12.4 7.5L3 20.5l1.5-5A8.5 8.5 0 1 1 21 11.5Z', null, 'M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01'],
        'mail' => ['M3.5 6.5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2Z', null, 'm4 7.5 8 6 8-6'],
        'zap' => ['M13 2.5 4.5 13.5H11l-1 8 8.5-11H12Z', null, null],
        'target' => ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z', 'M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Z', 'M12 12h.01'],
        'help-circle' => ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z', 'M9.5 9.2a2.6 2.6 0 0 1 5 .9c0 1.7-2.5 2.2-2.5 3.7', 'M12 17.3h.01'],
        'eye' => ['M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z', null, 'M12 9.2a2.8 2.8 0 1 0 0 5.6 2.8 2.8 0 0 0 0-5.6Z'],
        'plane' => ['M21 3 3 10.2l6.8 2.9 2.9 6.8Z', null, 'M21 3 9.8 13.1'],
        'send' => ['M21 3 3 10.2l6.8 2.9 2.9 6.8Z', null, 'M21 3 9.8 13.1'],
        'signal' => [null, null, 'M5 19v-3M10 19v-7M15 19V9M20 19V5'],
        'file-text' => ['M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z', null, 'M14 3v5h5M9 13h6M9 16.5h4'],
        'download' => ['M4 16h16v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z', null, 'M12 3.5v11m0 0 4-4m-4 4-4-4'],
        'id-card' => ['M3.5 6.5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2Z', null, 'M8.8 9.6a1.8 1.8 0 1 0 0 3.6 1.8 1.8 0 0 0 0-3.6ZM6.4 17c.4-1.4 1.3-2 2.4-2s2 .6 2.4 2M14 10h4M14 13.5h3'],
        'globe' => ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z', null, 'M3 12h18M12 3c2.6 2.6 3.8 5.6 3.8 9s-1.2 6.4-3.8 9c-2.6-2.6-3.8-5.6-3.8-9S9.4 5.6 12 3Z'],
        'clock' => ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z', null, 'M12 7.5V12l3 2'],
        'wallet' => ['M3.5 8a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v9.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2Z', 'M3.5 8V6.5a2 2 0 0 1 2-2H16', 'M20.5 12h-4a1.8 1.8 0 0 0 0 3.6h4'],
        'trending-up' => ['M3 17l6-6 4 4 8-8v12H3Z', null, 'M3 17l6-6 4 4 8-8M15 7h6v6'],
        'sparkles' => ['M12 3.5l1.9 5.1 5.1 1.9-5.1 1.9L12 17.5l-1.9-5.1L5 10.5l5.1-1.9Z', null, 'M19 15.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8Z'],
        'heart' => ['M12 20.5S3.5 15 3.5 9.2A4.7 4.7 0 0 1 12 6.6a4.7 4.7 0 0 1 8.5 2.6C20.5 15 12 20.5 12 20.5Z', null, null],
        'shield-check' => ['M12 3 4.5 6v5.5c0 4.5 3.1 8 7.5 9.5 4.4-1.5 7.5-5 7.5-9.5V6Z', null, 'm8.8 12 2.4 2.4 4-4.4'],
        'sim' => ['M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z', null, 'M9 12h6v6H9ZM9 15h6M12 12v6'],
        'smartphone' => ['M8 2.5h8a2 2 0 0 1 2 2v15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-15a2 2 0 0 1 2-2Z', null, 'M10.5 18.5h3M10 5.5h4'],
        'badge-check' => ['M12 2.8l2.4 1.8 3-.2.9 2.9 2.5 1.7-1 2.8 1 2.8-2.5 1.7-.9 2.9-3-.2L12 21.2l-2.4-1.8-3 .2-.9-2.9-2.5-1.7 1-2.8-1-2.8 2.5-1.7.9-2.9 3 .2Z', null, 'm8.6 12.2 2.4 2.4 4.4-4.8'],
        'star' => ['M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9Z', null, null],
        'play' => ['M7 4.5v15l12-7.5Z', null, null],
        'check' => [null, null, 'm5 12.5 4.5 4.5L19 7.5'],
        'search' => ['M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Z', null, 'm20 20-4-4'],
        'list' => [null, 'M8 6.5h12M8 12h12M8 17.5h12', 'M4 6.5h.01M4 12h.01M4 17.5h.01'],
        'terminal' => ['M3.5 5.5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2Z', null, 'm7.5 9.5 3 2.5-3 2.5M12.5 15h4'],
        'gift' => ['M4 11h16v8.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19.5Z', 'M3 7.5h18V11H3ZM12 7.5V21', 'M12 7.5C12 5 10.8 3.5 9 3.5a2 2 0 0 0 0 4h3Zm0 0c0-2.5 1.2-4 3-4a2 2 0 0 1 0 4h-3Z'],
        'refresh' => [null, 'M20 11a8 8 0 0 0-14.5-3.7M4 13a8 8 0 0 0 14.5 3.7', 'M5.5 3.5v4h4M18.5 20.5v-4h-4'],
        'lock' => ['M5.5 11h13a1.5 1.5 0 0 1 1.5 1.5v6a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-6A1.5 1.5 0 0 1 5.5 11Z', 'M8 11V8a4 4 0 0 1 8 0v3', 'M12 15v2'],
        'user-plus' => ['M9.5 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z', 'M2.5 20c.5-3.5 3.3-5.5 7-5.5s6.5 2 7 5.5', 'M19 8v6M16 11h6'],
        'qr-code' => [null, 'M4 4h6v6H4ZM14 4h6v6h-6ZM4 14h6v6H4Z', 'M14 14h2.5v2.5H14ZM18 18h2v2h-2ZM18 14h2M14 19v1'],
        'headset' => ['M3.5 13h3v5h-1.5a1.5 1.5 0 0 1-1.5-1.5ZM20.5 13h-3v5h1.5a1.5 1.5 0 0 0 1.5-1.5Z', 'M4 14v-2a8 8 0 0 1 16 0v2', 'M17.5 18.5c0 1.5-2 2.5-5.5 2.5'],
        'credit-card' => ['M3.5 6.5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2Z', null, 'M3.5 9.5h17M7 15h3'],
        'wifi' => [null, 'M2.5 9a14 14 0 0 1 19 0M5.5 12.5a9.5 9.5 0 0 1 13 0', 'M8.8 16a5 5 0 0 1 6.4 0M12 19.5h.01'],
        'map-pin' => ['M12 21s-6.5-5.7-6.5-10.5a6.5 6.5 0 1 1 13 0C18.5 15.3 12 21 12 21Z', null, 'M12 8.5a2.2 2.2 0 1 0 0 4.4 2.2 2.2 0 0 0 0-4.4Z'],
    ];
    $d = $g[$name] ?? null;
@endphp
@if (! $d)
    <x-icon :name="$name" :class="$class ?: 'h-5 w-5'" />
@else
    @php $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" width="'.($tile ? (int) round($size * 0.58) : $size).'" height="'.($tile ? (int) round($size * 0.58) : $size).'" class="glyph '.e($tile ? '' : $class).'">'.($d[0] ? '<path d="'.$d[0].'" fill="currentColor" fill-opacity=".14" stroke="none"/><path d="'.$d[0].'"/>' : '').($d[1] ? '<path d="'.$d[1].'"/>' : '').($d[2] ? '<path d="'.$d[2].'" stroke="var(--glyph-accent, currentColor)" stroke-width="2"/>' : '').'</svg>'; @endphp
    @if ($tile)
        <span {{ $attributes->merge(['class' => 'glyph-tile inline-flex shrink-0 items-center justify-center '.$class]) }} style="width:{{ $size }}px;height:{{ $size }}px">{!! $svg !!}</span>
    @else
        {!! $svg !!}
    @endif
@endif
