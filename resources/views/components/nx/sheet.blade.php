{{-- ONE sheet chrome for every flow (Verify / Rent / Line ...). mode=modal: fixed overlay (bottom sheet on phones, centred dialog
     on desktop) with scrim, focus trap and Escape. mode=page: the same header and body laid out in flow (the admin's "dedicated
     page" setting). The body scrolls; the `foot` slot (price bar + CTA) stays pinned at the bottom of the sheet. --}}
@props(['mode' => 'modal', 'title' => '', 'subtitle' => null, 'icon' => 'phone', 'closeAction' => 'closeModal', 'closeLabel' => null, 'backLabel' => null, 'label' => null])
@php($isPage = $mode === 'page')
@if ($isPage)<div class="ns-flow--page ns-flow" role="region" aria-label="{{ $label ?? $title }}">@else
<div class="ns-modal" x-data x-trap.noscroll="true" @keydown.escape.window="$wire.{{ $closeAction }}()" role="dialog" aria-modal="true" aria-label="{{ $label ?? $title }}">
    <div class="ns-scrim" wire:click="{{ $closeAction }}"></div>
@endif
    <div class="ns-sheet">
        <div class="ns-handle"></div>
        <div class="ns-sheet__head">
            <button type="button" class="ns-sheet__back" wire:click="{{ $closeAction }}" aria-label="{{ $backLabel ?? __('numbers.back_to_numbers') }}"><x-nx.icon name="chevron-left" /></button>
            <span class="ns-tile"><x-nx.icon :name="$icon" /></span>
            <div class="ns-text"><b>{{ $title }}</b>@if ($subtitle)<small>{{ $subtitle }}</small>@endif</div>
            <button type="button" class="ns-sheet__close" wire:click="{{ $closeAction }}" aria-label="{{ $closeLabel ?? __('numbers.close') }}"><x-nx.icon name="x" /></button>
        </div>
        <div class="ns-sheet__body">{{ $slot }}</div>
        @if (isset($foot) && ! $foot->isEmpty())<div class="ns-sheet__foot">{{ $foot }}</div>@endif
    </div>
@if ($isPage)</div>@else</div>@endif
