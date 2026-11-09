@php
    $user = auth()->user();
    $spendable = app(\App\Services\Wallet\WalletService::class)->spendableUsd($user);
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $amtFmt = fn ($v, $cur) => $cur.' '.number_format((float) $v, (float) $v == floor((float) $v) ? 0 : 2);
    $srcText = ['supplier' => 'confirmed live by the network', 'confirmed' => 'confirmed by an earlier top-up to this number', 'saved' => 'from your saved contact', 'prefix' => 'from the number\'s network code'];
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
@endphp
<div>
<x-nx.page class="ns-tp">
    @include('livewire.partials.topup._hero')
    @includeIf('livewire.partials.topup._offers')

    {{-- Saved beneficiaries --}}
    @if ($beneficiaries->isNotEmpty())
    <section aria-label="Saved contacts" data-tour="topup-bens">
        <div class="ns-tp__num" style="margin-bottom:8px">Your people <a href="{{ route('topup.beneficiaries') }}" wire:navigate class="ns-small" style="margin-left:auto;font-weight:600;color:rgb(var(--nx-teal))">Manage{{ $autoCount ? ' · '.$autoCount.' auto' : '' }}</a></div>
        <div class="ns-tp__bens">
            @foreach ($beneficiaries as $b)
                <button type="button" wire:key="b{{ $b->id }}" wire:click="useBeneficiary({{ $b->id }})" class="ns-tp__ben {{ $e164 === $b->phone_e164 ? 'is-on' : '' }}">
                    @include('livewire.partials.topup.logo', ['name' => $b->operator_name ?? $b->name, 'size' => 36, 'country' => $b->country_iso])
                    <span><b>{{ $b->name }}</b><span>{{ $b->phone_e164 }}</span></span>
                </button>
            @endforeach
        </div>
    </section>
    @endif

    {{-- 1. Number --}}
    <section id="step-number" class="ns-card ns-tp__step" data-tour="topup-number">
        <div class="ns-tp__num"><b>1</b> Whose phone?</div>
        <div class="ns-tp__row">
            <label class="ns-tp__field">
                <span class="ns-small">Country</span>
                <select wire:model.live="country" class="ns-input" aria-label="Country">
                    <option value="">Choose a country…</option>
                    @if ($popular)
                        <optgroup label="Popular">@foreach ($popular as $code)<option value="{{ $code }}">{{ $countries[$code] }}</option>@endforeach</optgroup>
                        <optgroup label="All countries A to Z">@foreach ($countries as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</optgroup>
                    @else
                        @foreach ($countries as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach
                    @endif
                </select>
            </label>
            <label class="ns-tp__field ns-tp__field--grow">
                <span class="ns-small">Phone number (with or without the + country code)</span>
                <span class="ns-tp__phonewrap" x-data="{ can: 'contacts' in navigator && 'ContactsManager' in window }">
                <input type="tel" inputmode="tel" autocomplete="off" wire:model.live.debounce.500ms="phone" class="ns-input" placeholder="{{ $country === 'NG' ? '0803 123 4567 or +234 803 123 4567' : '+ country code and number' }}" aria-label="Phone number">
                <button type="button" x-show="can" x-cloak class="ns-tp__pick" aria-label="Pick from my contacts" @click="(async () => { try { const c = await navigator.contacts.select(['tel'], { multiple: false }); if (c.length && c[0].tel.length) { $wire.set('phone', c[0].tel[0]) } } catch (e) {} })()"><x-nx.icon name="users" /></button>
                </span>
            </label>
        </div>
        @if ($phoneError)<p class="ns-tp__err" role="alert"><x-nx.icon name="alert-triangle" /> {{ $phoneError }}</p>@endif
        @if ($country !== '' && ! $sellable)<p class="ns-tp__err" role="status"><x-nx.icon name="info" /> Top-ups to {{ \App\Support\CountryNames::name($country) }} are not available yet.</p>@endif
        @if ($stale)<p class="ns-sub ns-small">Showing the last known offers while the operator updates.</p>@endif

        @if ($sellable && $e164 !== '')
            @if ($operator)
                <div class="ns-tp__found" wire:key="found-{{ $operator }}">
                    @include('livewire.partials.topup.logo', ['name' => $operator, 'size' => 52, 'country' => $country])
                    <div>
                        <b>{{ $operator }} · {{ $e164 }}</b>
                        <span class="ns-small">{{ \App\Support\CountryNames::name($country) }}@if ($detected && isset($srcText[$detectedSource])) · {{ $srcText[$detectedSource] }}@endif</span>
                        <div class="ns-tp__svc">
                            <x-nx.pill variant="best">Airtime{{ $airtimeCount ? ' · '.$airtimeCount : '' }}</x-nx.pill>
                            <x-nx.pill variant="best">Data{{ $dataCount ? ' · '.$dataCount.' bundles' : ' · none' }}</x-nx.pill>
                        </div>
                    </div>
                </div>
                @if ($detectedSource === 'prefix' || ! $detected)
                    <p class="ns-sub ns-small">Numbers can move between networks. If this is not the right network, pick the correct one:</p>
                @endif
            @else
                <p class="ns-tp__detect"><x-nx.icon name="info" /> We could not tell the network from this number. Pick it below.</p>
            @endif
            @if ($detectedSource === 'prefix' || ! $detected)
            <div class="ns-tp__ops" role="group" aria-label="Network">
                @foreach ($operators as $op)
                    <button type="button" wire:click="pickOperator(@js($op))" class="ns-tp__op {{ $operator && strcasecmp($operator, $op) === 0 ? 'is-on' : '' }}" aria-pressed="{{ $operator && strcasecmp($operator, $op) === 0 ? 'true' : 'false' }}">
                        @include('livewire.partials.topup.logo', ['name' => $op, 'size' => 40, 'country' => $country])<span>{{ $op }}</span>
                    </button>
                @endforeach
            </div>
            @endif
        @endif
        <div wire:loading wire:target="country,phone,pickOperator,useBeneficiary" class="ns-sub ns-small">Checking the number and getting live offers…</div>
    </section>

    {{-- 2. Services for that line --}}
    @if ($operator && $e164 !== '')
    <section class="ns-card ns-tp__step" data-tour="topup-products">
        <div class="ns-tp__num"><b>2</b> What would you like to send to this line?</div>
        <div class="ns-tp__tabs">
            <x-nx.segmented :options="['airtime' => 'Airtime'.($airtimeCount ? ' ('.$airtimeCount.')' : ''), 'data' => 'Data ('.$dataCount.')']" :current="$type" action="setType" label="Type" />
            @if ($type === 'data')<label class="ns-search ns-tp__search"><x-nx.icon name="search" /><input type="text" wire:model.live.debounce.300ms="search" placeholder="Search bundles" aria-label="Search bundles"></label>@endif
        </div>
        @if ($type === 'data' && $windowCounts['all'] > 0)
            <div class="ns-tp__win" role="tablist" aria-label="Bundle length">
                @foreach (['all' => 'All', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $wk => $wl)
                    <button type="button" role="tab" wire:click="setWindow('{{ $wk }}')" aria-selected="{{ $window === $wk ? 'true' : 'false' }}" @disabled($wk !== 'all' && $windowCounts[$wk] === 0) class="ns-tp__wtab {{ $window === $wk ? 'is-on' : '' }}">{{ $wl }} <small>{{ $windowCounts[$wk] }}</small></button>
                @endforeach
            </div>
        @endif
        <div class="ns-tp__grid">
            @forelse ($products as $p)
                @php $price = $prices[$p->id] ?? null; @endphp
                <button type="button" wire:key="p{{ $p->id }}" wire:click="pickProduct({{ $p->id }})" class="ns-tp__prod {{ $productId === $p->id ? 'is-on' : '' }}" aria-pressed="{{ $productId === $p->id ? 'true' : 'false' }}">
                    @if ($p->is_fixed)
                        <b class="ns-tp__get">{{ $p->type === 'data' ? $p->name : $amtFmt($p->fixed_amount, $p->currency) }}</b>
                        @if ($p->type === 'data' && $p->fixed_amount)<span class="ns-small ns-tp__sub">{{ $amtFmt($p->fixed_amount, $p->currency) }} value</span>@endif
                        @if ($p->validity_text)<span class="ns-small ns-tp__sub"><x-nx.icon name="clock" /> {{ preg_replace('/\b1 days\b/', '1 day', $p->validity_text) }}</span>@endif
                        <span class="ns-tp__price">{{ $price ? $money($price) : '' }}</span>
                    @else
                        <b class="ns-tp__get">Any amount</b>
                        <span class="ns-small ns-tp__sub">{{ $amtFmt($p->min_amount, $p->currency) }} to {{ $amtFmt($p->max_amount, $p->currency) }}</span>
                    @endif
                </button>
            @empty
                <p class="ns-sub">{{ $type === 'data' ? 'No data bundles for this network right now.' : 'No airtime options for this network right now.' }}@if ($type === 'airtime' && $hasData) Try Data bundles.@endif</p>
            @endforelse
        </div>
        @if ($total > 12 && $search === '')<button type="button" wire:click="toggleAll" class="ns-tp__more">{{ $showAll ? 'Show fewer' : 'Show all '.$total.' options' }}</button>@endif

        @if ($selected && ! $selected->is_fixed)
            <div class="ns-tp__range">
                <label class="ns-tp__field"><span class="ns-small">Amount the recipient gets ({{ $selected->currency }})</span>
                    <input type="number" inputmode="decimal" min="{{ $selected->min_amount }}" max="{{ $selected->max_amount }}" step="any" wire:model="amount" wire:keydown.enter="makeQuote" class="ns-input" placeholder="{{ (int) $selected->min_amount }} to {{ (int) $selected->max_amount }}" aria-label="Amount"></label>
                <button type="button" class="ns-cta ns-cta--pill" wire:click="makeQuote" wire:loading.attr="disabled" wire:target="makeQuote"><span wire:loading.remove wire:target="makeQuote">See price</span><span wire:loading wire:target="makeQuote">Pricing…</span></button>
            </div>
            @if ($tilePrices)
                <div class="ns-tp__tiles" role="group" aria-label="Quick amounts">
                    @foreach ($tilePrices as $v => $pr)
                        <button type="button" class="ns-tp__tile {{ (string) $amount === (string) $v ? 'is-on' : '' }}" wire:click="quickAmount('{{ $v }}')" wire:loading.attr="disabled" wire:target="quickAmount">
                            <b>{{ $selected->currency }} {{ number_format($v) }}</b>
                            @if ($pr)<span class="ns-small">{{ $money($pr) }}</span>@endif
                        </button>
                    @endforeach
                </div>
            @endif
        @endif
        @if ($error)<p class="ns-tp__err" role="alert"><x-nx.icon name="alert-triangle" /> {{ $error }}</p>@endif
    </section>
    @endif

    {{-- 3. Review, save, auto-reload, pay --}}
    @if ($quote)
    <section class="ns-card ns-tp__step ns-tp__review" data-tour="topup-pay" x-data="{ left: {{ max(0, (int) now()->diffInSeconds($quote->expires_at, false)) }} }" x-init="setInterval(() => { if (left > 0) left-- }, 1000)">
        <div class="ns-tp__num"><b>3</b> Review and pay</div>
        <dl class="ns-tp__sum">
            <div><dt>Sending to</dt><dd>{{ $quote->phone_e164 }}</dd></div>
            <div><dt>Network</dt><dd>{{ $quote->product->operator_name }}</dd></div>
            <div><dt>They get</dt><dd>{{ $quote->product->type === 'data' ? $quote->product->name : $amtFmt($quote->send_amount, $quote->send_currency) }}@if ($quote->product->type === 'data' && $quote->product->validity_text) <span class="ns-small">({{ $quote->product->validity_text }})</span>@endif</dd></div>
            @if ((float) $quote->credit_usd > 0)
                <div><dt>Price</dt><dd>{{ $money($quote->list_price_usd) }}</dd></div>
                <div><dt>Credit discount</dt><dd>-{{ $money($quote->credit_usd) }} <span class="ns-small">({{ rtrim(rtrim(number_format((float) $quote->credits, 2), '0'), '.') }} credits)</span></dd></div>
            @endif
            @if ($rateLine)
                <div><dt>Exchange rate</dt><dd>{{ $rateLine['currency'] }} {{ number_format($rateLine['per_usd'], 2) }} = $1 <span class="ns-small">@if ($rateLine['as_of']) · live, checked {{ \Illuminate\Support\Carbon::createFromTimestamp($rateLine['as_of'])->diffForHumans() }}@else · live @endif</span></dd></div>
            @endif
            <div class="ns-tp__total"><dt>You pay</dt><dd>{{ $money($quote->price_usd) }}</dd></div>
        </dl>
        @if ($quote->product->type === 'data' && \App\Support\TopupSettings::bool('credit_discount_enabled') && \App\Support\CreditSettings::enabled())
            <label class="ns-tp__chk"><input type="checkbox" wire:model.live="useCredits"> Use my Credit for a small discount on data</label>
        @endif

        <div class="ns-tp__auto">
            <label class="ns-tp__chk"><input type="checkbox" wire:model.live="saveBeneficiary"> Save this number to my people</label>
            @if ($saveBeneficiary || $autoOn)
                <label class="ns-tp__field"><span class="ns-small">Name</span><input type="text" wire:model="beneficiaryName" maxlength="60" class="ns-input" placeholder="Mum, Tunde, Office line…" aria-label="Contact name"></label>
            @endif
            @if (\App\Support\TopupSettings::bool('autoreload_enabled'))
                <label class="ns-tp__chk"><input type="checkbox" wire:model.live="autoOn"> Repeat this top-up automatically</label>
                @if ($autoOn)
                    <div class="ns-tp__row">
                        <label class="ns-tp__field"><span class="ns-small">How often</span>
                            <select wire:model.live="freq" class="ns-input" aria-label="Frequency"><option value="daily">Every day</option><option value="weekly">Every week</option><option value="monthly">Every month</option></select></label>
                        @if ($freq === 'weekly')<label class="ns-tp__field"><span class="ns-small">On</span><select wire:model.live="weekday" class="ns-input" aria-label="Weekday">@foreach ($days as $i => $d)<option value="{{ $i }}">{{ $d }}</option>@endforeach</select></label>@endif
                        @if ($freq === 'monthly')<label class="ns-tp__field"><span class="ns-small">On day</span><select wire:model.live="monthday" class="ns-input" aria-label="Day of month">@for ($d = 1; $d <= 28; $d++)<option value="{{ $d }}">{{ $d }}</option>@endfor</select></label>@endif
                        <label class="ns-tp__field"><span class="ns-small">At</span><select wire:model.live="hour" class="ns-input" aria-label="Hour">@for ($h = 0; $h < 24; $h++)<option value="{{ $h }}">{{ str_pad((string) $h, 2, '0', STR_PAD_LEFT) }}:00</option>@endfor</select></label>
                    </div>
                    <p class="ns-small ns-tp__lock"><x-nx.icon name="refresh" /> After this one, we send the same top-up {{ $freq === 'daily' ? 'every day' : ($freq === 'weekly' ? 'every '.$days[$weekday] : 'on day '.$monthday.' of each month') }} at {{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00, charged at the price then. If your wallet is short we skip it and tell you. Pause or stop it any time in My people.</p>
                @endif
            @endif
        </div>

        <p class="ns-small ns-tp__lock"><x-nx.icon name="lock" /> Price held for <span x-text="Math.floor(left/60) + ':' + String(left%60).padStart(2,'0')"></span>. <span x-show="left <= 0" x-cloak>It expired: tap Pay to refresh it.</span></p>
        <p class="ns-small">Wallet balance {{ $money($spendable) }}@if ($spendable < (float) $quote->price_usd) · <a href="{{ route('wallet') }}" wire:navigate>Add funds</a>@endif. If we cannot deliver, you are refunded automatically.</p>
        <button type="button" class="ns-cta ns-cta--pill ns-tp__pay" wire:click="pay" wire:loading.attr="disabled" wire:target="pay">
            <span wire:loading.remove wire:target="pay">Pay {{ $money($quote->price_usd) }} from wallet{{ $autoOn ? ' and start auto top-up' : '' }}</span><span wire:loading wire:target="pay">Sending your top-up…</span>
        </button>
    </section>
    @endif
</x-nx.page>
</div>
