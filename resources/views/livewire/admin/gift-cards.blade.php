<div class="mx-auto max-w-5xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Naara Gift</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        All {{ count($providers) }} registered providers run together, ranked Primary → Secondary → Tertiary → …
        — each fills the gaps the higher-ranked ones don't carry for a brand.
        The storefront only ever sells the primary provider per brand, and never sees cost.
        A provider stays "Coming Soon" until its keys are added — Tillo additionally needs its enterprise account set up first.
    </p>

    {{-- Providers side by side --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        @foreach ($providers as $p)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]" wire:key="prov-{{ $p['key'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $p['label'] }}</h2>
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary dark:text-teal-300">{{ $p['role'] }}</span>
                        </div>
                        <p class="mt-0.5 text-xs {{ $p['configured'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                            {{ $p['configured'] ? 'Keys configured · Active' : 'Add keys on Admin → API keys' }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" wire:click="preflight('{{ $p['key'] }}')" wire:loading.attr="disabled" wire:target="preflight('{{ $p['key'] }}')"
                                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-primary hover:text-primary disabled:opacity-60 dark:border-[#2D4060] dark:text-slate-300">
                            <span wire:loading.remove wire:target="preflight('{{ $p['key'] }}')">Test</span>
                            <span wire:loading wire:target="preflight('{{ $p['key'] }}')">Testing…</span>
                        </button>
                        <button type="button" wire:click="sync('{{ $p['key'] }}')" wire:loading.attr="disabled" wire:target="sync('{{ $p['key'] }}')"
                                class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                            <span wire:loading.remove wire:target="sync('{{ $p['key'] }}')">Sync now</span>
                            <span wire:loading wire:target="sync('{{ $p['key'] }}')">Syncing…</span>
                        </button>
                    </div>
                </div>

                @if (! empty($probe[$p['key']]))
                    @php $pr = $probe[$p['key']]; @endphp
                    <div class="mt-3 rounded-xl border p-3 text-xs {{ $pr['ok'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300' }}">
                        @if ($pr['ok'])
                            <span class="font-semibold">Connected.</span>
                            @if ($pr['balance'] !== null) Balance {{ $pr['currency'] ?: '' }} {{ number_format((float) $pr['balance'], 2) }}.@endif
                            @if ($pr['products'] !== null) {{ number_format($pr['products']) }} products available.@endif
                        @else
                            <span class="font-semibold">Failed.</span> {{ $pr['error'] }}
                        @endif
                    </div>
                @endif
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#243352]">
                        <dt class="text-[11px] uppercase tracking-wide text-slate-400">Products</dt>
                        <dd class="mt-0.5 font-bold text-slate-900 dark:text-slate-100">{{ number_format($p['total']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-[#243352]">
                        <dt class="text-[11px] uppercase tracking-wide text-slate-400">Live in store</dt>
                        <dd class="mt-0.5 font-bold text-slate-900 dark:text-slate-100">{{ number_format($p['live']) }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-[11px] text-slate-400">
                    @if ($p['sync'])
                        Last sync {{ \Illuminate\Support\Carbon::parse($p['sync']['at'])->diffForHumans() }} ·
                        <span class="{{ $p['sync']['ok'] ? 'text-emerald-500' : 'text-red-500' }}">{{ $p['sync']['ok'] ? 'OK' : 'Failed' }}</span>
                        @if (! $p['sync']['ok'] && $p['sync']['error'])<span class="text-red-400"> — {{ $p['sync']['error'] }}</span>@endif
                    @else
                        Never synced.
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    {{-- Review queue --}}
    <h2 class="mt-8 flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
        <x-icon name="shield" class="h-4 w-4" /> Manual review
        @if ($review->isNotEmpty())<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ $review->count() }}</span>@endif
    </h2>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">High-value purchases are held here — the buyer's wallet is already debited. Approve to deliver, reject to refund.</p>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">Buyer</th>
                    <th class="px-4 py-3">Brand</th>
                    <th class="px-4 py-3">Face</th>
                    <th class="px-4 py-3">Charged</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($review as $o)
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="rev-{{ $o->id }}">
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $o->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $o->brand_name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $o->currency }} {{ number_format((float) $o->face_value, 2) }}</td>
                        <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">${{ number_format((float) $o->price_charged, 2) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="approve({{ $o->id }})" wire:confirm="Approve and deliver this gift card?"
                                        class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Approve</button>
                                <button type="button" wire:click="reject({{ $o->id }})" wire:confirm="Reject and refund this order?"
                                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-600 dark:border-[#2D4060] dark:text-slate-300">Reject</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">Nothing awaiting review.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Fraud controls --}}
    <h2 class="mt-8 text-sm font-semibold text-slate-900 dark:text-slate-100">Fraud controls</h2>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Per-account limits. Purchases at or above the review threshold hold for manual approval.</p>
    <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="grid gap-4 sm:grid-cols-3">
            @php $labels = [
                'max_amount_24h' => ['Max spend / 24h (USD)', 'number', '0.01'],
                'max_count_24h' => ['Max orders / 24h', 'number', '1'],
                'max_amount_7d' => ['Max spend / 7 days (USD)', 'number', '0.01'],
                'cooloff_value' => ['New-account cool-off cap (USD)', 'number', '0.01'],
                'cooloff_hours' => ['Cool-off window (hours)', 'number', '1'],
                'review_threshold' => ['Manual-review threshold (USD)', 'number', '0.01'],
            ]; @endphp
            @foreach ($labels as $field => $meta)
                <div wire:key="fraud-{{ $field }}">
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ $meta[0] }}</label>
                    <input type="{{ $meta[1] }}" step="{{ $meta[2] }}" min="0" wire:model="{{ $field }}"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
                    @error($field)<p class="mt-1 text-[11px] text-red-500">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div class="mt-4">
            <button type="button" wire:click="saveFraud" wire:loading.attr="disabled" wire:target="saveFraud"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">Save controls</button>
        </div>
    </div>

    {{-- Catalogue --}}
    <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Catalogue</h2>
        <div class="flex items-center gap-2">
            <select wire:model.live="providerFilter" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                <option value="">All providers</option>
                @foreach ($providers as $p)
                    <option value="{{ $p['key'] }}">{{ $p['label'] }}</option>
                @endforeach
            </select>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search brand…"
                   class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
        </div>
    </div>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">Brand</th>
                    <th class="px-4 py-3">Provider</th>
                    <th class="px-4 py-3">Country</th>
                    <th class="px-4 py-3">Primary</th>
                    <th class="px-4 py-3 text-center">In store</th>
                    <th class="px-4 py-3 text-center">Featured</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($catalogue as $c)
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="cat-{{ $c->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if ($c->logo_url)
                                    <img src="{{ $c->logo_url }}" alt="" class="h-6 w-6 rounded object-contain" loading="lazy" />
                                @else
                                    <span class="flex h-6 w-6 items-center justify-center rounded bg-slate-100 text-[10px] font-bold text-slate-400 dark:bg-white/10">{{ mb_substr($c->brand_name, 0, 1) }}</span>
                                @endif
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $c->brand_name }}</span>
                                {{-- Add / replace a logo (for brands that synced without one). --}}
                                <label class="cursor-pointer text-[11px] font-semibold text-primary hover:underline dark:text-teal-300" title="Upload a logo">
                                    {{ $c->logo_url ? 'Replace' : 'Add logo' }}
                                    <input type="file" wire:model="logoUploads.{{ $c->id }}" accept="image/png,image/webp,image/svg+xml,image/jpeg" class="hidden" />
                                </label>
                                <span wire:loading wire:target="logoUploads.{{ $c->id }}" class="text-[11px] text-slate-400">Uploading…</span>
                            </div>
                            @error('logoUploads.'.$c->id)<p class="mt-1 text-[11px] text-red-500">{{ $message }}</p>@enderror
                        </td>
                        <td class="px-4 py-3 text-xs capitalize text-slate-500 dark:text-slate-400">{{ $c->provider }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $c->country ?: 'Global' }}</td>
                        <td class="px-4 py-3">
                            @if ($c->is_primary)
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary dark:text-teal-300">Primary</span>
                            @else
                                <span class="text-[11px] text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" wire:click="toggle({{ $c->id }}, 'admin_enabled')"
                                    class="inline-flex h-6 w-11 items-center rounded-full transition {{ $c->admin_enabled ? 'bg-primary' : 'bg-slate-300 dark:bg-[#2D4060]' }}">
                                <span class="ml-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition {{ $c->admin_enabled ? 'translate-x-5' : '' }}"></span>
                            </button>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" wire:click="toggle({{ $c->id }}, 'featured')"
                                    class="inline-flex items-center justify-center rounded-lg p-1.5 transition {{ $c->featured ? 'text-accent' : 'text-slate-300 hover:text-slate-400 dark:text-[#2D4060]' }}">
                                <x-icon name="star" class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No products yet — sync a provider above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $catalogue->links() }}</div>

    {{-- Recent orders --}}
    <div class="mt-8 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Recent orders</h2>
        <button type="button" wire:click="exportCsv"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-primary hover:text-primary dark:border-[#2D4060] dark:text-slate-300">
            <x-icon name="file-text" class="h-3.5 w-3.5" /> Export CSV
        </button>
    </div>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">Buyer</th>
                    <th class="px-4 py-3">Brand</th>
                    <th class="px-4 py-3">Charged</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">When</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $o)
                    @php $tone = match($o->status){ 'delivered' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'failed','refunded' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300', default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' }; @endphp
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="ord-{{ $o->id }}">
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $o->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $o->brand_name }}</td>
                        <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">${{ number_format((float) $o->price_charged, 2) }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize {{ $tone }}">{{ $o->status }}</span></td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ $o->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
