@php
    $__cur = \App\Support\LocaleCurrency::resolve(auth()->user());
    $__fx = app(\App\Services\Pricing\CurrencyService::class);
    $__countries = array_values(array_filter((array) $plan->countries));
    // What the member will actually be charged, and its local-currency line (live FX; the charge itself is always USD).
    $__due = $couponPrice ?? (float) $plan->final_retail_usd;
    $__total = $couponPrice !== null ? '$'.number_format($couponPrice, 2) : $plan->display_price['usd'];
    $__local = $__cur !== 'USD'
        ? '≈ '.$__fx->format($__due, $__cur)
        : ($couponPrice === null ? $plan->display_price['ngn'] : null);
@endphp
<div class="mx-auto max-w-lg">
<x-nx.page>
    <a href="{{ route('catalogue') }}" wire:navigate class="ns-back"><x-nx.icon name="chevron-left" /> {{ __('checkout.back_to_catalogue') }}</a>

    <div style="position:relative">
        {{-- Scoped action loader (audit §7): pulsing logo over the page while the purchase is in flight; dismisses when it resolves. --}}
        <x-brand-loader target="purchase" :overlay="true" :label="__('checkout.processing_order')" />

        <h1 class="ns-h1" style="margin-top:6px">{{ __('checkout.title') }}</h1>
        <p class="ns-sub">{{ __('checkout.subtitle') }}</p>

        {{-- Order card: the plan, its real synced facts (data, validity, calls) and where it works. --}}
        <div class="ns-card ns-card--rent ns-ring ns-full" style="margin-top:16px;padding:16px">
            <span class="ns-card__row" style="justify-content:space-between;align-items:flex-start">
                <span class="ns-card__text"><h3 style="font-size:19px">{{ $plan->name }}</h3></span>
                <x-nx.pill variant="best">{{ $plan->type ?? __('checkout.data_type') }}</x-nx.pill>
            </span>
            <span class="ns-chips">
                <span><x-nx.icon name="bars" />{{ $plan->data_mb ? number_format($plan->data_mb / 1024, 1).' GB' : __('checkout.unlimited_data') }}</span>
                <span><x-nx.icon name="cal" />{{ $plan->validity_days ? __('checkout.days', ['days' => $plan->validity_days]) : __('checkout.flexible_validity') }}</span>
                @if ($plan->has_voice)<span><x-nx.icon name="phone" />{{ __('checkout.calls_and_data') }}</span>@endif
            </span>
            @if (! empty($__countries))
                <span class="ns-chips" style="margin-top:0;border-top:0;padding-top:6px;align-items:center">
                    <span>{{ __('checkout.works_in') }}</span>
                    @foreach (array_slice($__countries, 0, 6) as $__iso)<x-country-flag :country="$__iso" class="h-4 w-6" />@endforeach
                    @if (count($__countries) > 6)<span>{{ __('checkout.more_countries', ['count' => count($__countries) - 6]) }}</span>
                    @elseif (count($__countries) === 1)<span>{{ \App\Support\CountryNames::name($__countries[0]) }}</span>@endif
                </span>
            @endif
        </div>

        {{-- Fair-usage disclosure (Prompt 10): only an unlimited plan carries one; the accessor returns null for a data-capped plan. --}}
        @if ($plan->display_fair_usage_note)
            <x-nx.note icon="info" variant="warn">{{ $plan->display_fair_usage_note }}</x-nx.note>
        @endif

        @unless ($done)
            {{-- Coupon code (Module 31): the discount is margin-guarded server-side. --}}
            <div class="ns-lbl" style="margin-top:20px">{{ __('checkout.coupon_placeholder') }}</div>
            @if ($couponPrice !== null)
                <div class="ns-note ns-note--dash" style="align-items:center;justify-content:space-between">
                    <span style="display:flex;gap:8px;align-items:center;color:rgb(var(--nx-ok))"><x-nx.icon name="check" /> {{ __('checkout.coupon_applied', ['code' => $coupon]) }}</span>
                    <button type="button" class="ns-btn" wire:click="removeCoupon" wire:loading.attr="disabled" wire:target="removeCoupon">{{ __('checkout.remove') }}</button>
                </div>
            @else
                <div style="display:flex;gap:8px;align-items:center">
                    <label class="ns-search" style="flex:1;min-width:0">
                        <x-nx.icon name="gift" />
                        <input wire:model="coupon" wire:keydown.enter="applyCoupon" type="text" placeholder="{{ __('checkout.coupon_placeholder') }}" autocomplete="off" style="text-transform:uppercase;letter-spacing:.06em" aria-label="{{ __('checkout.coupon_placeholder') }}">
                    </label>
                    <button type="button" class="ns-btn" style="margin-top:8px;height:54px" wire:click="applyCoupon" wire:loading.attr="disabled" wire:target="applyCoupon">
                        <span wire:loading.remove wire:target="applyCoupon">{{ __('checkout.apply') }}</span>
                        <span wire:loading wire:target="applyCoupon" class="ns-cta__label"><x-ui.spinner class="h-3.5 w-3.5" /> {{ __('checkout.checking') }}</span>
                    </button>
                </div>
                @if ($couponError)<x-nx.note icon="info" variant="warn" role="alert">{{ $couponError }}</x-nx.note>@endif
            @endif

            {{-- NaaraCredits redemption (loyalty): margin-capped server-side. --}}
            @if ($creditsEnabled && $creditBalance > 0 && $creditQuote['usd'] > 0)
                <label class="ns-note ns-note--dash" style="cursor:pointer">
                    <input type="checkbox" wire:model.live="useCredits" class="ns-check-input">
                    <span>
                        <b style="display:flex;align-items:center;gap:6px;color:rgb(var(--nx-text));font-weight:600"><x-naara-coin class="h-4 w-4" /> {{ __('checkout.use_naaracredits') }}</b>
                        {{ __('checkout.credits_balance', ['balance' => number_format($creditBalance, 0), 'credits' => number_format($creditQuote['credits'], 0), 'amount' => '$'.number_format($creditQuote['usd'], 2)]) }}
                    </span>
                </label>
                @if ($useCredits)
                    <div class="ns-note" style="justify-content:space-between;align-items:center">
                        <span>{{ __('checkout.charged_after_credits') }}</span>
                        <b style="color:rgb(var(--nx-text));font-variant-numeric:tabular-nums">${{ number_format($__due - $creditQuote['usd'], 2) }}</b>
                    </div>
                @endif
            @endif
        @endunless

        @if ($error)
            <x-nx.note icon="info" variant="warn" role="alert">
                {{ $error }}
                @if ($needsTopUp)<a href="{{ route('wallet') }}" wire:navigate class="ns-btn ns-btn--solid" style="margin-top:10px;display:inline-flex">{{ __('checkout.top_up_wallet') }}</a>@endif
            </x-nx.note>
        @endif

        @if ($done)
            <x-nx.note icon="check">{{ $message }}</x-nx.note>
            <a href="{{ route('dashboard') }}" wire:navigate class="ns-cta" style="margin-top:16px">{{ __('checkout.go_to_connectivity') }} <x-nx.icon name="chevron-right" /></a>
        @else
            {{-- Device-compatibility check: runs BEFORE purchase (Section 32). --}}
            <x-nx.step icon="phone" :title="__('checkout.compat_heading')" :hint="__('checkout.compat_subheading')" />
            <div style="display:flex;gap:8px;align-items:center">
                <label class="ns-search" style="flex:1;min-width:0;margin-top:0">
                    <x-nx.icon name="search" />
                    <input wire:model="device" type="text" placeholder="{{ __('checkout.device_placeholder') }}" aria-label="{{ __('checkout.compat_heading') }}">
                </label>
                <button type="button" class="ns-btn" style="height:54px" wire:click="checkDevice" wire:loading.attr="disabled" wire:target="checkDevice">{{ __('checkout.check') }}</button>
            </div>

            @if ($deviceResult === true)
                <x-nx.note icon="check">{{ __('checkout.compat_yes') }}</x-nx.note>
            @elseif ($deviceResult === false)
                {{-- Warning, not a hard block (Prompt 10 §3): some buyers purchase for a second device or gift the eSIM, so the risk is
                     surfaced and an explicit acknowledgment is required rather than preventing checkout. --}}
                <x-nx.note icon="info" variant="warn">{{ __('checkout.compat_no') }}</x-nx.note>
            @elseif ($deviceResult === null && $device !== '')
                <x-nx.note icon="info" variant="warn">{{ __('checkout.compat_unsure', ['howToCheck' => \App\Support\Niche\DeviceCompat::howToCheck()]) }}</x-nx.note>
            @endif

            <label class="ns-chk" style="cursor:pointer">
                <input type="checkbox" wire:model.live="deviceConfirmed" class="ns-check-input">
                <div><small style="font-size:14px;color:rgb(var(--nx-text-2))">{{ $deviceResult === false ? __('checkout.confirm_incompatible') : __('checkout.confirm_compatible') }}</small></div>
            </label>

            {{-- Shared plan (Prompt 11 §3): only shown with at least one ACCEPTED shared-plan membership; defaults to the member's own wallet. --}}
            @if ($sharedPlans->isNotEmpty())
                <div class="ns-lbl" style="margin-top:20px">{{ __('checkout.pay_from_heading') }}</div>
                <select wire:model.live="payFromGroupMemberId" class="ns-select" aria-label="{{ __('checkout.pay_from_heading') }}">
                    <option value="">{{ __('checkout.pay_from_own_wallet') }}</option>
                    @foreach ($sharedPlans as $plan_member)
                        <option value="{{ $plan_member->id }}">{{ __('checkout.pay_from_shared_plan', ['name' => $plan_member->walletGroup->owner->name ?? 'A NaaraSim user']) }}</option>
                    @endforeach
                </select>
            @endif

            {{-- Taxes & fees (Prompt 10): no separate tax or fee is charged on top of the retail price shown, so the customer is never
                 left wondering whether something will be added before Pay. Gold CTA: it commits wallet funds. --}}
            <x-nx.price-bar :fees-label="__('checkout.taxes_and_fees')" fees="$0.00" :pay-label="__('checkout.you_pay')" :total="$__total" :local="$__local" style="margin:20px -16px -28px">
                @if ($couponPrice !== null)<x-nx.note icon="check">{{ __('checkout.you_save', ['amount' => '$'.number_format($couponSaved, 2)]) }}</x-nx.note>@endif
                <x-nx.cta variant="gold" wire:click="purchase" loading="purchase" :disabled="! $deviceConfirmed">
                    <span wire:loading.remove wire:target="purchase" class="ns-cta__label"><x-nx.icon name="shield" /> {{ __('checkout.pay_with_wallet') }}</span>
                    <span wire:loading wire:target="purchase" class="ns-cta__label"><x-ui.spinner class="h-5 w-5" /> {{ __('checkout.processing') }}</span>
                </x-nx.cta>
                <x-nx.note icon="info">{!! __('checkout.wallet_disclaimer', ['refundPolicy' => '<a href="'.route('refund-policy').'" style="text-decoration:underline">'.__('checkout.refund_policy').'</a>']) !!}</x-nx.note>
            </x-nx.price-bar>
        @endif
    </div>
</x-nx.page>
</div>
