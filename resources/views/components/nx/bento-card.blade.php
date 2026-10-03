{{-- One Numbers bento card from NumbersBento::cards(). Same markup in every skin; tone comes from the card key (only Verify follows
     the member's accent), the badge from the admin-set label. Links either to a route or opens a Livewire modal. --}}
@props(['card'])
@php
    $tones = [
        'verify' => 'ns-card--primary', 'rent' => 'ns-card--rent ns-ring', 'line' => 'ns-card--line ns-ring ns-full',
        'internet_calls' => 'ns-card--net ns-ring', 'call_forwarding' => 'ns-card--fwd ns-ring', 'contact_management' => 'ns-card--ppl ns-ring ns-full',
    ];
    $isRoute = isset($card['link']['route']);
    $full = (int) $card['span'] === 6;
    $cls = 'ns-card '.($tones[$card['key']] ?? 'ns-card--rent ns-ring').($full && ! str_contains($tones[$card['key']] ?? '', 'ns-full') ? ' ns-full' : '');
    $badge = $card['badge_label'] ? strtolower(trim($card['badge_label'])) : null;
    $badgeVariant = match ($badge) { 'popular', 'hot' => 'gold', 'premium', 'featured' => 'out', default => null };
    $chips = (array) ($card['bullets'] ?? []);
    if ($card['key'] === 'verify' && count($chips) > 3) {
        $chips = array_merge(array_slice($chips, 0, 2), [end($chips)]);   // two services + the live "+N more"
    }
    $chipIcons = ['verify' => ['wa', 'send'], 'rent' => ['globe', 'pin', 'bolt'], 'line' => ['globe', 'phone', 'chat'], 'internet_calls' => ['check', 'check', 'check']];
@endphp
<{{ $isRoute ? 'a' : 'button' }}
    @if ($isRoute) href="{{ route($card['link']['route'], $card['link']['query'] ?? []) }}" wire:navigate
    @else type="button" wire:click="openModal('{{ $card['link']['modal'] }}')" wire:loading.attr="disabled" wire:target="openModal" @endif
    wire:key="bento-{{ $card['key'] }}" class="{{ $cls }}">
    <i class="ns-deco" aria-hidden="true"></i>
    @if ($full)
        <span class="ns-card__row">
            <span class="ns-tile"><x-nx.icon :name="$card['icon']" /></span>
            <span class="ns-card__text"><h3>{{ $card['title'] }}</h3></span>
            @if ($badge)<x-nx.pill :variant="$badgeVariant" style="margin-left:auto">{{ $card['badge_label'] }}</x-nx.pill>
            @else<x-nx.icon name="chevron-right" class="ns-chevron" style="margin-left:auto;font-size:20px" />@endif
        </span>
        <p style="margin-top:8px">{{ $card['subtitle'] }}</p>
    @else
        <span class="ns-card__top">
            <span class="ns-tile"><x-nx.icon :name="$card['icon']" /></span>
            @if ($badge)<x-nx.pill :variant="$badgeVariant">{{ $card['badge_label'] }}</x-nx.pill>@else<x-nx.icon name="chevron-right" />@endif
        </span>
        <h3>{{ $card['title'] }}</h3>
        <p>{{ $card['subtitle'] }}</p>
    @endif
    @if ($chips)
        <span class="ns-chips">
            @foreach ($chips as $i => $b)
                <span><x-nx.icon :name="($chipIcons[$card['key']][$i] ?? 'check')" />{{ $b }}</span>
            @endforeach
        </span>
    @endif
    @if (! empty($card['image']))<img class="ns-card__art" src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async">@endif
</{{ $isRoute ? 'a' : 'button' }}>
