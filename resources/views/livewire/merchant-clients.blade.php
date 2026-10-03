{{-- Merchant clients on the skin system (S3 Batch 4): list + status countdowns, and the five action sheets (client / assign / invoice / deliver / reserve). --}}
<div x-data="{ sheet: false, assign: false, invoice: false, deliver: false, reserve: false }"
     @close-client-sheet.window="sheet = false"
     @close-reserve-sheet.window="reserve = false">
<x-nx.page class="ns-pg">
    {{-- Header + wallet --}}
    <div class="ns-pg__head">
        <div>
            <h1 class="ns-h1" style="margin-top:6px">Clients</h1>
            <p class="ns-sub">Manage eSIMs for people who don't have an account.</p>
        </div>
        <button type="button" @click="$wire.newClient(); sheet = true" class="ns-cta ns-cta--pill" style="margin-top:8px"><x-nx.icon name="plus" /> Add client</button>
    </div>

    {{-- Wallet strip: spendable vs reserved (locked for auto-renewals) --}}
    <div class="ns-pg__stats" style="grid-template-columns:repeat(3,minmax(0,1fr))">
        <div class="ns-pg__stat ns-ring"><small>Spendable</small><b>${{ number_format($spendableUsd, 2) }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Reserved</small><b class="is-warn">${{ number_format($reservedUsd, 2) }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Wallet</small><b>${{ number_format($walletUsd, 2) }}</b></div>
    </div>

    {{-- Search --}}
    <label class="ns-search" style="margin-top:16px"><x-nx.icon name="search" /><input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, email, WhatsApp, device…" aria-label="Search clients"></label>

    {{-- Client list --}}
    <div class="ns-pg__stack ns-pg__stack--sm" style="margin-top:14px">
        @forelse ($clients as $client)
            @php $sub = $client->subscriptions->first(); $days = $sub?->daysLeft(); @endphp
            <div wire:key="mc-{{ $client->id }}" class="ns-pg__card ns-pg__card--tight ns-ring {{ $client->is_active ? '' : 'ns-pg__dim' }}" style="margin-top:0">
                <div class="ns-pg__head" style="flex-wrap:nowrap;align-items:flex-start;gap:10px">
                    <div style="min-width:0">
                        <b class="ns-pg__h2">{{ $client->name }}</b>
                        <p class="ns-small" style="margin:2px 0 0">
                            @if ($client->device){{ $client->device }} · @endif{{ $client->esim_orders_count }} eSIM{{ $client->esim_orders_count === 1 ? '' : 's' }}@if ($client->email) · {{ $client->email }}@endif
                        </p>
                    </div>
                    @unless ($client->is_active)<span class="ns-st" style="flex:none">Inactive</span>@endunless
                </div>

                {{-- Current subscription status + countdown --}}
                @if ($sub)
                    @php
                        $tone = match ($sub->status) {
                            'active' => $sub->isDueSoon() ? 'ns-st--warn' : 'ns-st--ok',
                            'expired' => 'ns-st--bad',
                            default => '',
                        };
                        $countdown = is_null($days) ? '' : ($days < 0 ? 'expired '.abs($days).'d ago' : ($days === 0 ? 'expires today' : $days.'d left'));
                    @endphp
                    <div class="ns-pg__kv" style="margin-top:12px;flex-wrap:wrap;justify-content:flex-start">
                        <span class="ns-st {{ $tone }}">{{ $sub->esim_type === 'connect' ? 'Naara Connect' : 'Naara Data' }}</span>
                        <span style="font-weight:600;color:rgb(var(--nx-text))">{{ ucfirst($sub->status) }}@if ($countdown) · {{ $countdown }}@endif</span>
                        @if ($sub->auto_renew)
                            <span class="ns-pg__act is-warn" style="margin-left:auto;color:color-mix(in srgb, rgb(var(--nx-warn)) 70%, rgb(var(--nx-text)))">
                                <x-nx.icon name="refresh" />
                                @if ($sub->renew_indefinitely) Auto-renew · for life
                                @elseif ((int) $sub->reserved_cycles > 1) Auto-renew · {{ (int) $sub->reserved_cycles }} cycles reserved
                                @else Auto-renew locked @endif
                            </span>
                        @endif
                    </div>
                @endif

                {{-- Actions --}}
                <div class="ns-pg__btns" style="margin-top:12px">
                    <button type="button" wire:click="openAssign({{ $client->id }})" @click="assign = true" @disabled(! $client->is_active) class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><x-nx.icon name="plus" /> {{ $sub && $sub->status !== 'disabled' ? 'New / renew eSIM' : 'Assign eSIM' }}</button>
                    @if ($sub && $sub->isActive() && ! $sub->auto_renew)
                        <button type="button" wire:click="openReserve({{ $sub->id }})" @click="reserve = true" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><x-nx.icon name="refresh" /> Auto-renew</button>
                    @endif
                    @if ($sub && $sub->status !== 'disabled')
                        <button type="button" wire:click="openDeliver({{ $sub->id }})" @click="deliver = true" class="ns-cta ns-cta--pill ns-cta--sm"><x-nx.icon name="send" /> Deliver eSIM</button>
                    @endif
                    @if ($sub && $sub->isActive())
                        <button type="button" wire:click="disableEsim({{ $sub->id }})" wire:confirm="Disable this client's eSIM?" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Disable</button>
                    @endif
                    @if ($client->whatsapp)
                        @php $msg = "Hi {$client->name}, your eSIM subscription".($days !== null && $days >= 0 ? " is due in {$days} day(s)" : ' has expired').". Please renew with me to stay connected."; @endphp
                        <a href="{{ $client->whatsappLink($msg) }}" target="_blank" rel="noopener" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><x-nx.icon name="message-circle" /> WhatsApp</a>
                    @endif
                    <button type="button" wire:click="openInvoice({{ $client->id }})" @click="invoice = true" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Invoice</button>
                    <button type="button" wire:click="edit({{ $client->id }})" @click="sheet = true" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Edit</button>
                </div>
            </div>
        @empty
            <x-nx.empty text="No clients yet. Add your first client to start managing eSIMs for them." />
        @endforelse
    </div>
    <div style="margin-top:16px">{{ $clients->links() }}</div>

    {{-- Add / edit client sheet --}}
    <x-nx.alpine-sheet show="sheet" :title="$editingId ? 'Edit client' : 'New client'" icon="user">
        <div class="ns-pg__form">
            <label class="ns-pg__lbl" for="mc-name">Name</label>
            <input id="mc-name" type="text" wire:model="name" class="ns-input">
            @error('name')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
            <div class="ns-pg__two">
                <div><label class="ns-pg__lbl" for="mc-wa">WhatsApp</label><input id="mc-wa" type="text" wire:model="whatsapp" placeholder="+234801…" class="ns-input">@error('whatsapp')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror</div>
                <div><label class="ns-pg__lbl" for="mc-mail">Email</label><input id="mc-mail" type="email" wire:model="email" class="ns-input">@error('email')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror</div>
            </div>
            <div class="ns-pg__two">
                <div><label class="ns-pg__lbl" for="mc-dev">Device</label><input id="mc-dev" type="text" wire:model="device" placeholder="iPhone 15" class="ns-input"></div>
                <div><label class="ns-pg__lbl" for="mc-os">Device OS</label>
                    <select id="mc-os" wire:model="device_os" class="ns-input"><option value="">Unknown</option><option value="ios">iOS</option><option value="android">Android</option><option value="other">Other</option></select></div>
            </div>
            <label class="ns-pg__lbl" for="mc-notes">Notes</label>
            <textarea id="mc-notes" wire:model="notes" rows="2" class="ns-input"></textarea>
            @if ($error)<p class="ns-pg__err" role="alert">{{ $error }}</p>@endif
        </div>
        <x-slot:foot>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="ns-cta">
                <span wire:loading.remove wire:target="save" class="ns-cta__label">{{ $editingId ? 'Save changes' : 'Add client' }}</span>
                <span wire:loading wire:target="save" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </x-slot:foot>
    </x-nx.alpine-sheet>

    {{-- Assign eSIM sheet --}}
    <x-nx.alpine-sheet show="assign" title="Assign an eSIM" subtitle="Charged from your merchant wallet at your reseller price." icon="cards">
        <div class="ns-pg__stack">
            {{-- Type toggle --}}
            <div class="ns-pg__choices ns-pg__choices--2">
                <button type="button" wire:click="$set('assignType', 'data')" class="ns-pg__choice {{ $assignType === 'data' ? 'is-on' : '' }}" aria-pressed="{{ $assignType === 'data' ? 'true' : 'false' }}">Naara Data</button>
                <button type="button" wire:click="$set('assignType', 'connect')" class="ns-pg__choice {{ $assignType === 'connect' ? 'is-on' : '' }}" aria-pressed="{{ $assignType === 'connect' ? 'true' : 'false' }}">Naara Connect</button>
            </div>

            {{-- Search + country filter: results are the real, live footprint for the active line, so they are always assignable. --}}
            <div class="ns-pg__row" style="flex-wrap:nowrap">
                <label class="ns-search is-grow" style="margin:0;height:46px"><x-nx.icon name="search" /><input type="text" wire:model.live.debounce.300ms="assignSearch" placeholder="Search plans…" aria-label="Search plans"></label>
                <select wire:model.live="assignCountry" class="ns-input" style="width:9rem;flex:none;height:46px" aria-label="Country">
                    <option value="">All countries</option>
                    @foreach ($assignCountryOptions as $c)<option value="{{ $c['code'] }}">{{ $c['name'] }} ({{ $c['count'] }})</option>@endforeach
                </select>
            </div>

            <select wire:model="assignPlanId" size="5" class="ns-input ns-input--list" aria-label="Plan">
                @forelse (($assignType === 'connect' ? $connectPlans : $dataPlans) as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                @empty
                    <option value="" disabled>No plans match. Try a different search or country.</option>
                @endforelse
            </select>
            @if ($assignType === 'connect' && $connectPlans->isEmpty() && $assignSearch === '' && $assignCountry === '')<p class="ns-pg__hint">No Naara Connect plans are live yet.</p>@endif

            <label class="ns-pg__check"><input type="checkbox" wire:model="assignForce"> Device confirmed eSIM-compatible (override the check)</label>
            @if ($error)<p class="ns-pg__err" role="alert" style="margin-top:0">{{ $error }}</p>@endif
        </div>
        <x-slot:foot>
            <button type="button" wire:click="assign" @click="if (! $wire.error) assign = false" wire:loading.attr="disabled" wire:target="assign" @disabled(! $assignPlanId) class="ns-cta">
                <span wire:loading.remove wire:target="assign" class="ns-cta__label"><x-nx.icon name="check" /> Buy &amp; assign</span>
                <span wire:loading wire:target="assign" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Provisioning…</span>
            </button>
        </x-slot:foot>
    </x-nx.alpine-sheet>

    {{-- Invoice sheet --}}
    <x-nx.alpine-sheet show="invoice" title="Send an invoice" subtitle="Your own price and brand name, forwarded to the client on WhatsApp. This is between you and your client." icon="receipt">
        <div class="ns-pg__form">
            <label class="ns-pg__lbl" for="mc-idesc">Service description</label>
            <input id="mc-idesc" type="text" wire:model="invoiceDesc" placeholder="1-month eSIM renewal" class="ns-input">
            @error('invoiceDesc')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
            <label class="ns-pg__lbl" for="mc-iamt">Amount ($)</label>
            <input id="mc-iamt" type="number" step="0.01" wire:model="invoiceAmount" class="ns-input">
            @error('invoiceAmount')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
            <button type="button" wire:click="generateInvoice" wire:loading.attr="disabled" wire:target="generateInvoice" class="ns-cta" style="margin-top:12px">Generate invoice</button>
            @if ($invoiceText)
                <pre class="ns-pg__pre" style="margin-top:12px">{{ $invoiceText }}</pre>
                @if ($invoiceLink)
                    <a href="{{ $invoiceLink }}" target="_blank" rel="noopener" class="ns-cta" style="margin-top:8px"><x-nx.icon name="message-circle" /> Forward on WhatsApp</a>
                @else
                    <p class="ns-pg__note">Add a WhatsApp number to this client to forward it directly.</p>
                @endif
            @endif
            <a href="{{ route('merchant.invoices', ['client' => $invoiceClientId]) }}" wire:navigate class="ns-pg__act ns-pg__act--link" style="justify-content:center;margin-top:12px">Prefer to track paid/unpaid status? Create a tracked invoice instead →</a>
        </div>
    </x-nx.alpine-sheet>

    {{-- Deliver eSIM sheet: QR + activation code + status, forwardable to the client by email / WhatsApp / both. --}}
    <x-nx.alpine-sheet show="deliver" title="Deliver eSIM" :subtitle="$deliverSub ? (($deliverSub->client?->name).' · '.($deliverSub->plan?->name ?? 'eSIM plan')) : null" icon="send">
        @if ($deliverSub)
            @php $order = $deliverSub->order; $client = $deliverSub->client; $ready = $order && $order->isDeliverable(); @endphp
            @if (! $ready)
                <div class="ns-pg__warnbox" style="flex-direction:column;text-align:center;cursor:default">
                    <x-ui.spinner class="h-5 w-5" />
                    <b>Still provisioning</b><small>The activation code isn't ready yet. Check back in a moment.</small>
                </div>
            @else
                {{-- QR + code (what the client scans / taps) --}}
                <div class="ns-pg__card ns-pg__card--tight ns-ring" style="margin-top:0" x-data="{ copied: false }">
                    <img src="{{ route('esim.qr', $order) }}" alt="eSIM QR code" width="176" height="176" class="ns-pg__qr">
                    @if ($order->lpa_string)
                        <span class="ns-pg__lbl" style="margin-top:14px">Activation code</span>
                        <div class="ns-pg__row" style="flex-wrap:nowrap;align-items:stretch">
                            <code class="ns-pg__code" style="flex:1;min-width:0;word-break:break-all;white-space:normal">{{ $order->lpa_string }}</code>
                            <button type="button" @click="navigator.clipboard.writeText(@js($order->lpa_string)); copied = true; setTimeout(() => copied = false, 1500)" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" style="width:auto;padding:0 12px" aria-label="Copy activation code"><x-nx.icon name="file" /></button>
                        </div>
                        <p class="ns-pg__hint" x-show="copied" x-cloak>Copied.</p>
                    @endif
                    @if ($order->iccid)<p class="ns-pg__hint">ICCID: <span class="ns-pg__mono">{{ $order->iccid }}</span></p>@endif
                </div>

                {{-- Channel choice --}}
                <span class="ns-pg__lbl" style="margin-top:16px">Send it to your client</span>
                @php $hasEmail = filled($client?->email); $hasWa = filled($client?->whatsapp); @endphp
                <div class="ns-pg__choices">
                    <button type="button" wire:click="$set('deliverChannel', 'email')" @disabled(! $hasEmail) class="ns-pg__choice {{ $deliverChannel === 'email' ? 'is-on' : '' }}">Email</button>
                    <button type="button" wire:click="$set('deliverChannel', 'whatsapp')" @disabled(! $hasWa) class="ns-pg__choice {{ $deliverChannel === 'whatsapp' ? 'is-on' : '' }}">WhatsApp</button>
                    <button type="button" wire:click="$set('deliverChannel', 'both')" @disabled(! ($hasEmail && $hasWa)) class="ns-pg__choice {{ $deliverChannel === 'both' ? 'is-on' : '' }}">Both</button>
                </div>
                @unless ($hasEmail && $hasWa)
                    <p class="ns-pg__note">Add {{ ! $hasEmail ? 'an email' : '' }}{{ ! $hasEmail && ! $hasWa ? ' and ' : '' }}{{ ! $hasWa ? 'a WhatsApp number' : '' }} to this client to unlock every channel.</p>
                @endunless
                @if ($error)<p class="ns-pg__err" role="alert">{{ $error }}</p>@endif

                <button type="button" wire:click="deliver" wire:loading.attr="disabled" wire:target="deliver" class="ns-cta" style="margin-top:16px">
                    <span wire:loading.remove wire:target="deliver" class="ns-cta__label"><x-nx.icon name="send" /> Send eSIM</span>
                    <span wire:loading wire:target="deliver" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Sending…</span>
                </button>
                @if ($deliverWaLink)
                    <a href="{{ $deliverWaLink }}" target="_blank" rel="noopener" class="ns-cta ns-cta--ghost" style="margin-top:8px"><x-nx.icon name="message-circle" /> Open WhatsApp to send</a>
                @endif
                <p class="ns-pg__hint" style="margin-top:12px">Email includes the scannable QR; WhatsApp includes the tap-to-install code. Both carry the manual install steps.</p>
            @endif
        @endif
    </x-nx.alpine-sheet>

    {{-- Auto-renew reserve sheet: pre-fund N renewal cycles up front (or "for life", a rolling single earmark). --}}
    <x-nx.alpine-sheet show="reserve" title="Lock auto-renewal" :subtitle="$reserveSub ? (($reserveSub->client?->name).' · $'.number_format((float) $reserveSub->renewal_price, 2).' per renewal cycle') : null" icon="cards">
        @if ($reserveSub)
            @php $price = (float) $reserveSub->renewal_price; @endphp
            <p class="ns-pg__sub">We set the funds aside now so the line renews itself. A lot of clients keep the same eSIM for years.</p>

            {{-- For-life toggle --}}
            <label class="ns-pg__warnbox">
                <span style="min-width:0"><b>Keep it for life</b><small>Reserves one cycle and tops it back up after every renewal. Renews forever while your wallet has funds.</small></span>
                <input type="checkbox" wire:model.live="reserveIndefinite">
            </label>

            <div x-show="! $wire.reserveIndefinite" style="margin-top:16px">
                <span class="ns-pg__lbl">How many cycles to pre-fund?</span>
                <div class="ns-pg__chips">
                    @foreach ([2, 3, 6, 12, 24] as $n)
                        <button type="button" wire:click="$set('reserveCycles', {{ $n }})" class="ns-pg__chip {{ $reserveCycles === $n ? 'is-on' : '' }}" aria-pressed="{{ $reserveCycles === $n ? 'true' : 'false' }}">{{ $n }}</button>
                    @endforeach
                </div>
                <div class="ns-pg__row" style="margin-top:12px;align-items:center">
                    <label class="ns-pg__lbl" style="margin:0" for="mc-cycles">Cycles</label>
                    <input id="mc-cycles" type="number" min="1" max="{{ $maxReserveCycles }}" wire:model.live="reserveCycles" class="ns-input" style="width:6.5rem">
                    <span class="ns-small">up to {{ $maxReserveCycles }}; beyond that, use "for life".</span>
                </div>
                @error('reserveCycles')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
            </div>

            {{-- Live cost preview --}}
            <div class="ns-pg__kv" style="margin-top:16px;display:block" x-data="{ price: {{ $price }} }">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                    <span x-text="$wire.reserveIndefinite ? 'Reserved now (rolling)' : 'Reserved now'"></span>
                    <b class="is-warn" style="font-size:19px" x-text="'$' + ($wire.reserveIndefinite ? price : price * Math.max(1, Number($wire.reserveCycles || 0))).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></b>
                </div>
                <p class="ns-pg__hint" x-show="! $wire.reserveIndefinite" x-cloak><span x-text="Number($wire.reserveCycles || 0)"></span> renewal cycle(s) set aside from your spendable balance.</p>
            </div>

            <p class="ns-pg__hint" style="margin-top:12px">Reserved funds stay earmarked and can only be freed by disabling the eSIM or a renewal that fails to provision. Real charges always run at renewal time.</p>

            <button type="button" wire:click="enableAutoRenew" wire:loading.attr="disabled" wire:target="enableAutoRenew" class="ns-cta" style="margin-top:16px">
                <span wire:loading.remove wire:target="enableAutoRenew" class="ns-cta__label"><x-nx.icon name="refresh" /> Lock auto-renewal</span>
                <span wire:loading wire:target="enableAutoRenew" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Reserving…</span>
            </button>
        @endif
    </x-nx.alpine-sheet>
</x-nx.page>
</div>
