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
<svg {{ $attributes->merge(['class' => 'ns-i']) }} aria-hidden="true"><use href="#{{ $id }}"></use></svg>
