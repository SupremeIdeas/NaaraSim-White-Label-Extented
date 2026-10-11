{{-- Account -> Appearance (Prompt 20 §18). Markup follows the V9 wireframe (`t-appearance`); every class is an ns-* hook
     so any skin can restyle it. Wrapped in .ns-app so the tokens read against the canvas, as in the wireframe. --}}
<div class="mx-auto max-w-xl px-3 py-4 sm:px-4 sm:py-6"
     x-data="{ mode: document.documentElement.getAttribute('data-nx-mode') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light') }"
     x-on:nx-appearance.window="mode = $event.detail.mode || mode">
<div class="ns-app" @if (! empty($r['country'])) data-cc="{{ strtoupper($r['country']) }}" @endif><div class="ns-app__body">
    <a href="{{ $back }}" wire:navigate class="ns-back"><x-nx.icon name="chevron-left" />{{ __('appearance.back') }}</a>
    <h1 class="ns-h1" style="margin-top:6px">{{ __('appearance.title') }}</h1>
    <p class="ns-sub">{{ __('appearance.subtitle') }}</p>
    <p class="ns-sub" style="font-size:13px" role="status">{{ $r['locked'] ? __('appearance.locked') : __('appearance.only_you') }}</p>

    {{-- live preview: the real components, restyled by whatever is applied right now --}}
    <div class="ns-preview">
        <div class="ns-grid">
            <x-nx.card primary icon="shield" :title="__('appearance.preview.verify')" :text="__('appearance.preview.verify_text')" />
            <x-nx.card tone="rent" class="ns-ring" icon="hash" :title="__('appearance.preview.rent')" :text="__('appearance.preview.rent_text')" />
        </div>
        <x-nx.cta type="button">{{ __('appearance.preview.cta') }}</x-nx.cta>
    </div>

    <fieldset class="ns-section" @disabled($r['locked']) style="border:0;padding:0">
        <span class="ns-lbl" id="ap-mode">{{ __('appearance.mode_label') }}</span>
        <div class="ns-seg" style="margin-top:8px" role="group" aria-labelledby="ap-mode">
            @foreach ($modes as $value => $text)
                <button type="button" x-on:click="mode = '{{ $value }}'; $wire.setMode('{{ $value }}')" wire:loading.attr="disabled" wire:target="setMode"
                        :class="mode === '{{ $value }}' ? 'is-on' : ''" :aria-pressed="mode === '{{ $value }}'">{{ $text }}</button>
            @endforeach
        </div>
    </fieldset>

    <div class="ns-section">
        <span class="ns-lbl">{{ __('appearance.skin_label', ['count' => $groups->flatten(1)->count()]) }}</span>
        @foreach ($groups as $group => $items)
            <span class="ns-group">{{ $group }}</span>
            @foreach ($items as $key => $s)
                <button type="button" wire:click="setSkin('{{ $key }}')" wire:loading.attr="disabled" wire:target="setSkin" @disabled($r['locked'])
                        class="ns-skin-card {{ $r['skin'] === $key ? 'is-selected' : '' }}" aria-pressed="{{ $r['skin'] === $key ? 'true' : 'false' }}" wire:key="skin-{{ $key }}">
                    <span class="ns-skin-card__head">
                        <span><b>{{ $s['label'] }}</b><small>{{ $s['blurb'] }}</small></span>
                        @if ($key === $defaultSkin)<span class="ns-pill ns-pill--best">{{ __('appearance.default') }}</span>@endif
                        <span class="ns-check"><x-nx.icon name="check" /></span>
                    </span>
                    {{-- scoped mini preview: its own data-nx-preview, never a screenshot --}}
                    <span class="ns-mini" data-nx-preview="{{ $key }}" aria-hidden="true">
                        <span class="ns-grid">
                            <span class="ns-card ns-card--primary"><i class="ns-deco"></i><span class="ns-tile"><x-nx.icon name="shield" /></span><h3>{{ __('appearance.preview.verify') }}</h3></span>
                            <span class="ns-card ns-card--rent ns-ring"><i class="ns-deco"></i><span class="ns-tile"><x-nx.icon name="hash" /></span><h3>{{ __('appearance.preview.rent') }}</h3></span>
                        </span>
                    </span>
                </button>
            @endforeach
        @endforeach
    </div>

    <div class="ns-section">
        <span class="ns-lbl">{{ __('appearance.accent_label') }}</span>
        <div class="ns-swatches">
            @foreach ($accents as $key => $a)
                <button type="button" wire:click="setAccent('{{ $key }}')" wire:loading.attr="disabled" wire:target="setAccent" @disabled($r['locked'])
                        class="ns-swatch {{ $r['accent'] === $key ? 'is-selected' : '' }}" data-nx-accent="{{ $key }}" aria-pressed="{{ $r['accent'] === $key ? 'true' : 'false' }}" wire:key="acc-{{ $key }}"><i></i><span>{{ $a['label'] }}</span></button>
            @endforeach
            @if ($allowCustom)
                <button type="button" wire:click="setAccent('custom')" wire:loading.attr="disabled" wire:target="setAccent" @disabled($r['locked'])
                        class="ns-swatch {{ $r['accent'] === 'custom' ? 'is-selected' : '' }}" aria-pressed="{{ $r['accent'] === 'custom' ? 'true' : 'false' }}" wire:key="acc-custom">
                    <i @if ($r['accent_hex']) style="background:{{ $r['accent_hex'] }}" @else class="ns-swatch__wheel" @endif></i><span>{{ __('appearance.custom') }}</span></button>
            @endif
        </div>
        @if ($allowCustom)
            <div class="ns-custom">
                <div class="ns-row-inline">
                    <input type="color" value="{{ $hex }}" aria-label="{{ __('appearance.pick_colour') }}" @disabled($r['locked'])
                           x-on:change="$wire.setHex($event.target.value)">
                    <input type="text" maxlength="7" value="{{ $hex }}" aria-label="{{ __('appearance.hex_label') }}" @disabled($r['locked'])
                           x-on:change="$wire.setHex($event.target.value)">
                    <button type="button" class="ns-btn" wire:click="resetAccent" wire:loading.attr="disabled" wire:target="resetAccent" @disabled($r['locked'])>{{ __('appearance.reset_accent') }}</button>
                </div>
                <small>
                    @if ($derived)
                        {{ ($derived['adjusted'] || $derived['light']['adjusted']) ? __('appearance.custom_adjusted') : __('appearance.custom_ok') }}
                    @else
                        {{ __('appearance.custom_hint', ['default' => $accents[$defaultAccent]['label'] ?? '']) }}
                    @endif
                </small>
            </div>
        @endif
        <p class="ns-sub" style="font-size:13px">{{ __('appearance.accent_note') }}</p>
    </div>

    <div class="ns-section">
        <span class="ns-lbl">{{ __('appearance.personalise') }}</span>
        @foreach ($dials as $dial => $d)
            <div class="ns-dial">
                <span class="ns-lbl">{{ __('appearance.dial.'.$dial) }}</span>
                <div class="ns-seg ns-seg--wrap" role="group" aria-label="{{ __('appearance.dial.'.$dial) }}">
                    @foreach ($d['options'] as $value => $label)
                        <button type="button" wire:click="setDial('{{ $dial }}', '{{ $value }}')" wire:loading.attr="disabled" wire:target="setDial" @disabled($r['locked'])
                                class="{{ $r['dials'][$dial] === $value ? 'is-on' : '' }}" aria-pressed="{{ $r['dials'][$dial] === $value ? 'true' : 'false' }}">{{ __('appearance.dial_option.'.$dial.'.'.$value) }}</button>
                    @endforeach
                </div>
            </div>
        @endforeach
        <button type="button" class="ns-btn" style="margin-top:14px" wire:click="resetDials" wire:loading.attr="disabled" wire:target="resetDials" @disabled($r['locked'])>{{ __('appearance.reset_all') }}</button>
    </div>
</div></div>
</div>
