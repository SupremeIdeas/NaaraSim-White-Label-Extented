{{-- Bento card (Prompt 20 §3): one markup for every skin. The decorative <i class="ns-deco"> is emitted HERE (not by JS as in the
     wireframes) so skins can draw their motif behind the content. `tone` is the class modifier: rent|line|net|fwd|ppl|cyan. --}}
@props(['tone' => null, 'primary' => false, 'icon' => null, 'title' => null, 'text' => null, 'chips' => [], 'href' => null, 'full' => false])
@php
    $cls = 'ns-card'.($primary ? ' ns-card--primary' : '').($tone ? ' ns-card--'.$tone : '').($full ? ' ns-full' : '');
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $cls]) }}>
    <i class="ns-deco" aria-hidden="true"></i>
    <span class="ns-card__top">
        @if ($icon)<span class="ns-tile"><x-nx.icon :name="$icon" /></span>@endif
        {{ $top ?? '' }}
    </span>
    @if ($title)<h3>{{ $title }}</h3>@endif
    @if ($text)<p>{{ $text }}</p>@endif
    @if ($chips)
        <span class="ns-chips">@foreach ($chips as $c)<span><x-nx.icon :name="$c[0]" />{{ $c[1] }}</span>@endforeach</span>
    @endif
    {{ $slot }}
</{{ $tag }}>
