@php($isAny = $service === \App\Services\SMS\NumberRequest::SERVICE_ANY)
@if ($order)
    <div class="ns-done">
        <span class="ns-tile ns-done__tile"><x-nx.icon name="check" /></span>
        <b>{{ __('numbers.rent.reserved_title') }}</b>
        <p>{{ __('numbers.rent.reserved_body') }}</p>
        <div class="ns-result ns-ring">
            <small>{{ __('numbers.your_number') }}</small>
            <b class="ns-result__num">{{ $order->phone_number }}</b>
        </div>
        <div class="ns-done__actions">
            <button type="button" class="ns-btn" wire:click="reset_" wire:loading.attr="disabled" wire:target="reset_">{{ __('numbers.rent.rent_another') }}</button>
            <a href="{{ route('dashboard') }}" wire:navigate class="ns-btn ns-btn--solid">{{ __('numbers.view_on_dashboard') }}</a>
        </div>
    </div>
@else
    {{-- Single service vs Any service ("Any" only when a full-rent-capable provider is configured, never a dead option). --}}
    <div class="ns-seg" role="group">
        <button type="button" wire:click="$set('service', 'whatsapp')" class="{{ ! $isAny ? 'is-on' : '' }}" aria-pressed="{{ ! $isAny ? 'true' : 'false' }}">{{ __('numbers.rent.single_service') }}</button>
        <button type="button" @if ($fullRentAvailable) wire:click="$set('service', '{{ \App\Services\SMS\NumberRequest::SERVICE_ANY }}')" @else disabled @endif
                class="{{ $isAny ? 'is-on' : '' }}" aria-pressed="{{ $isAny ? 'true' : 'false' }}">{{ __('numbers.rent.any_service') }} @unless ($fullRentAvailable){{ __('numbers.rent.soon') }}@endunless</button>
    </div>

    <x-nx.step icon="pin" :title="__('numbers.rent.step_country')" :hint="__('numbers.rent.step_country_hint')" />
    <x-nx.field wire:click="pickCountry" :value="$countryName" value-first>
        <x-slot:leading><x-country-flag :country="$country" class="h-6 w-8 shrink-0" /></x-slot:leading>
    </x-nx.field>

    <x-nx.step icon="chat" :title="__('numbers.rent.step_service')" :hint="__('numbers.rent.step_service_hint')" />
    @unless ($isAny)
        <x-nx.field wire:click="pickService" :value="$serviceName" value-first>
            <x-slot:leading><x-service-icon :slug="$service" class="h-9 w-9 shrink-0" /></x-slot:leading>
        </x-nx.field>
    @else
        <x-nx.note icon="check" variant="dash">{!! __('numbers.rent.any_service_note', ['bold' => '<b>'.e(__('numbers.rent.any_service_bold')).'</b>']) !!}</x-nx.note>
    @endunless

    {{-- Duration: only US numbers (Getatext) offer real longer rentals; every other country is a fixed short-term rental, stated honestly. --}}
    @if ($isUsRental)
        <x-nx.step icon="cal" :title="__('numbers.rent.rental_length')" :hint="__('numbers.rent.step_length_hint')" />
        <div class="ns-len">
            @foreach ($rentalDurations as $code => $label)
                <button type="button" wire:click="$set('rentalDuration', '{{ $code }}')" class="{{ $rentalDuration === $code ? 'is-on' : '' }}" aria-pressed="{{ $rentalDuration === $code ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        <label class="ns-chk" style="cursor:pointer">
            <input type="checkbox" wire:model.live="autoRenew" class="ns-check-input">
            <div><b>{{ __('numbers.rent.auto_renew') }}</b></div>
        </label>
    @else
        <x-nx.note icon="info">{{ __('numbers.rent.short_term_note') }}</x-nx.note>
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

    <x-nx.note variant="link" icon="shield" tag="div" style="margin-top:20px"><b>{{ __('numbers.rent.why_title') }}</b>{{ __('numbers.rent.why_text') }}</x-nx.note>
@endif
