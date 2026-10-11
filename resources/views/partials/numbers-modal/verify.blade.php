@if ($order)
    {{-- Success: number reserved, the code is polled in the background. --}}
    <div class="ns-done">
        <span class="ns-tile ns-done__tile"><x-nx.icon name="check" /></span>
        <b>{{ __('numbers.verify.reserved_title') }}</b>
        <p>{{ __('numbers.verify.reserved_body') }}</p>
        <div class="ns-result ns-ring">
            <small>{{ __('numbers.your_number') }}</small>
            <b class="ns-result__num">{{ $order->phone_number }}</b>
            @if ($order->otp_code)
                <small>{{ __('numbers.code') }}</small>
                <b class="ns-result__code">{{ $order->otp_code }}</b>
            @else
                <span class="ns-result__wait"><x-ui.spinner class="h-4 w-4" /> {{ __('numbers.waiting_for_code') }}</span>
            @endif
        </div>
        <div class="ns-done__actions">
            <button type="button" class="ns-btn" wire:click="reset_" wire:loading.attr="disabled" wire:target="reset_">{{ __('numbers.verify.buy_another') }}</button>
            <a href="{{ route('dashboard') }}" wire:navigate class="ns-btn ns-btn--solid">{{ __('numbers.view_on_dashboard') }}</a>
        </div>
    </div>
@else
    {{-- Manual / Smart Buy --}}
    <div class="ns-seg" role="group">
        @foreach (['manual' => __('numbers.verify.manual_buy'), 'smart' => __('numbers.verify.smart_buy')] as $k => $label)
            <button type="button" wire:click="$set('buyMode', '{{ $k }}')" class="{{ $buyMode === $k ? 'is-on' : '' }}" aria-pressed="{{ $buyMode === $k ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($buyMode === 'smart')
        <p class="ns-sub" style="font-size:14px;margin-top:12px">{{ __('numbers.verify.smart_buy_hint') }}</p>
    @endif

    <div class="ns-stack">
        <x-nx.field wire:click="pickService" :label="__('numbers.service_label')" :value="$serviceName">
            <x-slot:leading><x-service-icon :slug="$service" class="h-9 w-9 shrink-0" /></x-slot:leading>
        </x-nx.field>
        @if ($buyMode === 'manual')
            <x-nx.field wire:click="pickCountry" :label="__('numbers.country_label')" :value="$countryName">
                <x-slot:leading><x-country-flag :country="$country" class="h-6 w-8 shrink-0" /></x-slot:leading>
            </x-nx.field>
        @endif
    </div>

    {{-- Networks: merged across the lane, RETAIL-priced (provider and cost never shown), best flagged. Prices / Statistics + CSV. --}}
    @if ($buyMode === 'manual' && count($operators))
        <div x-data="{ tab: @entangle('opTab').live }">
            <div class="ns-nrow">
                <span class="ns-lbl">{{ __('numbers.verify.networks') }}</span>
                <span style="display:flex;gap:10px;align-items:center">
                    <span class="ns-seg ns-small" role="group">
                        <button type="button" @click="tab = 'prices'" :class="tab === 'prices' ? 'is-on' : ''">{{ __('numbers.verify.prices_tab') }}</button>
                        <button type="button" @click="tab = 'stats'" :class="tab === 'stats' ? 'is-on' : ''">{{ __('numbers.verify.stats_tab') }}</button>
                    </span>
                    <button type="button" class="ns-icon-btn" wire:click="exportOperatorsCsv" wire:loading.attr="disabled" wire:target="exportOperatorsCsv" title="{{ __('numbers.verify.export_csv') }}" aria-label="{{ __('numbers.verify.export_csv') }}"><x-nx.icon name="download" /></button>
                </span>
            </div>

            <x-nx.row auto icon="zap" :selected="$operator === ''" wire:click="pickOperator('')">
                {{ __('numbers.verify.auto_best_network') }}
                <x-slot:pills><x-nx.pill variant="rec" style="margin-left:auto">{{ __('numbers.verify.recommended') }}</x-nx.pill></x-slot:pills>
            </x-nx.row>

            @foreach ($operators as $op)
                <x-nx.row :disabled="! $op['in_stock']" :selected="$operator === $op['operator'] && $operator !== ''" wire:click="pickOperator('{{ $op['operator'] }}')" wire:key="op-{{ $op['operator'] }}">
                    {{ $op['label'] }}
                    <x-slot:pills>
                        @if ($op['best'])<x-nx.pill variant="best">{{ __('numbers.verify.best') }}</x-nx.pill>@endif
                        @unless ($op['in_stock'])<x-nx.pill>{{ __('numbers.verify.out_of_stock') }}</x-nx.pill>@endunless
                    </x-slot:pills>
                    <x-slot:trailing>
                        <span x-show="tab === 'prices'">${{ number_format($op['retail'], 2) }}</span>
                        <span x-show="tab === 'stats'" x-cloak class="ns-small" style="display:inline;font-weight:400">{{ __('numbers.verify.available_short', ['count' => $op['available']]) }}@if ($op['success'] !== null) · {{ $op['success'] }}%@endif</span>
                    </x-slot:trailing>
                </x-nx.row>
            @endforeach
        </div>
    @endif

    @if ($error)
        <x-nx.note icon="info" variant="warn" role="alert">{{ $error }}</x-nx.note>
    @endif

    {{-- NaaraCredits redemption (loyalty): margin-capped server-side. --}}
    @if ($modalPrice !== null && $creditsEnabled && $creditBalance > 0 && $creditQuote['usd'] > 0)
        <label class="ns-note ns-note--dash" style="cursor:pointer">
            <input type="checkbox" wire:model.live="useCredits" class="ns-check-input">
            <span>
                <b style="display:flex;align-items:center;gap:6px;color:rgb(var(--nx-text));font-weight:600"><x-naara-coin class="h-4 w-4" /> {{ __('numbers.use_naaracredits') }}</b>
                {{ __('numbers.credits_balance', ['balance' => number_format($creditBalance, 0), 'credits' => number_format($creditQuote['credits'], 0), 'amount' => '$'.number_format($creditQuote['usd'], 2)]) }}
            </span>
        </label>
    @endif
@endif
