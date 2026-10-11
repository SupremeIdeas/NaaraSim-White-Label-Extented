{{-- Skin sheet driven by an Alpine flag on an ancestor (`show` is the flag's name, e.g. "sheet"). Same chrome as x-nx.sheet: bottom sheet on a
     phone, centred dialog on desktop, scrim, focus trap, Escape. `foot` slot is pinned under the scrolling body. --}}
@props(['show', 'title' => '', 'subtitle' => null, 'icon' => 'user'])
<div x-show="{{ $show }}" x-cloak class="ns-modal" x-trap.noscroll="{{ $show }}" @keydown.escape.window="{{ $show }} = false" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="ns-scrim" @click="{{ $show }} = false"></div>
    <div class="ns-sheet">
        <div class="ns-handle"></div>
        <div class="ns-sheet__head">
            <span class="ns-tile"><x-nx.icon :name="$icon" /></span>
            <div class="ns-text"><b>{{ $title }}</b>@if ($subtitle)<small>{{ $subtitle }}</small>@endif</div>
            <button type="button" class="ns-sheet__close" @click="{{ $show }} = false" aria-label="Close"><x-nx.icon name="x" /></button>
        </div>
        <div class="ns-sheet__body">{{ $slot }}</div>
        @if (isset($foot) && ! $foot->isEmpty())<div class="ns-sheet__foot" style="padding:12px 16px 24px">{{ $foot }}</div>@endif
    </div>
</div>
