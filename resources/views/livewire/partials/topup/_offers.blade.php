{{-- "Pay with credit" offers: real data bundles; the saving is the margin-safe ceiling, never more than the quote honours. --}}
@if (! empty($offerCountries))
<section class="ns-tp__offers" aria-label="Offers with credit" data-tour="topup-offers"
         x-data="{ i: 0, n: {{ count($offers) }}, sync() { const t = this.$refs.t; const c = t.children[0]; if (! c) return; this.i = Math.min(this.n - 1, Math.round(t.scrollLeft / (c.offsetWidth + 12))) } }">
    <div class="ns-tp__offhead"><span class="ns-tp__kicker"><x-nx.icon name="zap" /> Offers for you</span><small>Pay with credit and keep a little more of your money</small></div>
    <div class="ns-tp__ocs" role="tablist" aria-label="Offers by country">
        @foreach (array_slice($offerCountries, 0, 12) as $iso)
            <button type="button" role="tab" aria-selected="{{ $iso === $oc ? 'true' : 'false' }}" class="ns-tp__oc {{ $iso === $oc ? 'is-on' : '' }}" wire:click="setOfferCountry('{{ $iso }}')" wire:key="oc-{{ $iso }}"><x-country-flag :country="$iso" class="h-3.5 w-5" /> {{ \App\Support\CountryNames::name($iso) }}</button>
        @endforeach
        @if (count($offerCountries) > 12)
            <select class="ns-tp__ocmore ns-input" aria-label="More countries" wire:change="setOfferCountry($event.target.value)">
                <option value="">More countries…</option>
                @foreach (array_slice($offerCountries, 12) as $iso)<option value="{{ $iso }}" @selected($iso === $oc)>{{ \App\Support\CountryNames::name($iso) }}</option>@endforeach
            </select>
        @endif
    </div>
    @if (empty($offers))
        <p class="ns-small ns-tp__nooff">No offers in {{ \App\Support\CountryNames::name($oc) }} right now. Pick another country, or use the top-up form below for any bundle.</p>
    @endif
    <div class="ns-tp__offtrack" x-ref="t" wire:key="offtrack-{{ $oc }}" @scroll.passive.debounce.80ms="sync()">
        @foreach ($offers as $o)
            <article class="ns-tp__offer" style="--oc: {{ $o['tint'] }}; --oe: {{ $o['end'] }}; --oi: {{ $o['ink'] }}" wire:key="offer-{{ $o['id'] }}">
                <span class="ns-tp__hot" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12.5 2s1 3.2-1.2 5.6C9.5 9.5 8 10.8 8 13.3A4.5 4.5 0 0 0 12.5 18c2.7 0 4.5-2 4.5-4.6 0-1.4-.6-2.5-1.3-3.5-.3 1-.9 1.6-1.6 1.8.5-3.6-.3-7.2-1.6-9.7z"/></svg><b>HOT</b></span>
                <div class="ns-tp__offbody">
                    <div class="ns-tp__offtop">
                        @if ($o['logo'])<span class="ns-tp__offlogo {{ $o['bg'] ? 'has-bg' : '' }}" @if ($o['bg']) style="--lb:{{ $o['bg'] }}" @endif><img src="{{ $o['logo'] }}" alt="" width="28" height="28" loading="lazy"></span>@endif
                        <strong>{{ $o['title'] }}</strong>@if ($o['validity'])<em>{{ $o['validity'] }}</em>@endif
                    </div>
                    <p>{{ $o['operator'] }} for <b>${{ number_format($o['price'], 2) }}</b>. Pay with credit and save up to <b>${{ number_format($o['save'], 2) }}</b> ({{ rtrim(rtrim(number_format($o['pct'], 1), '0'), '.') }}% off).</p>
                </div>
                <button type="button" class="ns-tp__claim" wire:click="openOffer({{ $o['id'] }})" wire:loading.attr="disabled" wire:target="openOffer({{ $o['id'] }})"><span wire:loading.remove wire:target="openOffer({{ $o['id'] }})">Claim</span><span wire:loading wire:target="openOffer({{ $o['id'] }})">…</span></button>
            </article>
        @endforeach
    </div>
    @if (count($offers) > 1)
        <div class="ns-tp__offdots" aria-hidden="true">@for ($k = 0; $k < count($offers); $k++)<i :class="i === {{ $k }} ? 'is-on' : ''"></i>@endfor</div>
    @endif
