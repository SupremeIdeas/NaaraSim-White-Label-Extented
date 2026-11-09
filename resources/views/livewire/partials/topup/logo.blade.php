@php
    $u = \App\Support\TopupLogos::url($name, $country ?? null); $size = $size ?? 44;
    // No logo on file: a deterministic brand-coloured monogram (same network, same colour, everywhere) instead of a grey blank.
    $hue = crc32(strtolower($name)) % 360; $bg = $u ? \App\Support\TopupLogos::bg($name, $country ?? null) : null;
@endphp
<span class="ns-tp__logo {{ $bg ? 'has-bg' : '' }}" style="--s:{{ $size }}px;{{ $bg ? '--lb:'.$bg : '' }}">
    @if ($u)<img src="{{ $u }}" alt="" decoding="async" width="{{ $size }}" height="{{ $size }}">@else<b aria-hidden="true" style="background:hsl({{ $hue }} 62% 38%);color:#fff;border-radius:inherit;width:100%;height:100%;display:grid;place-items:center">{{ \App\Support\TopupLogos::initials($name) }}</b>@endif
</span>
