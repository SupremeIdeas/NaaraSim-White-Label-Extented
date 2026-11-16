{{-- Skin-system icon: the wireframe's 1em stroke icon. Wireframe glyphs come from the nx-* sprite (verbatim from the visual
     contract, so every skin shows the same icons as the wireframes); anything the wireframe never drew falls back to the
     platform sprite. Size comes from font-size. --}}
@props(['name'])
@php
    $slug = \Illuminate\Support\Str::slug($name);
    $nx = ['file', 'idcard', 'plus', 'user', 'cog', 'code', 'receipt', 'shield', 'hash', 'phone', 'fwd', 'globe', 'pin', 'bolt', 'bars', 'star', 'chev', 'left', 'x', 'bell', 'menu', 'dl', 'info', 'lock', 'cal', 'users', 'gift', 'wallet', 'grid', 'list', 'chat', 'search', 'heart', 'check', 'home', 'wa', 'send', 'cards', 'play', 'stop', 'volume', 'vibrate'];
    $alias = ['chevron-left' => 'left', 'arrow-left' => 'left', 'chevron-right' => 'chev', 'download' => 'dl', 'calendar' => 'cal', 'map-pin' => 'pin', 'message-circle' => 'chat', 'id-card' => 'idcard', 'phone-forwarded' => 'fwd', 'credit-card' => 'cards', 'zap' => 'bolt', 'settings' => 'cog', 'shield-check' => 'shield', 'badge-check' => 'check', 'signal' => 'bars', 'file-text' => 'file', 'help-circle' => 'info', 'smartphone' => 'phone', 'volume-2' => 'volume', 'vibration' => 'vibrate'];
    $id = in_array($alias[$slug] ?? $slug, $nx, true) ? 'nx-'.($alias[$slug] ?? $slug) : 'i-'.$slug;
@endphp
@php($__duo = \App\Support\DuoIcons::get($slug))
@if ($__duo)
<svg {{ $attributes->merge(['class' => 'ns-i nx-duo nx-duo--drawn']) }} viewBox="0 0 24 24" fill="none" aria-hidden="true" style="overflow:visible">
    <g class="nx-duo__f" transform="translate(1.700 1.700)" fill="rgb(var(--nx-gold, 212 160 23))" fill-opacity=".38" stroke="none">{!! $__duo[1] !!}</g>
    <g stroke="currentColor" stroke-width="1.700" stroke-linecap="round" stroke-linejoin="round">{!! $__duo[0] !!}</g>
</svg>
@else
<svg {{ $attributes->merge(['class' => 'ns-i']) }} aria-hidden="true"><use href="#{{ $id }}"></use></svg>
@endif