</section>
@endif

@if ($offerProduct)
@php
    $op = $offerProduct;
    $oq = $offerQuote;
    $tint = \App\Support\Topup\TopupBrandTint::for($op->operator_name);
    $mbv = (float) $op->data_mb;
    $size = $op->data_unlimited ? 'Unlimited data' : ($mbv >= 1024 ? rtrim(rtrim(number_format($mbv / 1024, 2), '0'), '.').' GB' : (int) $mbv.' MB');
    $spend = app(\App\Services\Wallet\WalletService::class)->spendableUsd(auth()->user());
@endphp
<div class="ns-tp__sheetwrap" x-data x-on:keydown.escape.window="$wire.closeOffer()" role="dialog" aria-modal="true" aria-label="Buy this offer">
    <div class="ns-tp__scrim" wire:click="closeOffer"></div>
    <div class="ns-tp__sheet" style="--oc: {{ $tint }}">
        <button type="button" class="ns-tp__x" wire:click="closeOffer" aria-label="Close"><x-nx.icon name="x" /></button>
        <div class="ns-tp__sheethead">
            <span class="ns-tp__hot" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12.5 2s1 3.2-1.2 5.6C9.5 9.5 8 10.8 8 13.3A4.5 4.5 0 0 0 12.5 18c2.7 0 4.5-2 4.5-4.6 0-1.4-.6-2.5-1.3-3.5-.3 1-.9 1.6-1.6 1.8.5-3.6-.3-7.2-1.6-9.7z"/></svg></span>
            <div><strong>{{ $size }}</strong>@if ($op->validity_text)<em>{{ $op->validity_text }}</em>@endif<small>{{ $op->operator_name }} · {{ \App\Support\CountryNames::name($op->country_iso) }}</small></div>
        </div>
        <label class="ns-tp__field"><span class="ns-small">Phone number to top up</span>
            <input type="tel" inputmode="tel" autocomplete="off" wire:model.live.debounce.500ms="offerPhone" class="ns-input" placeholder="+ country code and number" aria-label="Phone number" @if (! $oq) autofocus @endif>
        </label>
        @if ($offerError)<p class="ns-tp__err" role="alert"><x-nx.icon name="alert-triangle" /> {{ $offerError }}</p>@endif
        @if ($oq)
            <dl class="ns-tp__sum">
                <div><dt>Sending to</dt><dd>{{ $oq->phone_e164 }}</dd></div>
                @if ((float) $oq->credit_usd > 0)
                    <div><dt>Price</dt><dd>${{ number_format((float) $oq->list_price_usd, 2) }}</dd></div>
                    <div><dt>Credit saving</dt><dd>−${{ number_format((float) $oq->credit_usd, 2) }}</dd></div>
                @endif
                <div><dt>You pay</dt><dd><b>${{ number_format((float) $oq->price_usd, 2) }}</b></dd></div>
            </dl>
            @if ((float) $oq->credit_usd <= 0)<p class="ns-small">Pay with credit to save a little more on this bundle.</p>@endif
            <p class="ns-small">Wallet balance ${{ number_format($spend, 2) }}@if ($spend < (float) $oq->price_usd) · <a href="{{ route('wallet') }}" wire:navigate>Add funds</a>@endif</p>
            <button type="button" class="ns-cta ns-cta--pill ns-tp__pay" wire:click="payOffer" wire:loading.attr="disabled" wire:target="payOffer" @disabled($spend < (float) $oq->price_usd)>
                <span wire:loading.remove wire:target="payOffer">Pay ${{ number_format((float) $oq->price_usd, 2) }} from wallet</span><span wire:loading wire:target="payOffer">Placing your top-up…</span>
            </button>
        @else
            <p class="ns-small">Enter the number and the price is locked for you.</p>
        @endif
    </div>
</div>
@endif
