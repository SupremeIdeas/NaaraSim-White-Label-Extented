<div class="mx-auto max-w-2xl px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Receipts</h1>
        <p class="text-sm text-slate-500 dark:text-slate-300">Every purchase, for your records. Save, re-view, or resend any receipt to your email.</p>
    </div>

    @php($fmt = app(\App\Services\Pricing\CurrencyService::class))
    @php($local = \App\Support\LocaleCurrency::resolve(auth()->user()))

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-[#16233d]">
        @forelse ($receipts as $r)
            <div wire:key="rcpt-{{ $r->id }}" class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 dark:border-white/5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon name="file-text" class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $r->description ?: 'Purchase' }}</p>
                    <p class="truncate text-xs text-slate-400">{{ $r->created_at->format('d M Y, H:i') }} · Ref {{ $r->reference ?: 'TX-'.$r->id }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-sm font-bold text-slate-900 dark:text-white">
                        @if (strtoupper($r->currency) === 'USD')
                            ${{ number_format(abs($r->amount), 2) }}
                        @else
                            {{ strtoupper($r->currency) }} {{ number_format(abs($r->amount), 2) }}
                        @endif
                    </p>
                    @if (strtoupper($r->currency) === 'USD' && $local !== 'USD')
                        <p class="text-[11px] text-slate-400">{{ $fmt->format(abs($r->amount), $local) }}</p>
                    @endif
                    <button wire:click="resend({{ $r->id }})" wire:loading.attr="disabled" wire:target="resend({{ $r->id }})"
                            class="mt-0.5 text-[11px] font-semibold text-primary hover:underline dark:text-teal-300">Email me</button>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">No purchases yet — your receipts will appear here.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $receipts->links() }}</div>
</div>
