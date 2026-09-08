<div class="mx-auto max-w-5xl" x-data="{ create: @entangle('showCreate') }">
    {{-- Header --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('merchant.clients') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-primary dark:text-slate-400">
                <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Clients
            </a>
            <h1 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">Invoices</h1>
        </div>
        <button type="button" wire:click="openCreate" @click="create = true" class="flex items-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark">
            <x-icon name="plus" class="h-4 w-4" /> Create invoice
        </button>
    </div>

    {{-- Stat row --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Overdue</p>
            <p class="mt-1 text-xl font-bold text-red-600 dark:text-red-400">${{ number_format($overdueTotal, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Due within 30 days</p>
            <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">${{ number_format($dueSoonTotal, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Avg. time to get paid</p>
            <p class="mt-1 text-xl font-bold text-slate-900 dark:text-white">{{ $avgDaysToPay !== null ? $avgDaysToPay.' days' : '—' }}</p>
        </div>
        <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 dark:border-primary/20 dark:bg-primary/10">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-primary dark:text-teal-300">Available for payout</p>
            <div class="mt-1 flex items-end justify-between gap-2">
                <p class="text-xl font-bold text-slate-900 dark:text-white">${{ number_format($payoutAvailable, 2) }}</p>
                <a href="{{ route('merchant.dashboard') }}" wire:navigate class="shrink-0 rounded-full bg-primary px-2.5 py-1 text-[11px] font-bold text-white transition hover:bg-primary-dark">Pay out</a>
            </div>
        </div>
    </div>

    {{-- Filters + search --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach (['all' => 'All', 'draft' => 'Draft', 'unpaid' => 'Unpaid', 'paid' => 'Paid'] as $key => $label)
            <button type="button" wire:click="$set('statusFilter', '{{ $key }}')"
                    class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition {{ $statusFilter === $key ? 'bg-primary text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-400 dark:hover:bg-white/10' }}">{{ $label }}</button>
        @endforeach
        <div class="relative ml-auto min-w-[10rem] flex-1 sm:flex-none">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search…"
                   class="w-full rounded-full border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_1.2fr]">
        {{-- List --}}
        <div class="space-y-2">
            @forelse ($invoices as $invoice)
                @php
                    $badge = match (true) {
                        $invoice->status === \App\Models\MerchantInvoice::PAID => ['label' => 'Paid', 'class' => 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300'],
                        $invoice->status === \App\Models\MerchantInvoice::VOID => ['label' => 'Void', 'class' => 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400'],
                        $invoice->status === \App\Models\MerchantInvoice::DRAFT => ['label' => 'Draft', 'class' => 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400'],
                        $invoice->isOverdue() => ['label' => 'Overdue', 'class' => 'bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-300'],
                        $invoice->viewed_at !== null => ['label' => 'Viewed', 'class' => 'bg-blue-100 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300'],
                        default => ['label' => 'Unsent', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'],
                    };
                @endphp
                <button type="button" wire:click="select({{ $invoice->id }})" wire:key="inv-{{ $invoice->id }}"
                        class="flex w-full items-center justify-between gap-3 rounded-2xl border p-3.5 text-left transition {{ $selected?->id === $invoice->id ? 'border-primary bg-primary/5 dark:bg-primary/10' : 'border-slate-200 hover:border-primary/30 dark:border-white/10' }}">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $invoice->client->name }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ $invoice->reference }} @if ($invoice->due_at) · due {{ $invoice->due_at->format('M j') }} @endif</span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="block text-sm font-bold text-slate-900 dark:text-white">${{ number_format((float) $invoice->amount, 2) }}</span>
                        <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    </span>
                </button>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-400 dark:border-white/10">No invoices yet — create one for a client.</div>
            @endforelse
            <div class="mt-2">{{ $invoices->links() }}</div>
        </div>

        {{-- Detail panel --}}
        <div class="rounded-2xl border border-slate-200 nx-glass-tile p-5 dark:border-white/10">
            @if ($selected)
                @php
                    $badge = match (true) {
                        $selected->status === \App\Models\MerchantInvoice::PAID => ['label' => 'Paid', 'class' => 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300'],
                        $selected->status === \App\Models\MerchantInvoice::VOID => ['label' => 'Void', 'class' => 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400'],
                        $selected->status === \App\Models\MerchantInvoice::DRAFT => ['label' => 'Draft', 'class' => 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400'],
                        $selected->isOverdue() => ['label' => 'Overdue', 'class' => 'bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-300'],
                        $selected->viewed_at !== null => ['label' => 'Viewed', 'class' => 'bg-blue-100 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300'],
                        default => ['label' => 'Unsent', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'],
                    };
                @endphp
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs text-slate-400">{{ $selected->reference }}</p>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $selected->client->name }}</h2>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                </div>

                <div class="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-white/5">
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $selected->description }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">${{ number_format((float) $selected->amount, 2) }}</p>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    @if ($selected->due_at)<div class="flex justify-between"><dt class="text-slate-400">Due</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ $selected->due_at->format('M j, Y') }}</dd></div>@endif
                    @if ($selected->sent_at)<div class="flex justify-between"><dt class="text-slate-400">Sent</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ $selected->sent_at->format('M j, Y') }}</dd></div>@endif
                    @if ($selected->paid_at)<div class="flex justify-between"><dt class="text-slate-400">Paid</dt><dd class="font-medium text-green-600 dark:text-green-400">{{ $selected->paid_at->format('M j, Y') }}</dd></div>@endif
                </dl>

                @php($publicUrl = route('invoice.public', $selected->public_token))
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    @if ($selected->status === \App\Models\MerchantInvoice::DRAFT)
                        <button type="button" wire:click="send({{ $selected->id }})" class="rounded-xl bg-primary px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-primary-dark"><x-icon name="send" class="mr-1 inline h-3.5 w-3.5" /> Send</button>
                        <button type="button" wire:click="void({{ $selected->id }})" wire:confirm="Void this draft invoice?" class="rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-semibold text-slate-500 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-400">Void</button>
                    @elseif ($selected->status === \App\Models\MerchantInvoice::SENT)
                        @if ($selected->client->whatsapp)
                            <a href="{{ $selected->client->whatsappLink("Hi {$selected->client->name}, here's your invoice for {$selected->description} — \${$selected->amount}. View it here: {$publicUrl}") }}" target="_blank" rel="noopener"
                               class="rounded-xl border border-emerald-300 px-3.5 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300"><x-icon name="message-circle" class="mr-1 inline h-3.5 w-3.5" /> WhatsApp</a>
                        @endif
                        <button type="button" wire:click="markPaid({{ $selected->id }})" wire:confirm="Mark this invoice as paid?" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"><x-icon name="badge-check" class="mr-1 inline h-3.5 w-3.5" /> Mark paid</button>
                        <button type="button" wire:click="void({{ $selected->id }})" wire:confirm="Void this invoice?" class="rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-semibold text-slate-500 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-400">Void</button>
                    @endif
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="ml-auto inline-flex items-center gap-1 text-xs font-semibold text-slate-400 hover:text-primary"><x-icon name="link" class="h-3.5 w-3.5" /> Public link</a>
                </div>
            @else
                <div class="flex h-full min-h-[16rem] flex-col items-center justify-center text-center text-sm text-slate-400">
                    <x-icon name="file-text" class="mb-2 h-8 w-8" />
                    Select an invoice to see its details.
                </div>
            @endif
        </div>
    </div>

    {{-- Create-invoice sheet --}}
    <div x-show="create" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="create = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/60" @click="create = false"></div>
        <div x-show="create" x-transition class="relative w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            <h2 class="mb-1 text-base font-bold text-slate-900 dark:text-white">Create invoice</h2>
            <p class="mb-4 text-xs text-slate-400">Saved as a draft — send it when you're ready.</p>
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Client</label>
                    <select wire:model="clientId" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                        <option value="">Choose a client…</option>
                        @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('clientId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Description</label>
                    <input type="text" wire:model="description" placeholder="1-month eSIM renewal" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                    @error('description')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Amount ($)</label>
                        <input type="number" step="0.01" wire:model="amount" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                        @error('amount')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Due date</label>
                        <input type="date" wire:model="dueAt" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                    </div>
                </div>
                @if ($error)<p class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-950/40 dark:text-red-300">{{ $error }}</p>@endif
                <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                        class="w-full rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="create">Create invoice</span>
                    <span wire:loading wire:target="create" class="inline-flex items-center justify-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                </button>
            </div>
        </div>
    </div>
</div>
