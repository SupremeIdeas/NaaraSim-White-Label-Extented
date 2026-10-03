{{-- Premium feature link: a full-width accent card that sells one specific thing and sends the member to its flow (port-in, upgrades...).
     Built on the skin's primary card, so every skin restyles it; nothing here carries a colour of its own. --}}
@props(['href', 'icon' => 'phone-forwarded', 'kicker' => null, 'title' => null, 'text' => null, 'cta' => null, 'chips' => []])
<a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => 'ns-card ns-card--primary ns-ring ns-featlink']) }}>
    <i class="ns-deco" aria-hidden="true"></i>
    <span class="ns-featlink__tile"><x-nx.icon :name="$icon" /></span>
    <span class="ns-featlink__body">
        @if ($kicker)<small class="ns-featlink__kicker">{{ $kicker }}</small>@endif
        <b>{{ $title }}</b>
        @if ($text)<span class="ns-featlink__text">{{ $text }}</span>@endif
        @if (! empty($chips))
            <span class="ns-featlink__chips">
                @foreach ($chips as $chip)<span class="ns-featlink__chip"><x-nx.icon name="check" /> {{ $chip }}</span>@endforeach
            </span>
        @endif
    </span>
    @if ($cta)<span class="ns-featlink__go">{{ $cta }} <x-nx.icon name="chevron-right" /></span>@endif
</a>
