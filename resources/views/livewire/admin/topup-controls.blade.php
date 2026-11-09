{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm';
    $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lbl = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $btn = 'rounded-lg bg-[rgb(var(--nx-teal))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60';
    $btn2 = 'rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))] disabled:opacity-60';
    $tabs = ['overview' => 'Overview', 'suppliers' => 'Suppliers', 'routes' => 'Routes', 'pricing' => 'Pricing', 'limits' => 'Limits', 'rates' => 'Rates', 'catalogue' => 'Catalogue', 'logos' => 'Logos', 'offers' => 'Offers', 'hero' => 'Hero & banners', 'orders' => 'Orders', 'logs' => 'Supplier logs'];
    $statusColor = fn ($s) => match ($s) { 'delivered' => '--nx-ok', 'refunded', 'failed', 'payment_failed' => '--nx-bad', default => '--nx-warn' };
@endphp
<div class="mx-auto max-w-6xl">
    <div class="flex flex-wrap items-center gap-3">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Top-ups: airtime and data</h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">For physical SIMs. Live offers from the suppliers you switch on, a locked price at checkout, automatic refunds when a top-up cannot be delivered.</p>
        </div>
        <button type="button" wire:click="toggleFeature" wire:loading.attr="disabled" wire:target="toggleFeature" data-tour="topup-switch"
                class="rounded-full px-4 py-2 text-sm font-semibold {{ $featureOn ? 'bg-[rgb(var(--nx-ok))] text-white' : 'border border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]' }}" aria-pressed="{{ $featureOn ? 'true' : 'false' }}">
            {{ $featureOn ? 'Switched on' : 'Switched off' }}
        </button>
    </div>
    <p class="mt-2 text-xs {{ $live ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-warn))]' }}">
        {{ $live ? 'Live for customers.' : ($featureOn ? 'Switched on, but not live yet: enable at least one supplier with keys saved (Suppliers tab).' : 'Customers see "coming soon" until you switch this on and enable a supplier.') }}
    </p>

    @if ($saved)<div class="mt-3 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $saved }}</div>@endif
    @if ($problem)<div class="mt-3 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-bad)/0.12)] p-3 text-sm text-[rgb(var(--nx-bad))]" role="alert"><x-icon name="alert-triangle" class="h-4 w-4" /> {{ $problem }}</div>@endif

    <nav class="mt-4 flex gap-1 overflow-x-auto border-b border-[rgb(var(--nx-line))]" aria-label="Top-up sections">
        @foreach ($tabs as $k => $t)
            <button type="button" wire:click="setTab('{{ $k }}')" class="whitespace-nowrap px-3 py-2 text-sm font-semibold {{ $tab === $k ? 'border-b-2 border-[rgb(var(--nx-teal))] text-[rgb(var(--nx-teal))]' : 'text-[rgb(var(--nx-text-2))]' }}">{{ $t }}</button>
        @endforeach
    </nav>

    {{-- OVERVIEW --}}
    @if ($tab === 'overview')
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
            @foreach ([['Orders (24h)', $kpi['orders24']], ['Delivered (24h)', $kpi['delivered24']], ['In flight', $kpi['pending']], ['Needs a look', $kpi['escalated']], ['Refunded (24h)', $kpi['refunded24']], ['Live products', $kpi['products']], ['Active auto top-ups', $kpi['autos']]] as [$l, $v])
                <div class="{{ $card }} !p-4"><p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $l }}</p><p class="text-xl font-bold {{ $l === 'Needs a look' && $v ? 'text-[rgb(var(--nx-warn))]' : 'text-[rgb(var(--nx-text))]' }}">{{ number_format($v) }}</p></div>
            @endforeach
        </div>
        <section class="{{ $card }} mt-4">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Money, last 30 days</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-4">
                <div><p class="text-xs text-[rgb(var(--nx-text-2))]">Revenue</p><p class="text-lg font-bold">${{ number_format($margin['revenue'], 2) }}</p></div>
                <div><p class="text-xs text-[rgb(var(--nx-text-2))]">Supplier cost</p><p class="text-lg font-bold">${{ number_format($margin['cost'], 2) }}</p></div>
                <div><p class="text-xs text-[rgb(var(--nx-text-2))]">Margin</p><p class="text-lg font-bold text-[rgb(var(--nx-ok))]">${{ number_format($margin['margin'], 2) }}</p></div>
                <div><p class="text-xs text-[rgb(var(--nx-text-2))]">Delivered orders</p><p class="text-lg font-bold">{{ number_format($margin['orders']) }}</p></div>
            </div>
            @if ($margin['by_provider'])
                <table class="mt-3 w-full text-left text-xs"><thead class="text-[rgb(var(--nx-text-2))]"><tr><th class="py-1">Supplier</th><th class="text-right">Orders</th><th class="text-right">Revenue</th><th class="text-right">Cost</th><th class="text-right">Margin</th></tr></thead><tbody>
                @foreach ($margin['by_provider'] as $code => $m)<tr class="border-t border-[rgb(var(--nx-line))]"><td class="py-1.5 font-semibold">{{ \App\Services\Topup\TopupProviders::META[$code]['name'] ?? $code }}</td><td class="text-right">{{ $m['orders'] }}</td><td class="text-right">${{ number_format($m['revenue'], 2) }}</td><td class="text-right">${{ number_format($m['cost'], 2) }}</td><td class="text-right">${{ number_format($m['revenue'] - $m['cost'], 2) }}</td></tr>@endforeach
                </tbody></table>
            @else<p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">No delivered top-ups yet.</p>@endif
        </section>
        <section class="{{ $card }} mt-4">
            <div class="flex flex-wrap items-center gap-2"><h2 class="flex-1 text-base font-semibold text-[rgb(var(--nx-text))]">Reconciliation</h2>
                <button type="button" wire:click="reconcileNow" wire:loading.attr="disabled" wire:target="reconcileNow" class="{{ $btn2 }}"><span wire:loading.remove wire:target="reconcileNow">Reconcile last 48h now</span><span wire:loading wire:target="reconcileNow">Checking suppliers…</span></button></div>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Every day we ask each supplier about our last 48 hours of orders and flag any disagreement (delivered there but refunded here, or the other way round).</p>
            @if ($reconcile)<p class="mt-2 text-sm">Last run {{ \Illuminate\Support\Carbon::parse($reconcile['at'])->diffForHumans() }}: {{ $reconcile['checked'] }} checked, <b class="{{ count($reconcile['mismatches']) ? 'text-[rgb(var(--nx-bad))]' : 'text-[rgb(var(--nx-ok))]' }}">{{ count($reconcile['mismatches']) }} mismatch(es)</b>.</p>
                @foreach ($reconcile['mismatches'] as $m)<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $m['order'] }}: we say {{ $m['ours'] }}, {{ $m['provider'] }} says {{ $m['supplier'] }}</p>@endforeach
            @else<p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">Not run yet.</p>@endif
        </section>
    @endif

    {{-- SUPPLIERS --}}
    @if ($tab === 'suppliers')
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <p class="flex-1 text-xs text-[rgb(var(--nx-text-2))]">Keys are saved under <a class="underline" href="{{ route('admin.api-keys') }}" wire:navigate>API keys</a> (group "Airtime and data top-ups"; Zendit and Reloadly use the keys you already saved).</p>
            <button type="button" wire:click="pollFloat" wire:loading.attr="disabled" wire:target="pollFloat" class="{{ $btn2 }}">Check all floats now</button>
        </div>
        <div class="mt-3 grid gap-4 lg:grid-cols-2">
        @foreach (\App\Services\Topup\TopupProviders::META as $code => $meta)
            @php $row = $providers[$code] ?? null; $has = $configured[$code] ?? false; @endphp
            <section class="{{ $card }}" wire:key="sup-{{ $code }}">
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1"><h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">{{ $meta['name'] }}</h2><p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">{{ $meta['note'] }}</p></div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $has ? 'bg-[rgb(var(--nx-ok)/0.15)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-warn)/0.15)] text-[rgb(var(--nx-warn))]' }}">{{ $has ? 'Keys saved' : 'No keys' }}</span>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Float</dt><dd class="font-semibold">{{ $row?->last_balance !== null ? $row->currency.' '.number_format((float) $row->last_balance, 2) : 'unknown' }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Checked</dt><dd class="font-semibold">{{ $row?->last_balance_at?->diffForHumans() ?? 'never' }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">Catalogue synced</dt><dd class="font-semibold">{{ $row?->last_synced_at?->diffForHumans() ?? 'never' }}</dd></div>
                    <div><dt class="text-[rgb(var(--nx-text-2))]">In routing</dt><dd class="font-semibold">{{ $router->eligible($code) ? 'Yes' : 'No' }}</dd></div>
                </dl>
                @if ($row?->last_error)<p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">Last error: {{ $row->last_error }}</p>@endif
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="suppliers.{{ $code }}.enabled" class="h-4 w-4"> Enabled</label>
                    <div><label class="{{ $lbl }}" for="mf-{{ $code }}">Pause routing below this float ({{ $meta['currency'] }})</label><input id="mf-{{ $code }}" type="number" step="0.01" min="0" wire:model="suppliers.{{ $code }}.min_float" class="{{ $inp }}"></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" wire:click="saveSupplier('{{ $code }}')" wire:loading.attr="disabled" wire:target="saveSupplier('{{ $code }}')" class="{{ $btn }}">Save</button>
                    <button type="button" wire:click="testSupplier('{{ $code }}')" wire:loading.attr="disabled" wire:target="testSupplier('{{ $code }}')" class="{{ $btn2 }}"><span wire:loading.remove wire:target="testSupplier('{{ $code }}')">Test connection</span><span wire:loading wire:target="testSupplier('{{ $code }}')">Testing…</span></button>
                </div>
                @if ($code === 'vtpass' || $code === 'zendit' || $code === 'reloadly')
                    <p class="mt-3 break-all text-xs text-[rgb(var(--nx-text-2))]">Callback URL: {{ url('/webhooks/topup/'.$code) }}?secret=<i>your webhook secret</i></p>
                @endif
            </section>
        @endforeach
        </div>
    @endif

    {{-- ROUTES --}}
    @if ($tab === 'routes')
        <section class="{{ $card }} mt-4">
            <div class="flex flex-wrap items-center gap-2"><h2 class="flex-1 text-base font-semibold text-[rgb(var(--nx-text))]">Who serves which country</h2><button type="button" wire:click="seedDefaults" class="{{ $btn2 }}">Add missing default routes</button></div>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Country + product type give an ordered list of suppliers. The first one that is enabled, has keys and enough float serves the order; if it fails for certain, the next one takes over with a new reference. A country with no route is not sold. A country-specific route outranks "*" (every country). No deploy needed.</p>
            <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[560px] text-left text-sm"><thead class="text-xs text-[rgb(var(--nx-text-2))]"><tr><th class="py-1">Country</th><th>Type</th><th>Supplier</th><th>Order</th><th>On</th><th></th></tr></thead><tbody>
            @forelse ($routes as $r)
                <tr class="border-t border-[rgb(var(--nx-line))]" wire:key="rt{{ $r->id }}"><td class="py-2 font-semibold">{{ in_array($r->country_iso, [null, '', '*'], true) ? 'Every country' : $r->country_iso.' · '.\App\Support\CountryNames::name($r->country_iso) }}</td><td>{{ $r->product_type === '*' ? 'Airtime + data' : ucfirst($r->product_type) }}</td><td>{{ \App\Services\Topup\TopupProviders::META[$r->provider_code]['name'] ?? $r->provider_code }}</td>
                    <td><button type="button" wire:click="moveRoute({{ $r->id }}, -1)" class="{{ $btn2 }}" aria-label="Higher priority">Up</button> <span class="mx-1">{{ $r->priority }}</span> <button type="button" wire:click="moveRoute({{ $r->id }}, 1)" class="{{ $btn2 }}" aria-label="Lower priority">Down</button></td>
                    <td><input type="checkbox" @checked($r->enabled) wire:click="toggleRoute({{ $r->id }})" class="h-4 w-4" aria-label="Route enabled"></td><td><button type="button" wire:click="deleteRoute({{ $r->id }})" wire:confirm="Remove this route?" class="text-xs text-[rgb(var(--nx-bad))]">Remove</button></td></tr>
            @empty<tr><td colspan="6" class="py-4 text-sm text-[rgb(var(--nx-text-2))]">No routes: no country is sellable. Tap "Add missing default routes".</td></tr>@endforelse
            </tbody></table></div>
            <form wire:submit="addRoute" class="mt-4 grid gap-3 border-t border-[rgb(var(--nx-line))] pt-4 sm:grid-cols-4">
                <div><label class="{{ $lbl }}" for="nr-c">Country (2 letters, or * for all)</label><input id="nr-c" wire:model="newRouteCountry" maxlength="2" placeholder="NG" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}" for="nr-t">Type</label><select id="nr-t" wire:model="newRouteType" class="{{ $inp }}"><option value="*">Airtime + data</option><option value="airtime">Airtime</option><option value="data">Data</option></select></div>
                <div><label class="{{ $lbl }}" for="nr-p">Supplier</label><select id="nr-p" wire:model="newRouteProvider" class="{{ $inp }}">@foreach (\App\Services\Topup\TopupProviders::META as $c => $m)<option value="{{ $c }}">{{ $m['name'] }}</option>@endforeach</select></div>
                <div class="flex items-end"><button type="submit" class="{{ $btn }} w-full">Add route</button></div>
            </form>
            @error('newRouteCountry')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        </section>
    @endif

    {{-- PRICING + LIMITS share one form --}}
    @if ($tab === 'pricing' || $tab === 'limits')
        <form wire:submit="saveSettings" class="{{ $card }} mt-4 grid gap-4 sm:grid-cols-3">
            @php
                $pricing = [['markup_pct', 'Markup over our cost (%)', '0.1'], ['min_profit_usd', 'Minimum profit per order (USD)', '0.01'], ['fx_buffer_pct', 'FX safety buffer (%)', '0.1'], ['quote_ttl_seconds', 'Price lock (seconds)', '1'], ['offer_cache_seconds', 'Live offers cached for (seconds)', '1'], ['default_country', 'Default country (2 letters)', ''], ['credit_profit_share_pct', 'Credit discount: max share of our profit (%)', '1'], ['credit_max_discount_usd', 'Credit discount: max per order (USD)', '0.01'], ['credit_min_profit_usd', 'Profit that must remain after a credit discount (USD)', '0.01']];
                $limits = [['min_order_usd', 'Smallest order (USD)', '0.1'], ['max_order_usd', 'Largest order (USD)', '1'], ['daily_cap_usd', 'Per-customer daily cap (USD)', '1'], ['weekly_cap_usd', 'Per-customer weekly cap (USD)', '1'], ['new_account_hours', 'A new account is under (hours)', '1'], ['new_account_daily_cap_usd', 'New-account daily cap (USD)', '1'], ['max_numbers_per_day', 'Different numbers per day', '1'], ['max_orders_per_hour', 'Orders per hour', '1'], ['escalate_after_minutes', 'Flag a pending order after (minutes)', '1'], ['max_autoreloads_per_user', 'Active auto top-ups per customer', '1'], ['catalogue_drop_alert_pct', 'Alert if a country loses more than (%) of its products', '1'], ['log_retention_days', 'Keep supplier call logs (days)', '1']];
                $fields = $tab === 'pricing' ? $pricing : $limits;
            @endphp
            @if ($tab === 'pricing')
                <div class="sm:col-span-3 rounded-xl bg-[rgb(var(--nx-surface-2))] p-3 text-xs text-[rgb(var(--nx-text-2))]">
                    <b class="text-[rgb(var(--nx-text))]">Price = (our cost in USD x FX buffer) x (1 + markup)</b>, never below cost plus the minimum profit. Example: a top-up costing us $9.00 with a {{ $s['markup_pct'] }}% markup is sold at ${{ number_format(max(9 * (1 + (float) $s['markup_pct'] / 100), 9 + (float) $s['min_profit_usd']), 2) }}. The price is locked for {{ $s['quote_ttl_seconds'] }} seconds, and a customer is never re-priced after paying.
                </div>
            @endif
            @foreach ($fields as [$k, $label, $step])
                <div wire:key="f-{{ $k }}"><label class="{{ $lbl }}" for="s-{{ $k }}">{{ $label }}</label><input id="s-{{ $k }}" @if ($step !== '') type="number" step="{{ $step }}" @else type="text" @endif wire:model.live.debounce.400ms="s.{{ $k }}" class="{{ $inp }}">@error("s.$k")<span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror</div>
            @endforeach
            @if ($tab === 'pricing')
                <div class="sm:col-span-3"><label class="{{ $lbl }}" for="s-ov">Different markup for a country or one network (one per line: <code>NG=4</code> or <code>NG:mtn=3.5</code>)</label><textarea id="s-ov" rows="4" wire:model="overrides" class="{{ $inp }} font-mono" placeholder="NG=4&#10;GH:mtn=5"></textarea></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="s.failover" class="h-4 w-4"> Fail over to the next supplier on a certain failure</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="s.credit_discount_enabled" class="h-4 w-4"> Let customers use Credit for a small discount on DATA bundles</label>
                <div class="sm:col-span-3 rounded-xl bg-[rgb(var(--nx-surface-2))] p-3 text-xs text-[rgb(var(--nx-text-2))]"><b class="text-[rgb(var(--nx-text))]">Credit discount never eats the margin.</b> It is worked out from the live cost and live price at quote time and is the smallest of: {{ $s['credit_profit_share_pct'] }}% of our profit on that sale, the profit above ${{ number_format(max((float) $s['min_profit_usd'], (float) $s['credit_min_profit_usd']), 2) }}, ${{ number_format((float) $s['credit_max_discount_usd'], 2) }}, the credit max-redeem % on the Credits screen, and the customer's credit balance. Airtime is never discounted. Example: a bundle costing us $2.00 sold at $2.12 leaves $0.12 profit, so the most a customer can save is ${{ number_format(min(0.12 * (float) $s['credit_profit_share_pct'] / 100, 0.12 - max((float) $s['min_profit_usd'], (float) $s['credit_min_profit_usd']), (float) $s['credit_max_discount_usd']), 2) }}.</div>
            @endif
            @if ($tab === 'limits')<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="s.notify_receipts" class="h-4 w-4"> Notify customers on delivery</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="s.autoreload_enabled" class="h-4 w-4"> Allow auto top-ups (daily / weekly / monthly)</label>@endif
            <div class="sm:col-span-3 flex justify-end"><button type="submit" wire:loading.attr="disabled" wire:target="saveSettings" class="{{ $btn }}">Save {{ $tab }}</button></div>
        </form>
    @endif

    {{-- CATALOGUE --}}
    @if ($tab === 'catalogue')
        <section class="{{ $card }} mt-4">
            <div class="flex flex-wrap items-end gap-3">
                <div><label class="{{ $lbl }}" for="cc">Country</label><input id="cc" wire:model.live.debounce.400ms="catalogueCountry" maxlength="2" placeholder="NG" class="{{ $inp }} !w-24"></div>
                <div class="min-w-[160px] flex-1"><label class="{{ $lbl }}" for="cs">Search network or bundle</label><input id="cs" wire:model.live.debounce.400ms="catalogueSearch" class="{{ $inp }}"></div>
                <button type="button" wire:click="refreshCountry" wire:loading.attr="disabled" wire:target="refreshCountry" class="{{ $btn2 }}"><span wire:loading.remove wire:target="refreshCountry">Fetch live now</span><span wire:loading wire:target="refreshCountry">Fetching…</span></button>
                <button type="button" wire:click="syncNow" wire:loading.attr="disabled" wire:target="syncNow" class="{{ $btn }}"><span wire:loading.remove wire:target="syncNow">Sync every routed country</span><span wire:loading wire:target="syncNow">Syncing…</span></button>
            </div>
            <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Customers always get live offers when they open a country (cached {{ $s['offer_cache_seconds'] }}s). This table is the stored snapshot used for quotes and as a fallback if a supplier is down. Turning a product off hides it from customers. Cost shown is private to admins.</p>
            @if ($byCountry->isNotEmpty())<p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Biggest: @foreach ($byCountry as $iso => $n)<button type="button" class="underline" wire:click="$set('catalogueCountry','{{ $iso }}')">{{ $iso }} {{ $n }}</button>@if (! $loop->last), @endif @endforeach</p>@endif
            <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[720px] text-left text-xs"><thead class="text-[rgb(var(--nx-text-2))]"><tr><th class="py-1">Country</th><th>Network</th><th>Product</th><th>Type</th><th class="text-right">Recipient gets</th><th class="text-right">Our cost</th><th class="text-right">Sells at</th><th>Supplier</th><th>On</th></tr></thead><tbody>
            @php $qs = app(\App\Services\Topup\QuoteService::class); @endphp
            @forelse ($products as $p)
                @php try { $pr = $p->is_fixed ? $qs->price($p, (float) $p->fixed_amount) : null; } catch (\Throwable) { $pr = null; } @endphp
                <tr class="border-t border-[rgb(var(--nx-line))]" wire:key="cp{{ $p->id }}"><td class="py-1.5">{{ $p->country_iso }}</td><td class="font-semibold">{{ $p->operator_name }}</td><td>{{ $p->name }}</td><td>{{ $p->type }}</td>
                    <td class="text-right">{{ $p->is_fixed ? $p->currency.' '.number_format((float) $p->fixed_amount, 2) : $p->currency.' '.number_format((float) $p->min_amount).'–'.number_format((float) $p->max_amount) }}</td>
                    <td class="text-right">{{ $p->is_fixed ? ($p->cost_currency ?: 'USD').' '.number_format((float) $p->cost_native, 2) : ($p->cost_currency ?: 'USD').' '.rtrim(rtrim(number_format((float) $p->cost_ratio, 5), '0'), '.').'/unit' }}</td>
                    <td class="text-right">{{ $pr ? '$'.number_format($pr['price'], 2) : 'per amount' }}</td><td>{{ $p->provider_code }}</td>
                    <td><input type="checkbox" @checked($p->active) wire:click="toggleProduct({{ $p->id }})" class="h-4 w-4" aria-label="Product on"></td></tr>
            @empty<tr><td colspan="9" class="py-4 text-sm text-[rgb(var(--nx-text-2))]">No products stored yet. Enable a supplier, then "Fetch live now" for a country.</td></tr>@endforelse
            </tbody></table></div>
            <div class="mt-3">{{ $products->links() }}</div>
        </section>
    @endif

    {{-- LOGOS --}}
    @if ($tab === 'rates')
        <section class="{{ $card }} mt-4">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Rate and margin proof</h2>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">For each network and face value: what the supplier charges us, what we charge, and the benchmark of the same face value at the live mid-market rate (what a par-priced local supplier or bank app effectively charges). Read from the synced catalogue and the same pricing engine the customer is quoted with. Admin only.</p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div><label class="{{ $lbl }}" for="rc">Country</label><select id="rc" wire:model.live="rateCountry" class="{{ $in ?? '' }} rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">@foreach ($rateCountries as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></div>
                <p class="text-sm text-[rgb(var(--nx-text))]">Mid-market: <b>{{ $rateCheck['mid'] ? $rateCheck['currency'].' '.number_format($rateCheck['mid'], 2).' per $1' : 'feed unavailable' }}</b> &middot; markup {{ $rateCheck['settings']['markup_pct'] }}% &middot; FX buffer {{ $rateCheck['settings']['fx_buffer_pct'] }}% &middot; minimum profit ${{ number_format($rateCheck['settings']['min_profit_usd'], 2) }}</p>
            </div>
            <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[44rem] text-left text-xs"><thead class="text-[rgb(var(--nx-text-2))]"><tr><th class="py-1">Network</th><th>Supplier</th><th class="text-right">Face</th><th class="text-right">Our cost $</th><th class="text-right">Our price $</th><th class="text-right">Par $</th><th class="text-right">Markup on cost</th><th class="text-right">Supplier vs par</th><th class="text-right">Our price vs par</th></tr></thead><tbody>
                @forelse ($rateCheck['rows'] as $r)
                    <tr class="border-t border-[rgb(var(--nx-line))]"><td class="py-1.5 font-semibold">{{ $r['operator'] }}</td><td>{{ $r['supplier'] }}</td><td class="text-right">{{ number_format($r['face'], 2) }}</td><td class="text-right">{{ number_format($r['cost_usd'], 2) }}</td><td class="text-right font-semibold">{{ number_format($r['price_usd'], 2) }}</td><td class="text-right">{{ $r['par_usd'] !== null ? number_format($r['par_usd'], 2) : '-' }}</td><td class="text-right">{{ $r['markup_on_cost_pct'] }}%</td><td class="text-right {{ ($r['supplier_vs_par_pct'] ?? 0) > 3 ? 'text-[rgb(var(--nx-warn))] font-semibold' : '' }}">{{ $r['supplier_vs_par_pct'] !== null ? $r['supplier_vs_par_pct'].'%' : '-' }}</td><td class="text-right {{ ($r['price_vs_par_pct'] ?? 0) > 10 ? 'text-[rgb(var(--nx-bad))] font-semibold' : '' }}">{{ $r['price_vs_par_pct'] !== null ? $r['price_vs_par_pct'].'%' : '-' }}</td></tr>
                @empty
                    <tr><td colspan="9" class="py-4 text-[rgb(var(--nx-text-2))]">No fixed-amount airtime products near these face values for this country yet. Sync the catalogue first.</td></tr>
                @endforelse
            </tbody></table></div>
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Reading it: "Supplier vs par" above 3% means that supplier is dearer than paying face value at the mid rate: route this country to a cheaper supplier. "Our price vs par" is what a customer pays over the neutral benchmark; above 10% is shaded red.</p>
        </section>
    @endif

    @if ($tab === 'rates')
        <section class="{{ $card }} mt-4" data-live-rates>
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Live supplier rates</h2>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Calls every supplier's own API right now for the country above and lays the answers side by side: what each supplier sells the product to us for, what we would charge, and our margin. Par is the same face value at the live mid-market rate. Nothing here changes a price; it shows where a contract or a route is out of line. Admin only.</p>
            <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]" data-live-autopilot>@if ($liveLast)Autopilot checked {{ count($liveLast['countries'] ?? []) }} countries live on {{ \Carbon\Carbon::parse($liveLast['at'])->format('j M H:i') }}; {{ count($liveLast['bad'] ?? []) }} price(s) under the minimum profit. It repeats every day by itself.@else Autopilot has not run its daily live check yet; it starts by itself once a supplier is switched on with keys (the scheduler runs it every ten minutes).@endif</p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div><label class="{{ $lbl }}" for="lt">Product</label><select id="lt" wire:model="liveType" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"><option value="airtime">Airtime</option><option value="data">Data bundles</option></select></div>
                <button type="button" wire:click="fetchLive" wire:loading.attr="disabled" wire:target="fetchLive" class="{{ $btn }}"><span wire:loading.remove wire:target="fetchLive">Fetch live rates for {{ $rateCountry }}</span><span wire:loading wire:target="fetchLive">Asking every supplier...</span></button>
            </div>
            @if ($liveSheet)
                @php $ls = $liveSheet; @endphp
                <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">As of {{ \Carbon\Carbon::parse($ls['as_of'])->format('j M H:i:s') }} &middot; mid-market {{ $ls['mid'] ? $ls['currency'].' '.number_format($ls['mid'], 2).' per $1' : 'feed unavailable' }} &middot; markup {{ $ls['settings']['markup_pct'] }}% &middot; FX buffer {{ $ls['settings']['fx_buffer_pct'] }}% &middot; minimum profit ${{ number_format($ls['settings']['min_profit_usd'], 2) }} &middot; routed order: {{ $ls['routed'] ? implode(' > ', $ls['routed']) : 'none (country not sellable)' }}</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($ls['providers'] as $pv)
                        <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3 text-xs" wire:key="lp-{{ $pv['code'] }}">
                            <div class="flex items-baseline justify-between gap-2"><b class="text-sm text-[rgb(var(--nx-text))]">{{ $pv['name'] }}</b><span class="font-semibold text-[rgb(var({{ $pv['state'] === 'ok' ? '--nx-ok' : ($pv['state'] === 'error' ? '--nx-bad' : '--nx-warn') }}))]">{{ $pv['state'] === 'off' ? 'No keys' : ($pv['state'] === 'ok' ? 'Live' : ($pv['state'] === 'empty' ? 'No offers here' : 'Error')) }}</span></div>
                            @if ($pv['error'])<p class="mt-1 text-[rgb(var(--nx-text-2))]">{{ $pv['error'] }}</p>@endif
                            @if ($pv['state'] === 'ok')
                                <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1">
                                    <dt class="text-[rgb(var(--nx-text-2))]">Answered in</dt><dd class="text-right">{{ $pv['ms'] }} ms</dd>
                                    <dt class="text-[rgb(var(--nx-text-2))]">Offers listed</dt><dd class="text-right">{{ $pv['listed'] }}</dd>
                                    <dt class="text-[rgb(var(--nx-text-2))]">Sells to us vs par</dt><dd class="text-right font-semibold">{{ $pv['avg_supplier_vs_par'] !== null ? $pv['avg_supplier_vs_par'].'%' : 'n/a' }}</dd>
                                    <dt class="text-[rgb(var(--nx-text-2))]">Our margin (of price)</dt><dd class="text-right font-semibold">{{ $pv['avg_margin_pct'] !== null ? $pv['avg_margin_pct'].'%' : 'n/a' }}</dd>
                                    <dt class="text-[rgb(var(--nx-text-2))]">Our price vs par</dt><dd class="text-right font-semibold">{{ $pv['avg_price_vs_par'] !== null ? $pv['avg_price_vs_par'].'%' : 'n/a' }}</dd>
                                    <dt class="text-[rgb(var(--nx-text-2))]">Cheapest on</dt><dd class="text-right">{{ $pv['cheapest_count'] ?? 0 }} products</dd>
                                    <dt class="text-[rgb(var(--nx-text-2))]">In our route</dt><dd class="text-right">{{ $pv['routed'] ? 'Yes' : 'No' }}</dd>
                                </dl>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($ls['alerts'])
                    <ul class="mt-3 grid gap-1 text-xs">@foreach ($ls['alerts'] as $a)<li class="font-semibold text-[rgb(var({{ $a['level'] === 'bad' ? '--nx-bad' : ($a['level'] === 'warn' ? '--nx-warn' : '--nx-text') }}))]">{{ $a['text'] }}</li>@endforeach</ul>
                @endif
                <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[56rem] text-left text-xs"><thead class="text-[rgb(var(--nx-text-2))]"><tr><th class="py-1">Network</th><th>Product</th><th>Supplier</th><th class="text-right">Face</th><th class="text-right">Supplier sells us $</th><th class="text-right">We charge $</th><th class="text-right">Margin $</th><th class="text-right">Margin %</th><th class="text-right">Par $</th><th class="text-right">Supplier vs par</th><th class="text-right">Price vs par</th><th class="pl-4">Best cost</th></tr></thead><tbody>
                    @forelse (array_slice($ls['rows'], 0, 300) as $r)
                        <tr class="border-t border-[rgb(var(--nx-line))]"><td class="py-1.5 font-semibold">{{ $r['operator'] }}</td><td>{{ $r['type'] === 'data' ? $r['name'] : 'Airtime' }}</td><td>{{ $r['provider'] }}</td><td class="text-right">{{ number_format($r['face'], 2) }} {{ $r['currency'] }}</td><td class="text-right">{{ number_format($r['cost_usd'], 3) }}</td><td class="text-right font-semibold">{{ number_format($r['price_usd'], 3) }}</td><td class="text-right">{{ number_format($r['margin_usd'], 3) }}</td><td class="text-right">{{ $r['margin_pct'] !== null ? $r['margin_pct'].'%' : '-' }}</td><td class="text-right">{{ $r['par_usd'] !== null ? number_format($r['par_usd'], 3) : '-' }}</td><td class="text-right {{ ($r['supplier_vs_par_pct'] ?? 0) > 3 ? 'font-semibold text-[rgb(var(--nx-warn))]' : '' }}">{{ $r['supplier_vs_par_pct'] !== null ? $r['supplier_vs_par_pct'].'%' : '-' }}</td><td class="text-right {{ ($r['price_vs_par_pct'] ?? 0) > 10 ? 'font-semibold text-[rgb(var(--nx-bad))]' : '' }}">{{ $r['price_vs_par_pct'] !== null ? $r['price_vs_par_pct'].'%' : '-' }}</td><td class="pl-4">{{ $r['competing'] ? ($r['cheapest'] ? 'Cheapest' : '+'.$r['gap_to_best_pct'].'% vs best') : 'Only supplier' }}</td></tr>
                    @empty
                        <tr><td colspan="12" class="py-4 text-[rgb(var(--nx-text-2))]">No supplier returned {{ $ls['type'] === 'data' ? 'data bundles' : 'airtime products near the sampled face values' }} for {{ $ls['country'] }}.</td></tr>
                    @endforelse
                </tbody></table></div>
                <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Reading it: "Supplier vs par" below zero means the supplier sells to us under face value (our contract discount); above zero means they charge more than face at the live rate. "Margin %" is our profit as a share of the customer price. Data bundles have no par because they have no face value; compare them across suppliers with "Best cost". Competitor apps have no price API we can call, so par is the neutral stand-in until you add reference prices by hand.</p>
            @endif
        </section>
    @endif

    @if ($tab === 'logos')
        <section class="{{ $card }} mt-4">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Operator logos</h2>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Every operator in the catalogue is listed. Those without a logo show initials to customers: upload one (WebP, PNG, JPG or SVG, under 512 KB, square or wide on a clear background). An uploaded logo replaces the shipped one. {{ $logoMissing }} of {{ $logoOps->count() }} shown have none.</p>
            <div class="mt-3 max-w-xs"><label class="{{ $lbl }}" for="ls">Find an operator</label><input id="ls" wire:model.live.debounce.300ms="logoSearch" class="{{ $inp }}"></div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($logoOps as $o)
                <div class="flex items-center gap-3 rounded-xl border border-[rgb(var(--nx-line))] p-3" wire:key="lg-{{ $o['key'] }}">
                    <span class="grid h-12 w-12 flex-none place-items-center overflow-hidden rounded-lg bg-white ring-1 ring-[rgb(var(--nx-line-strong))]">@if ($o['url'])<img src="{{ $o['url'] }}" alt="" class="h-full w-full object-contain p-1">@else<b class="text-sm text-[rgb(var(--nx-teal))]">{{ \App\Support\TopupLogos::initials($o['name']) }}</b>@endif</span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $o['name'] }}</p><p class="text-xs {{ $o['url'] ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-warn))]' }}">{{ $o['custom'] ? 'Your upload' : ($o['url'] ? 'Shipped logo' : 'No logo') }}</p>
                        <input type="file" wire:model="logoFile.{{ $o['key'] }}" accept="image/*" class="mt-1 w-full text-xs" aria-label="Upload logo for {{ $o['name'] }}">
                        @error('logoFile.'.$o['key'])<span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror
                        <div class="mt-1 flex gap-2"><button type="button" wire:click="saveLogo('{{ $o['key'] }}')" wire:loading.attr="disabled" class="{{ $btn2 }}">Save logo</button>@if ($o['custom'])<button type="button" wire:click="clearLogo('{{ $o['key'] }}')" class="text-xs text-[rgb(var(--nx-bad))]">Remove</button>@endif</div></div>
                </div>
            @endforeach
            </div>
        </section>
    @endif

    {{-- OFFERS --}}
    @if ($tab === 'offers')
        @php $adminOffers = app(\App\Services\Topup\TopupOffers::class)->for($offerCountry ?: 'NG', 24, true); @endphp
        <section class="{{ $card }} mt-4 grid gap-4">
            <div><h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Offers strip</h2><p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Real data bundles from the live catalogue, bought in one tap from the Top-up page. They appear on their own once a supplier key is saved and the catalogue has synced; nothing to upload. The price is the standard price, or the offer markup below when you set one (the minimum profit still applies). Customers who pay with credit get a small slice of our profit back, never more than the margin allows.</p></div>
            <form wire:submit="saveOffers" class="grid gap-3 sm:grid-cols-4 items-end">
                <label class="flex items-center gap-2 text-sm sm:col-span-4"><input type="checkbox" wire:model="s.offers_enabled" class="h-4 w-4"> Show the offers strip</label>
                <div><label class="{{ $lbl }}" for="om">Offer markup over cost (%)</label><input id="om" type="number" step="0.1" min="0" max="100" wire:model="s.offers_markup_pct" placeholder="standard" class="{{ $inp }}">@error('s.offers_markup_pct')<span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror</div>
                <div><label class="{{ $lbl }}" for="omx">Offers shown</label><input id="omx" type="number" min="1" max="24" wire:model="s.offers_max" class="{{ $inp }}"></div>
                <div><label class="{{ $lbl }}" for="oc">Preview country</label><input id="oc" maxlength="2" wire:model.live.debounce.400ms="offerCountry" class="{{ $inp }} uppercase"></div>
                <button type="submit" class="{{ $btn }}" wire:loading.attr="disabled" wire:target="saveOffers">Save offers</button>
            </form>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-[rgb(var(--nx-text-2))]"><th class="py-1 pr-3">Bundle</th><th class="pr-3">Network</th><th class="pr-3">Price</th><th class="pr-3">Credit saving</th><th></th></tr></thead>
                <tbody>
                @forelse ($adminOffers as $o)
                    <tr class="border-t border-[rgb(var(--nx-line))] {{ $o['hidden'] ? 'opacity-50' : '' }}" wire:key="ao{{ $o['id'] }}">
                        <td class="py-2 pr-3 font-semibold">{{ $o['title'] }} <span class="text-xs font-normal text-[rgb(var(--nx-text-2))]">{{ $o['validity'] }}</span></td>
                        <td class="pr-3">{{ $o['operator'] }}</td><td class="pr-3">${{ number_format($o['price'], 2) }}</td><td class="pr-3">${{ number_format($o['save'], 2) }} ({{ $o['pct'] }}%)</td>
                        <td class="flex gap-2 py-2"><button type="button" wire:click="toggleOfferPinned({{ $o['id'] }})" class="{{ $btn2 }}">{{ $o['pinned'] ? 'Unpin' : 'Pin first' }}</button><button type="button" wire:click="toggleOfferHidden({{ $o['id'] }})" class="{{ $btn2 }}">{{ $o['hidden'] ? 'Show' : 'Hide' }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-[rgb(var(--nx-text-2))]">No data bundles for this country yet. Save a supplier key and run Catalogue &gt; Sync now.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    @endif

    {{-- HERO + BANNERS --}}
    @if ($tab === 'hero')
        <form wire:submit="saveHero" class="{{ $card }} mt-4 grid gap-4">
            <div><h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Hero and offer banners</h2><p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">The top of the Top-up page: a headline beside the shipped illustration, then a slidable carousel of your offer banners. Banners are 2:1 (for example 1774 x 887). Until you upload your own, the four banners from the Numbers hero are used.</p></div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="{{ $lbl }}" for="ht">Headline</label><input id="ht" wire:model="heroTitle" maxlength="70" class="{{ $inp }}">@error('heroTitle')<span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror</div>
                <div><label class="{{ $lbl }}" for="hd">Line under it</label><input id="hd" wire:model="heroDesc" maxlength="200" class="{{ $inp }}"></div>
            </div>
            <div class="flex flex-wrap items-center gap-5 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="heroEnabled" class="h-4 w-4"> Show the hero and banners</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="heroAutoplay" class="h-4 w-4"> Slide automatically</label>
                <label class="flex items-center gap-2">every <input type="number" min="2" max="30" wire:model="heroInterval" class="{{ $inp }} !w-20"> seconds</label>
            </div>
            <div>
                <label class="{{ $lbl }}" for="nb">Add banners (you can pick several)</label>
                <input id="nb" type="file" wire:model="newBanners" multiple accept="image/*" class="text-sm">
                <div wire:loading wire:target="newBanners" class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Uploading…</div>
                @error('newBanners.*')<span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span>@enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($slides as $i => $sl)
                    <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3" wire:key="sl{{ $i }}-{{ md5($sl['image']) }}">
                        <img src="{{ \Illuminate\Support\Str::startsWith($sl['image'], ['http', '/storage', '//']) ? $sl['image'] : asset(ltrim($sl['image'], '/')) }}" alt="" class="aspect-[2/1] w-full rounded-lg object-cover">
                        <div class="mt-2 grid gap-2"><input wire:model="slides.{{ $i }}.title" maxlength="80" placeholder="Caption (optional)" class="{{ $inp }}" aria-label="Caption {{ $i + 1 }}"><input wire:model="slides.{{ $i }}.link" placeholder="Link when tapped (optional, /top-up or https://…)" class="{{ $inp }}" aria-label="Link {{ $i + 1 }}"></div>
                        <div class="mt-2 flex gap-2"><button type="button" wire:click="moveSlide({{ $i }}, -1)" class="{{ $btn2 }}">Earlier</button><button type="button" wire:click="moveSlide({{ $i }}, 1)" class="{{ $btn2 }}">Later</button><button type="button" wire:click="removeSlide({{ $i }})" class="ml-auto text-xs text-[rgb(var(--nx-bad))]">Remove</button></div>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap justify-between gap-2"><button type="button" wire:click="resetBanners" class="{{ $btn2 }}">Use the shipped banners</button><button type="submit" wire:loading.attr="disabled" wire:target="saveHero" class="{{ $btn }}">Save hero and banners</button></div>
        </form>
    @endif

    {{-- ORDERS --}}
    @if ($tab === 'orders')
        <section class="{{ $card }} mt-4">
            <div class="flex flex-wrap items-end gap-3">
                <div><label class="{{ $lbl }}" for="os">Status</label><select id="os" wire:model.live="orderStatus" class="{{ $inp }}"><option value="">All</option>@foreach (['funds_held', 'submitting', 'pending', 'delivered', 'failed', 'refunded', 'payment_failed'] as $st)<option value="{{ $st }}">{{ str_replace('_', ' ', $st) }}</option>@endforeach</select></div>
                <div class="min-w-[180px] flex-1"><label class="{{ $lbl }}" for="oq">Phone, receipt or reference</label><input id="oq" wire:model.live.debounce.400ms="orderSearch" class="{{ $inp }}"></div>
            </div>
            <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[760px] text-left text-xs"><thead class="text-[rgb(var(--nx-text-2))]"><tr><th class="py-1">When</th><th>Customer</th><th>Number</th><th>Product</th><th>Supplier</th><th class="text-right">Paid</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse ($orders as $o)
                <tr class="border-t border-[rgb(var(--nx-line))]" wire:key="ro{{ $o->id }}"><td class="py-1.5">{{ $o->created_at->format('j M H:i') }}</td><td>{{ $o->user?->email ?? '—' }}</td><td>{{ $o->phone_e164 }}</td><td>{{ $o->operator_name }} · {{ $o->product_type === 'data' ? $o->product_name : $o->send_currency.' '.number_format((float) $o->send_amount) }}</td><td>{{ $o->provider_code }}</td><td class="text-right">${{ number_format((float) $o->price_usd, 2) }}</td>
                    <td><span class="font-semibold text-[rgb(var({{ $statusColor($o->status) }}))]">{{ str_replace('_', ' ', $o->status) }}</span>@if ($o->escalated) <span class="text-[rgb(var(--nx-warn))]">· review</span>@endif</td>
                    <td><button type="button" wire:click="open({{ $o->id }})" class="{{ $btn2 }}">{{ $openOrder === $o->id ? 'Close' : 'Open' }}</button></td></tr>
                @if ($detail && $detail->id === $o->id)
                    <tr wire:key="rd{{ $o->id }}"><td colspan="8" class="bg-[rgb(var(--nx-surface-2))] p-4">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div><p class="text-xs font-semibold">Order {{ $detail->uuid }}</p>
                                <dl class="mt-2 grid grid-cols-2 gap-1 text-xs"><dt class="text-[rgb(var(--nx-text-2))]">Supplier reference</dt><dd class="break-all">{{ $detail->reference }}</dd><dt class="text-[rgb(var(--nx-text-2))]">Supplier txn id</dt><dd class="break-all">{{ $detail->provider_txn_id ?: '—' }}</dd><dt class="text-[rgb(var(--nx-text-2))]">Our cost (USD)</dt><dd>{{ $detail->cost_usd !== null ? '$'.number_format((float) $detail->cost_usd, 4) : '—' }}</dd><dt class="text-[rgb(var(--nx-text-2))]">Supplier charged</dt><dd>{{ $detail->cost_native !== null ? $detail->cost_currency.' '.number_format((float) $detail->cost_native, 2) : '—' }}</dd><dt class="text-[rgb(var(--nx-text-2))]">Attempts / requeries</dt><dd>{{ $detail->attempts }} / {{ $detail->requery_count }}</dd><dt class="text-[rgb(var(--nx-text-2))]">Failure reason</dt><dd>{{ $detail->failure_reason ?: '—' }}</dd></dl>
                                @unless ($detail->isTerminal())
                                    <div class="mt-3 flex flex-wrap items-end gap-2">
                                        <button type="button" wire:click="requery({{ $detail->id }})" wire:loading.attr="disabled" wire:target="requery({{ $detail->id }})" class="{{ $btn2 }}">Ask the supplier now</button>
                                        <input wire:model="refundReason" placeholder="Reason to refund" class="{{ $inp }} !w-56" aria-label="Refund reason">
                                        <button type="button" wire:click="refund({{ $detail->id }})" wire:confirm="Refund this customer? Check the supplier record first." wire:loading.attr="disabled" wire:target="refund({{ $detail->id }})" class="rounded-lg bg-[rgb(var(--nx-bad))] px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60">Refund customer</button>
                                    </div>
                                    <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Only refund a pending order after the supplier has confirmed it did not deliver.</p>
                                @endunless
                            </div>
                            <div><p class="text-xs font-semibold">Timeline</p><ol class="mt-2 grid gap-1 text-xs">@foreach ($detail->events as $ev)<li><span class="text-[rgb(var(--nx-text-2))]">{{ $ev->created_at?->format('H:i:s') }}</span> <b>{{ $ev->type }}</b>@if ($ev->payload) <span class="break-all text-[rgb(var(--nx-text-2))]">{{ \Illuminate\Support\Str::limit(json_encode($ev->payload), 140) }}</span>@endif</li>@endforeach</ol></div>
                        </div></td></tr>
                @endif
            @empty<tr><td colspan="8" class="py-4 text-sm text-[rgb(var(--nx-text-2))]">No orders match.</td></tr>@endforelse
            </tbody></table></div>
            <div class="mt-3">{{ $orders->links() }}</div>
        </section>
    @endif

    {{-- LOGS --}}
    @if ($tab === 'logs')
        <section class="{{ $card }} mt-4">
            <div class="flex flex-wrap items-end gap-3"><div><label class="{{ $lbl }}" for="lp">Supplier</label><select id="lp" wire:model.live="logProvider" class="{{ $inp }}"><option value="">All</option>@foreach (\App\Services\Topup\TopupProviders::META as $c => $m)<option value="{{ $c }}">{{ $m['name'] }}</option>@endforeach</select></div>
                <p class="flex-1 text-xs text-[rgb(var(--nx-text-2))]">Every call to a supplier, with keys, secrets and tokens redacted. Kept {{ $s['log_retention_days'] }} days, because phone numbers are personal data.</p></div>
            <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[720px] text-left text-xs"><thead class="text-[rgb(var(--nx-text-2))]"><tr><th class="py-1">When</th><th>Supplier</th><th>Call</th><th>Ref</th><th class="text-right">HTTP</th><th class="text-right">ms</th><th>Response</th></tr></thead><tbody>
            @forelse ($logs as $l)<tr class="border-t border-[rgb(var(--nx-line))] align-top" wire:key="lg{{ $l->id }}"><td class="py-1.5 whitespace-nowrap">{{ $l->created_at->format('j M H:i:s') }}</td><td>{{ $l->provider_code }}</td><td>{{ $l->endpoint }}</td><td class="break-all">{{ $l->reference }}</td><td class="text-right">{{ $l->http_status }}</td><td class="text-right">{{ $l->duration_ms }}</td><td class="break-all">{{ \Illuminate\Support\Str::limit($l->response, 160) }}</td></tr>
            @empty<tr><td colspan="7" class="py-4 text-sm text-[rgb(var(--nx-text-2))]">No supplier calls yet.</td></tr>@endforelse
            </tbody></table></div>
            <div class="mt-3">{{ $logs->links() }}</div>
        </section>
    @endif
</div>
