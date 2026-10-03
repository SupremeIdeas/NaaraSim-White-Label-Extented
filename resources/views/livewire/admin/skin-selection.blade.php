{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
{{-- Your skins (Prompt 22): the licensee picks which skins their licence fills; every other skin is hidden from their members. --}}
<x-nx.page :bleed="false" class="ns-adm">
    <h1 class="ns-adm__title">Your skins</h1>
    <p class="ns-sub">Your licence unlocks a set number of dashboard skins. Pick which ones your members can use, and which is the default. Every other skin stays hidden.</p>

    @if (! $activated)
        <x-nx.note icon="info" variant="dash" style="margin-top:14px">Skins unlock once your licence has been activated and has checked in with the original platform. Until then your members see the standard skin only. Open the <a class="ns-linkink" href="{{ route('admin.updater', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate>Platform updater</a> and press Refresh now to check in.</x-nx.note>
    @else
        <div class="ns-adm__grid ns-adm__grid--3" style="margin-top:14px">
            <x-nx.stat label="Your licence unlocks" :value="$allowance.' '.\Illuminate\Support\Str::plural('skin', $allowance)" />
            <x-nx.stat label="Selected" :value="count($picked).' of '.$allowance" :tone="count($picked) >= 1 ? 'ok' : null" />
            <x-nx.stat label="Hidden from members" :value="$hiddenCount" />
        </div>
        @if ($canAddMore > 0 && count($saved) > 0)
            <x-nx.note icon="info" style="margin-top:10px">You can add {{ $canAddMore }} more {{ \Illuminate\Support\Str::plural('skin', $canAddMore) }} to your selection.</x-nx.note>
        @endif
        @if (count($saved) === 0)
            <x-nx.banner icon="sparkles" title="Choose your skins" text="Nothing is saved yet, so your members see the standard skin only. Pick your skins below and save." style="margin-top:10px" />
        @endif

        @if ($message)<x-nx.note icon="check" style="margin-top:10px" role="status">{{ $message }}</x-nx.note>@endif
        @if ($error)<x-nx.note icon="info" variant="warn" style="margin-top:10px" role="alert">{{ $error }}</x-nx.note>@endif

        <div class="ns-adm__chips" style="margin-top:16px">
            <button type="button" wire:click="$set('group','all')" class="ns-pill {{ $group === 'all' ? 'ns-pill--best' : '' }}">All</button>
            @foreach ($allGroups as $g)
                <button type="button" wire:click="$set('group', '{{ $g }}')" class="ns-pill {{ $group === $g ? 'ns-pill--best' : '' }}" wire:key="g-{{ $g }}">{{ $g }}</button>
            @endforeach
        </div>
        <label class="ns-field" style="display:block;margin-top:10px">
            <span class="ns-lbl">Search skins</span>
            <input type="search" wire:model.live.debounce.300ms="search" class="ns-input" placeholder="Search skins" aria-label="Search skins">
        </label>

        <div class="ns-skins" style="margin-top:14px">
            @foreach ($groups as $gname => $items)
                <span class="ns-group">{{ $gname }}</span>
                @foreach ($items as $key => $s)
                    @php($on = in_array($key, $picked, true))
                    @php($full = ! $on && count($picked) >= $allowance)
                    <div class="ns-skin-card {{ $on ? 'is-selected' : '' }}" wire:key="sk-{{ $key }}" style="{{ $full ? 'opacity:.55' : '' }}">
                        <button type="button" wire:click="toggle('{{ $key }}')" wire:loading.attr="disabled" wire:target="toggle" @disabled($full) aria-pressed="{{ $on ? 'true' : 'false' }}" style="all:unset;display:block;cursor:pointer;width:100%">
                            <span class="ns-skin-card__head">
                                <span><b>{{ $s['label'] }}</b><small>{{ $s['blurb'] }}</small></span>
                                <span class="ns-check"><x-nx.icon name="check" /></span>
                            </span>
                            <span class="ns-mini" data-nx-preview="{{ $key }}" aria-hidden="true">
                                <span class="ns-grid">
                                    <span class="ns-card ns-card--primary"><i class="ns-deco"></i><span class="ns-tile"><x-nx.icon name="shield" /></span><h3>Verify</h3></span>
                                    <span class="ns-card ns-card--rent ns-ring"><i class="ns-deco"></i><span class="ns-tile"><x-nx.icon name="hash" /></span><h3>Rent</h3></span>
                                </span>
                            </span>
                        </button>
                        @if ($on)
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:8px">
                                @if ($picked[0] === $key)
                                    <span class="ns-pill ns-pill--best">Default</span>
                                @else
                                    <button type="button" wire:click="makeDefault('{{ $key }}')" wire:loading.attr="disabled" wire:target="makeDefault" class="ns-pill">Make default</button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>

        <div class="ns-adm__tier" style="position:sticky;bottom:12px">
            @if ($confirming)
                <x-nx.note icon="info" variant="warn" role="alertdialog">
                    Saving shows your members {{ count($picked) }} {{ \Illuminate\Support\Str::plural('skin', count($picked)) }} and hides the other {{ $hiddenCount }}. Anyone using a hidden skin switches to your default ({{ config('appearance.skins.'.($picked[0] ?? 'surface').'.label') }}); their choice is remembered if you add the skin back.
                </x-nx.note>
                <div style="display:flex;gap:10px;margin-top:10px">
                    <button type="button" class="ns-btn" wire:click="cancelReview" wire:loading.attr="disabled" wire:target="save">Cancel</button>
                    <button type="button" class="ns-cta" wire:click="save" wire:loading.attr="disabled" wire:target="save" style="flex:1">
                        <span wire:loading.remove wire:target="save">Confirm and save</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            @else
                <button type="button" class="ns-cta" wire:click="review" wire:loading.attr="disabled" wire:target="review" @disabled(count($picked) < 1)>
                    <span wire:loading.remove wire:target="review">Save my skins</span>
                    <span wire:loading wire:target="review">Checking…</span>
                </button>
            @endif
        </div>
    @endif
</x-nx.page>
