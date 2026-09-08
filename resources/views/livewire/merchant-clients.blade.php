<div class="mx-auto max-w-2xl" x-data="{ sheet: false, assign: false, invoice: false, deliver: false, reserve: false }"
     @close-client-sheet.window="sheet = false"
     @close-reserve-sheet.window="reserve = false">
    {{-- Header + wallet --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Clients</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Manage eSIMs for people who don't have an account.</p>
        </div>
        <button type="button" @click="$wire.newClient(); sheet = true" class="flex items-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark">
            <x-icon name="plus" class="h-4 w-4" /> Add client
        </button>
    </div>

    {{-- Wallet strip: spendable vs reserved (locked for auto-renewals) --}}
    <div class="mb-5 flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 nx-glass-tile px-4 py-3 dark:border-white/10">
        <div><p class="text-[11px] uppercase tracking-wide text-slate-400">Spendable</p><p class="text-lg font-bold text-slate-900 dark:text-white">${{ number_format($spendableUsd, 2) }}</p></div>
        <div><p class="text-[11px] uppercase tracking-wide text-slate-400">Reserved</p><p class="text-lg font-bold text-amber-600 dark:text-amber-400">${{ number_format($reservedUsd, 2) }}</p></div>
        <div class="ml-auto text-right"><p class="text-[11px] uppercase tracking-wide text-slate-400">Wallet</p><p class="text-sm font-semibold text-slate-500 dark:text-slate-300">${{ number_format($walletUsd, 2) }}</p></div>
    </div>

    {{-- Search --}}
    <div class="relative mb-5">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, email, WhatsApp, device…"
               class="w-full rounded-full border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-4 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
    </div>

    {{-- Client list --}}
    <div class="space-y-2.5">
        @forelse ($clients as $client)
            @php $sub = $client->subscriptions->first(); $days = $sub?->daysLeft(); @endphp
            <div wire:key="mc-{{ $client->id }}" class="rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10 {{ $client->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $client->name }}</p>
                        <p class="text-xs text-slate-400">
                            @if ($client->device){{ $client->device }} · @endif{{ $client->esim_orders_count }} eSIM{{ $client->esim_orders_count === 1 ? '' : 's' }}@if ($client->email) · {{ $client->email }}@endif
                        </p>
                    </div>
                    @unless ($client->is_active)<span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-white/10 dark:text-slate-300">Inactive</span>@endunless
                </div>

                {{-- Current subscription status + countdown --}}
                @if ($sub)
                    @php
                        $tone = match ($sub->status) {
                            'active' => $sub->isDueSoon() ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                            'expired' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
                            default => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
                        };
                        $countdown = is_null($days) ? '' : ($days < 0 ? 'expired '.abs($days).'d ago' : ($days === 0 ? 'expires today' : $days.'d left'));
                    @endphp
                    <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 dark:bg-white/5">
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $tone }}">{{ $sub->esim_type === 'connect' ? 'Naara Connect' : 'Naara Data' }}</span>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-300">{{ ucfirst($sub->status) }}@if ($countdown) · {{ $countdown }}@endif</span>
                        @if ($sub->auto_renew)
                            <span class="ml-auto inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                <x-icon name="refresh" class="h-3 w-3" />
                                @if ($sub->renew_indefinitely) Auto-renew · for life
                                @elseif ((int) $sub->reserved_cycles > 1) Auto-renew · {{ (int) $sub->reserved_cycles }} cycles reserved
                                @else Auto-renew locked @endif
                            </span>
                        @endif
                    </div>
                @endif

                {{-- Actions --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="openAssign({{ $client->id }})" @click="assign = true" @disabled(! $client->is_active)
                            class="rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/20 disabled:opacity-50 dark:bg-primary/20 dark:text-teal-300"><x-icon name="plus" class="mr-0.5 inline h-3.5 w-3.5" /> {{ $sub && $sub->status !== 'disabled' ? 'New / renew eSIM' : 'Assign eSIM' }}</button>

                    @if ($sub && $sub->isActive() && ! $sub->auto_renew)
                        <button type="button" wire:click="openReserve({{ $sub->id }})" @click="reserve = true"
                                class="rounded-xl border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-50 dark:border-amber-500/30 dark:text-amber-300"><x-icon name="refresh" class="mr-0.5 inline h-3.5 w-3.5" /> Auto-renew</button>
                    @endif
                    @if ($sub && $sub->status !== 'disabled')
                        <button type="button" wire:click="openDeliver({{ $sub->id }})" @click="deliver = true"
                                class="rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-dark"><x-icon name="send" class="mr-0.5 inline h-3.5 w-3.5" /> Deliver eSIM</button>
                    @endif
                    @if ($sub && $sub->isActive())
                        <button type="button" wire:click="disableEsim({{ $sub->id }})" wire:confirm="Disable this client's eSIM?"
                                class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-400">Disable</button>
                    @endif

                    @if ($client->whatsapp)
                        @php $msg = "Hi {$client->name}, your eSIM subscription".($days !== null && $days >= 0 ? " is due in {$days} day(s)" : ' has expired').". Please renew with me to stay connected."; @endphp
                        <a href="{{ $client->whatsappLink($msg) }}" target="_blank" rel="noopener"
                           class="rounded-xl border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300"><x-icon name="message-circle" class="mr-0.5 inline h-3.5 w-3.5" /> WhatsApp</a>
                    @endif
                    <button type="button" wire:click="openInvoice({{ $client->id }})" @click="invoice = true" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300">Invoice</button>
                    <button type="button" wire:click="edit({{ $client->id }})" @click="sheet = true" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300">Edit</button>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-400 dark:border-white/10">No clients yet. Add your first client to start managing eSIMs for them.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $clients->links() }}</div>

    {{-- Add / edit client sheet --}}
    <div x-show="sheet" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="sheet = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60" @click="sheet = false"></div>
        <div x-show="sheet" x-transition class="relative w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>
            <h2 class="mb-3 text-base font-bold text-slate-900 dark:text-white">{{ $editingId ? 'Edit client' : 'New client' }}</h2>
            <div class="space-y-3">
                <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label><input type="text" wire:model="name" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">@error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">WhatsApp</label><input type="text" wire:model="whatsapp" placeholder="+234801…" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">@error('whatsapp')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email</label><input type="email" wire:model="email" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">@error('email')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Device</label><input type="text" wire:model="device" placeholder="iPhone 15" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Device OS</label>
                        <select wire:model="device_os" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                            <option value="">Unknown</option><option value="ios">iOS</option><option value="android">Android</option><option value="other">Other</option>
                        </select></div>
                </div>
                <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Notes</label><textarea wire:model="notes" rows="2" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100"></textarea></div>
                @if ($error)<p class="text-xs text-red-600">{{ $error }}</p>@endif
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="w-full rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">{{ $editingId ? 'Save changes' : 'Add client' }}</button>
            </div>
        </div>
    </div>

    {{-- Assign eSIM sheet --}}
    <div x-show="assign" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="assign = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60" @click="assign = false"></div>
        <div x-show="assign" x-transition class="relative w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>
            <h2 class="mb-1 text-base font-bold text-slate-900 dark:text-white">Assign an eSIM</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Charged from your merchant wallet at your reseller price.</p>

            {{-- Type toggle --}}
            <div class="mb-3 flex gap-1.5 rounded-full bg-slate-100 p-1 dark:bg-white/5">
                <button type="button" wire:click="$set('assignType', 'data')" @class(['flex-1 rounded-full py-1.5 text-xs font-semibold transition', 'bg-white text-primary shadow-sm dark:bg-white/15 dark:text-teal-300' => $assignType === 'data', 'text-slate-500' => $assignType !== 'data'])>Naara Data</button>
                <button type="button" wire:click="$set('assignType', 'connect')" @class(['flex-1 rounded-full py-1.5 text-xs font-semibold transition', 'bg-white text-primary shadow-sm dark:bg-white/15 dark:text-teal-300' => $assignType === 'connect', 'text-slate-500' => $assignType !== 'connect'])>Naara Connect</button>
            </div>

            {{-- Search + country filter (owner request): the flat 200-name
                 dropdown made finding a specific country's plan slow. Search
                 by name; the country list is the real, live footprint for
                 the active line (data vs connect), so results are always
                 exactly what's actually assignable. --}}
            <div class="mb-2 flex gap-2">
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    <input type="text" wire:model.live.debounce.300ms="assignSearch" placeholder="Search plans…"
                           class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-8 pr-2 text-xs dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                </div>
                <select wire:model.live="assignCountry"
                        class="w-32 shrink-0 rounded-xl border border-slate-300 bg-white px-2 text-xs dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                    <option value="">All countries</option>
                    @foreach ($assignCountryOptions as $c)
                        <option value="{{ $c['code'] }}">{{ $c['name'] }} ({{ $c['count'] }})</option>
                    @endforeach
                </select>
            </div>

            <select wire:model="assignPlanId" size="5" class="mb-3 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                @forelse (($assignType === 'connect' ? $connectPlans : $dataPlans) as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                @empty
                    <option value="" disabled>No plans match — try a different search or country.</option>
                @endforelse
            </select>
            @if ($assignType === 'connect' && $connectPlans->isEmpty() && $assignSearch === '' && $assignCountry === '')<p class="mb-2 text-xs text-slate-400">No Naara Connect plans are live yet.</p>@endif

            <label class="mb-3 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                <input type="checkbox" wire:model="assignForce" class="rounded border-slate-300 text-primary focus:ring-primary">
                Device confirmed eSIM-compatible (override the check)
            </label>

            @if ($error)<p class="mb-2 text-xs text-red-600">{{ $error }}</p>@endif
            <button type="button" wire:click="assign" @click="if (! $wire.error) assign = false" wire:loading.attr="disabled" wire:target="assign" @disabled(! $assignPlanId)
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-50">
                <span wire:loading.remove wire:target="assign"><x-icon name="check" class="mr-1 inline h-4 w-4" /> Buy &amp; assign</span>
                <span wire:loading wire:target="assign" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Provisioning…</span>
            </button>
        </div>
    </div>

    {{-- Invoice sheet --}}
    <div x-show="invoice" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="invoice = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60" @click="invoice = false"></div>
        <div x-show="invoice" x-transition class="relative w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>
            <h2 class="mb-1 text-base font-bold text-slate-900 dark:text-white">Send an invoice</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Your own price + your brand name. Forwarded to the client on WhatsApp — this is between you and your client.</p>
            <div class="space-y-3">
                <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Service description</label><input type="text" wire:model="invoiceDesc" placeholder="1-month eSIM renewal" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">@error('invoiceDesc')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</div>
                <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Amount ($)</label><input type="number" step="0.01" wire:model="invoiceAmount" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">@error('invoiceAmount')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</div>
                <button type="button" wire:click="generateInvoice" class="w-full rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark">Generate invoice</button>
                @if ($invoiceText)
                    <pre class="whitespace-pre-wrap rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $invoiceText }}</pre>
                    @if ($invoiceLink)
                        <a href="{{ $invoiceLink }}" target="_blank" rel="noopener" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700"><x-icon name="message-circle" class="h-4 w-4" /> Forward on WhatsApp</a>
                    @else
                        <p class="text-xs text-amber-600 dark:text-amber-400">Add a WhatsApp number to this client to forward it directly.</p>
                    @endif
                @endif
                <a href="{{ route('merchant.invoices', ['client' => $invoiceClientId]) }}" wire:navigate class="block text-center text-xs font-semibold text-primary hover:underline dark:text-teal-300">Prefer to track paid/unpaid status? Create a tracked invoice instead →</a>
            </div>
        </div>
    </div>

    {{-- Deliver eSIM sheet — QR + activation code + status, forwardable to the
         client by email / WhatsApp / both. --}}
    <div x-show="deliver" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="deliver = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60" @click="deliver = false"></div>
        <div x-show="deliver" x-transition class="relative max-h-[92vh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>
            @if ($deliverSub)
                @php $order = $deliverSub->order; $client = $deliverSub->client; $ready = $order && $order->isDeliverable(); @endphp
                <h2 class="mb-1 text-base font-bold text-slate-900 dark:text-white">Deliver eSIM</h2>
                <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">{{ $client?->name }} · {{ $deliverSub->plan?->name ?? 'eSIM plan' }}</p>

                @if (! $ready)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-center text-sm text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
                        <x-ui.spinner class="mx-auto mb-2 h-5 w-5" />
                        Still provisioning — the activation code isn't ready yet. Check back in a moment.
                    </div>
                @else
                    {{-- QR + code (what the client scans / taps) --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5" x-data="{ copied: false }">
                        <div class="flex justify-center">
                            <img src="{{ route('esim.qr', $order) }}" alt="eSIM QR code" width="176" height="176" class="h-44 w-44 rounded-xl border border-slate-200 bg-white p-1 dark:border-white/10">
                        </div>
                        @if ($order->lpa_string)
                            <p class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Activation code</p>
                            <div class="mt-1 flex items-center gap-2">
                                <code class="min-w-0 flex-1 break-all rounded-lg bg-slate-100 px-2 py-1.5 font-mono text-[11px] text-slate-800 dark:bg-[#243352] dark:text-slate-200">{{ $order->lpa_string }}</code>
                                <button type="button" @click="navigator.clipboard.writeText(@js($order->lpa_string)); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="shrink-0 rounded-lg border border-slate-300 p-1.5 text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5" aria-label="Copy activation code"><x-icon name="copy" class="h-4 w-4" /></button>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400" x-show="copied" x-cloak>Copied.</p>
                        @endif
                        @if ($order->iccid)<p class="mt-2 text-[11px] text-slate-400">ICCID: <span class="font-mono">{{ $order->iccid }}</span></p>@endif
                    </div>

                    {{-- Channel choice --}}
                    <p class="mb-2 mt-4 text-xs font-semibold text-slate-600 dark:text-slate-300">Send it to your client</p>
                    <div class="grid grid-cols-3 gap-2">
                        @php $hasEmail = filled($client?->email); $hasWa = filled($client?->whatsapp); @endphp
                        <button type="button" wire:click="$set('deliverChannel', 'email')" @disabled(! $hasEmail)
                                @class(['rounded-xl border py-2 text-xs font-semibold transition disabled:opacity-40', 'border-primary bg-primary/10 text-primary dark:text-teal-300' => $deliverChannel === 'email', 'border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-300' => $deliverChannel !== 'email'])>Email</button>
                        <button type="button" wire:click="$set('deliverChannel', 'whatsapp')" @disabled(! $hasWa)
                                @class(['rounded-xl border py-2 text-xs font-semibold transition disabled:opacity-40', 'border-primary bg-primary/10 text-primary dark:text-teal-300' => $deliverChannel === 'whatsapp', 'border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-300' => $deliverChannel !== 'whatsapp'])>WhatsApp</button>
                        <button type="button" wire:click="$set('deliverChannel', 'both')" @disabled(! ($hasEmail && $hasWa))
                                @class(['rounded-xl border py-2 text-xs font-semibold transition disabled:opacity-40', 'border-primary bg-primary/10 text-primary dark:text-teal-300' => $deliverChannel === 'both', 'border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-300' => $deliverChannel !== 'both'])>Both</button>
                    </div>
                    @unless ($hasEmail && $hasWa)
                        <p class="mt-2 text-[11px] text-amber-600 dark:text-amber-400">Add {{ ! $hasEmail ? 'an email' : '' }}{{ ! $hasEmail && ! $hasWa ? ' and ' : '' }}{{ ! $hasWa ? 'a WhatsApp number' : '' }} to this client to unlock every channel.</p>
                    @endunless

                    @if ($error)<p class="mt-3 text-xs text-red-600">{{ $error }}</p>@endif

                    <button type="button" wire:click="deliver" wire:loading.attr="disabled" wire:target="deliver"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="deliver"><x-icon name="send" class="mr-1 inline h-4 w-4" /> Send eSIM</span>
                        <span wire:loading wire:target="deliver" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Sending…</span>
                    </button>

                    @if ($deliverWaLink)
                        <a href="{{ $deliverWaLink }}" target="_blank" rel="noopener"
                           class="mt-2 flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700"><x-icon name="message-circle" class="h-4 w-4" /> Open WhatsApp to send</a>
                    @endif

                    <p class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">Email includes the scannable QR; WhatsApp includes the tap-to-install code. Both carry the manual install steps.</p>
                @endif
            @endif
        </div>
    </div>

    {{-- Auto-renew reserve sheet — pre-fund N renewal cycles up front (or "for
         life", a rolling single earmark). Keeps a client's line renewing
         hands-free for as long as the merchant chooses. --}}
    <div x-show="reserve" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="reserve = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60" @click="reserve = false"></div>
        <div x-show="reserve" x-transition class="relative w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>
            @if ($reserveSub)
                @php $price = (float) $reserveSub->renewal_price; @endphp
                <h2 class="mb-1 text-base font-bold text-slate-900 dark:text-white">Lock auto-renewal</h2>
                <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
                    {{ $reserveSub->client?->name }} · ${{ number_format($price, 2) }} per renewal cycle. We set the funds aside now so the line renews itself — a lot of clients keep the same eSIM for years.
                </p>

                {{-- For-life toggle --}}
                <label class="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-amber-800 dark:text-amber-300">Keep it for life</span>
                        <span class="block text-[11px] text-amber-700/80 dark:text-amber-300/70">Reserves one cycle and tops it back up after every renewal — renews forever while your wallet has funds.</span>
                    </span>
                    <input type="checkbox" wire:model.live="reserveIndefinite" class="h-5 w-5 shrink-0 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                </label>

                <div x-show="! $wire.reserveIndefinite">
                    <p class="mb-2 text-xs font-semibold text-slate-600 dark:text-slate-300">How many cycles to pre-fund?</p>
                    <div class="mb-3 flex flex-wrap gap-1.5">
                        @foreach ([2, 3, 6, 12, 24] as $n)
                            <button type="button" wire:click="$set('reserveCycles', {{ $n }})"
                                    @class(['rounded-full border px-3 py-1.5 text-xs font-semibold transition', 'border-primary bg-primary/10 text-primary dark:text-teal-300' => $reserveCycles === $n, 'border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-300' => $reserveCycles !== $n])>{{ $n }}</button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-slate-500 dark:text-slate-400">Cycles</label>
                        <input type="number" min="1" max="{{ $maxReserveCycles }}" wire:model.live="reserveCycles"
                               class="w-24 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                        <span class="text-[11px] text-slate-400">up to {{ $maxReserveCycles }} — beyond that, use “for life”.</span>
                    </div>
                    @error('reserveCycles')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Live cost preview --}}
                <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-white/5"
                     x-data="{ price: {{ $price }} }">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500 dark:text-slate-400" x-text="$wire.reserveIndefinite ? 'Reserved now (rolling)' : 'Reserved now'"></span>
                        <span class="text-lg font-bold text-amber-600 dark:text-amber-400"
                              x-text="'$' + ($wire.reserveIndefinite ? price : price * Math.max(1, Number($wire.reserveCycles || 0))).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500" x-show="! $wire.reserveIndefinite" x-cloak>
                        <span x-text="Number($wire.reserveCycles || 0)"></span> renewal cycle(s) set aside from your spendable balance.
                    </p>
                </div>

                <p class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">Reserved funds stay earmarked and can only be freed by disabling the eSIM or a renewal that fails to provision. Real charges always run at renewal time.</p>

                <button type="button" wire:click="enableAutoRenew" wire:loading.attr="disabled" wire:target="enableAutoRenew"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-amber-500 py-3 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60">
                    <span wire:loading.remove wire:target="enableAutoRenew"><x-icon name="refresh" class="mr-1 inline h-4 w-4" /> Lock auto-renewal</span>
                    <span wire:loading wire:target="enableAutoRenew" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Reserving…</span>
                </button>
            @endif
        </div>
    </div>
</div>
