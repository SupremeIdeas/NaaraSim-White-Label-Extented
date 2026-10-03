@php($__cards = \App\Support\NumbersBento::cards())
{{-- Numbers landing (Prompt 20 §4.1): active-line strip (only when the member has one) -> heading -> bento 2 up / 1 full / 2 up / 1 full,
     same on mobile. Cards come from NumbersBento::cards() (admin copy, badge, image, order, visibility); every card is the one
     x-nx.bento-card so any skin can restyle it. --}}
<x-nx.page style="margin-bottom:24px">
    @if ($activeLine ?? null)
        <div class="ns-strip ns-ring">
            <span class="ns-tile"><x-nx.icon name="cards" /></span>
            <div class="ns-text"><small>{{ __('numbers.your_naara') }}</small><b>{{ $activeLine->phone_number }}</b><span class="ns-live">{{ __('numbers.line_active') }}</span></div>
            <a href="{{ route('numbers.lines') }}" wire:navigate class="ns-btn">{{ __('numbers.manage') }}<x-nx.icon name="chevron-right" /></a>
            <a href="{{ route('wallet') }}" wire:navigate class="ns-btn ns-btn--solid">{{ __('numbers.top_up') }}</a>
        </div>
    @endif
    <h1 class="ns-h1" style="margin-top:{{ ($activeLine ?? null) ? '22' : '6' }}px">{{ __('numbers.what_today') }}</h1>
    <p class="ns-sub">{{ __('numbers.what_today_sub') }}</p>
    <div class="ns-grid">
        @foreach ($__cards as $card)
            <x-nx.bento-card :card="$card" />
        @endforeach
    </div>
</x-nx.page>
