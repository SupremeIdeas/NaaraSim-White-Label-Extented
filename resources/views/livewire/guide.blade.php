@php
    $sections = collect($guide->sections ?? [])->filter(fn ($s) => is_array($s) && ($s['heading'] ?? '') !== '');
@endphp
{{-- Guides on the skin system (S3 Batch 6): admin-edited sections and agreement, rendered with the prose token styles. --}}
<div>
<x-nx.page class="ns-pg ns-pg--read">
    {{-- Header --}}
    <span class="ns-pg__kicker" style="margin-top:6px">{{ \App\Support\UserGuides::label($audience) }}</span>
    <h1 class="ns-h1" style="margin-top:4px">{{ $guide->title }}</h1>
    @if ($guide->intro)<p class="ns-sub">{{ $guide->intro }}</p>@endif

    {{-- Quick switch between guides --}}
    <div class="ns-pg__filters" role="navigation" aria-label="Guides">
        @foreach (\App\Support\UserGuides::AUDIENCES as $a)
            <a href="{{ route('guide', ['audience' => $a]) }}" wire:navigate class="ns-nt__chip {{ $audience === $a ? 'is-on' : '' }}" style="display:inline-flex;align-items:center" @if ($audience === $a) aria-current="page" @endif>{{ \App\Support\UserGuides::label($a) }}</a>
        @endforeach
    </div>

    {{-- Sections --}}
    <div class="ns-pg__stack" style="margin-top:8px">
        @foreach ($sections as $section)
            <div class="ns-pg__card ns-ring">
                <h2 class="ns-pg__h2" style="font-size:18px">{{ $section['heading'] }}</h2>
                @if (! empty($section['image']))<img src="{{ $section['image'] }}" alt="" loading="lazy" class="ns-pg__figure">@endif
                @if (! empty($section['body']))
                    <div class="ns-pg__prose" style="margin-top:8px">{!! \App\Support\HtmlSanitizer::clean($section['body']) !!}</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Agreement / policy --}}
    @if ($guide->agreement)
        <div class="ns-pg__card" style="margin-top:24px;background:rgb(var(--nx-surface-2));box-shadow:inset 0 0 0 1.5px rgb(var(--nx-teal) / .5)">
            <h2 class="ns-pg__h2" style="display:flex;align-items:center;gap:8px;margin-bottom:8px"><x-nx.icon name="shield" /> Your agreement &amp; policy</h2>
            <div class="ns-pg__prose">{!! \App\Support\HtmlSanitizer::clean($guide->agreement) !!}</div>
        </div>
    @endif
</x-nx.page>
</div>
