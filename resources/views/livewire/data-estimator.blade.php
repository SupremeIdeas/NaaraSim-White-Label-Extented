{{-- Data estimator on the skin system (S3 Batch 3): same radios + slider + estimate, now with a suggested-data hero and a matching-plans CTA. --}}
<x-nx.page class="ns-narrow">
    <a href="{{ route('catalogue') }}" wire:navigate class="ns-back"><x-nx.icon name="chevron-left" /> {{ __('esim.back') }}</a>
    <h1 class="ns-h1" style="margin-top:6px">Data estimator</h1>
    <p class="ns-sub">A rough guide to how much data you’ll need — so you buy the right plan, not too much or too little.</p>

    <div class="ns-lbl" style="margin-top:18px">How will you use it?</div>
    @foreach ($profiles as $key => $p)
        <label wire:key="profile-{{ $key }}" class="ns-row ns-ring {{ $profile === $key ? 'is-selected' : '' }}" style="cursor:pointer;height:auto;min-height:56px;padding:12px 14px">
            <input type="radio" wire:model.live="profile" value="{{ $key }}" class="ns-check-input" style="margin:0">
            <span class="ns-row__label">{{ $p['label'] }}</span>
        </label>
    @endforeach

    <label class="ns-lbl" for="est-days" style="display:flex;justify-content:space-between;margin-top:22px">
        <span>How many days?</span><b  class="ns-linkink" style="font-variant-numeric:tabular-nums">{{ $days }}</b>
    </label>
    <input id="est-days" type="range" min="1" max="60" wire:model.live="days" class="ns-range">

    <x-nx.balance-hero style="margin-top:20px" label="You’ll likely need about" icon="bars" :amount="'≈ '.$estimate['gb'].' GB'"
                       :sub="'for '.$days.' day'.($days > 1 ? 's' : '').' of '.\Illuminate\Support\Str::of($estimate['label'])->before(' —').' use'" />

    <a href="{{ route('catalogue') }}" wire:navigate class="ns-cta" style="margin-top:18px"><x-nx.icon name="globe" /> Find a plan <x-nx.icon name="chevron-right" /></a>
    <p class="ns-sub" style="font-size:13px;text-align:center">Estimates only — real usage varies with apps and video quality.</p>
</x-nx.page>
