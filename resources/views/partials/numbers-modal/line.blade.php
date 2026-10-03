<div x-data="{ tab: 'mobile' }">
    {{-- Mobile / Toll-free switcher --}}
    <div class="ns-seg" role="group">
        <button type="button" @click="tab = 'mobile'" :class="tab === 'mobile' ? 'is-on' : ''" :aria-pressed="tab === 'mobile'"><x-nx.icon name="phone" />{{ __('numbers.line.mobile_numbers') }}</button>
        <button type="button" @click="tab = 'toll'" :class="tab === 'toll' ? 'is-on' : ''" :aria-pressed="tab === 'toll'"><x-nx.icon name="phone" />{{ __('numbers.line.toll_free') }}</button>
    </div>

    @unless ($permanentAvailable)
        <div class="ns-empty" style="margin-top:16px">
            <span class="ns-tile" style="margin:0 auto 10px"><x-nx.icon name="phone" /></span>
            <b style="display:block;color:rgb(var(--nx-text));font-size:16px">{{ __('numbers.line.coming_soon_title') }}</b>
            {{ __('numbers.line.coming_soon_body') }}
        </div>
    @else
        <p class="ns-sub" style="font-size:17px;margin:16px 0;color:rgb(var(--nx-text))">{!! __('numbers.line.intro', ['bold' => '<b>'.e(__('numbers.line.intro_bold')).'</b>']) !!}</p>

        <x-nx.field wire:click="pickCountry" :label="__('numbers.country_label')" :value="$countryName">
            <x-slot:leading><x-country-flag :country="$country" class="h-6 w-8 shrink-0" /></x-slot:leading>
        </x-nx.field>

        {{-- Vanity search (PermanentNumberRouter::search: digits + position). --}}
        <div class="ns-lbl" style="margin-top:20px">{{ __('numbers.line.vanity_label') }}</div>
        <label class="ns-search">
            <x-nx.icon name="search" />
            <input type="text" wire:model="vanity" inputmode="numeric" placeholder="{{ __('numbers.line.vanity_placeholder') }}" aria-label="{{ __('numbers.line.vanity_label') }}">
        </label>

        @if ($lineDone)
            <div class="ns-result ns-ring" style="margin-top:16px;text-align:center">
                <span class="ns-tile ns-done__tile" style="margin:0 auto 6px"><x-nx.icon name="check" /></span>
                <b>{{ __('numbers.line.active_title') }}</b>
                <b class="ns-result__num">{{ $lineDone }}</b>
                <small>{{ $lineVoiceCapable ? __('numbers.line.active_hint') : __('numbers.line.active_hint_sms_only') }}</small>
            </div>
        @endif

        @if ($error)
            <x-nx.note icon="info" variant="warn" role="alert">{{ $error }}</x-nx.note>
        @endif

        {{-- Results: number + monthly price + Get this number (provider never shown). --}}
        @if (! empty($lineNumbers))
            <div class="ns-list" style="margin-top:16px">
                @foreach ($lineNumbers as $n)
                    <div wire:key="line-{{ $n['number'] }}" class="ns-list__row">
                        <span>
                            <b style="font-family:var(--nx-font-num);font-variant-numeric:tabular-nums;font-size:16px">{{ $n['number'] }}</b>
                            <small class="ns-small">{{ $n['locality'] ?: __('numbers.line.mobile_fallback') }} · ${{ number_format($n['monthly_retail'], 2) }}/mo</small>
                        </span>
                        <button type="button" class="ns-btn ns-btn--solid" style="margin-left:auto" wire:click="getLine('{{ $n['number'] }}')" wire:loading.attr="disabled" wire:target="getLine('{{ $n['number'] }}')">
                            <span wire:loading.remove wire:target="getLine('{{ $n['number'] }}')">{{ __('numbers.line.get_this_number') }}</span>
                            <span wire:loading wire:target="getLine('{{ $n['number'] }}')" class="ns-cta__label"><x-ui.spinner class="h-3.5 w-3.5" /> …</span>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <x-nx.note icon="info" variant="ring">{{ __('numbers.line.monthly_note') }}</x-nx.note>
    @endunless
</div>
