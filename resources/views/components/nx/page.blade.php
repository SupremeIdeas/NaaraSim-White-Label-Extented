{{-- Skin canvas for a dashboard page (Prompt 20 §23). Wrap a converted page's content in this; the page keeps its own layout,
     header and bottom nav. `bleed=false` keeps the framed panel everywhere (the Appearance page uses that). `bare` is for a component
     that renders both as a page and embedded inside another skin page (Withdraw inside Wallet): embedded it adds no second canvas. --}}
@props(['bleed' => true, 'bare' => false])
@if ($bare)
    <div {{ $attributes }}>{{ $slot }}</div>
@else
<div {{ $attributes->merge(['class' => 'ns-app'.($bleed ? ' ns-app--flush' : '')]) }}@if ($cc = \App\Support\Appearance\PageSkin::countryCode()) data-cc="{{ $cc }}"@endif>
    <div class="ns-app__body">{{ $slot }}</div>
</div>
@endif
