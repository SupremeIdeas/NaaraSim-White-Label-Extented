{{-- Round avatar: image when given, otherwise initials on a colour derived from the name (never a stranger's photo). --}}
@props(['name' => '', 'src' => null, 'size' => 42])
@php
    $initials = collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $hue = crc32((string) $name) % 360;
    $style = "width:{$size}px;height:{$size}px;".($src ? '' : "background:hsl({$hue} 45% 38%);").$attributes->get('style');
@endphp
<span {{ $attributes->except('style')->merge(['class' => 'ns-avatar']) }} style="{{ $style }}" aria-hidden="true">@if ($src)<img src="{{ $src }}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover">@else{{ $initials ?: '?' }}@endif</span>
