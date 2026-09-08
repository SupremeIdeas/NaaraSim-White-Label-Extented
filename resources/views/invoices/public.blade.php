<x-layouts.app :title="'Invoice from '.($invoice->merchant->business_name ?: 'your provider')">
    @php
        $merchant = $invoice->merchant;
        $badge = match (true) {
            $invoice->status === \App\Models\MerchantInvoice::PAID => ['label' => 'Paid', 'class' => 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300'],
            $invoice->status === \App\Models\MerchantInvoice::VOID => ['label' => 'Void', 'class' => 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-400'],
            $invoice->isOverdue() => ['label' => 'Overdue', 'class' => 'bg-red-100 text-red-600 dark:bg-red-950/50 dark:text-red-300'],
            default => ['label' => 'Awaiting payment', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'],
        };
    @endphp
    <div class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 dark:border-white/10 dark:bg-[#16233d]">
            <div class="mb-5 flex items-center gap-3">
                @if ($merchant->logo_url)
                    <img src="{{ $merchant->logo_url }}" alt="{{ $merchant->business_name }}" class="h-11 w-11 rounded-xl object-cover">
                @else
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-lg font-bold text-primary dark:bg-primary/20 dark:text-teal-300">{{ mb_substr($merchant->business_name ?: 'N', 0, 1) }}</span>
                @endif
                <div class="min-w-0">
                    <p class="truncate text-base font-bold text-slate-900 dark:text-white">{{ $merchant->business_name ?: 'Invoice' }}</p>
                    <p class="text-xs text-slate-400">Invoice {{ $invoice->reference }}</p>
                </div>
                <span class="ml-auto shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
            </div>

            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-white/5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Amount due</p>
                <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">${{ number_format((float) $invoice->amount, 2) }}</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $invoice->description }}</p>
            </div>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-400">Billed to</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ $invoice->client->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Sent</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ $invoice->sent_at?->format('M j, Y') ?? '—' }}</dd></div>
                @if ($invoice->due_at)
                    <div class="flex justify-between"><dt class="text-slate-400">Due</dt><dd class="font-medium text-slate-700 dark:text-slate-200">{{ $invoice->due_at->format('M j, Y') }}</dd></div>
                @endif
                @if ($invoice->status === \App\Models\MerchantInvoice::PAID)
                    <div class="flex justify-between"><dt class="text-slate-400">Paid</dt><dd class="font-medium text-green-600 dark:text-green-400">{{ $invoice->paid_at->format('M j, Y') }}</dd></div>
                @endif
            </dl>

            @if ($invoice->status !== \App\Models\MerchantInvoice::PAID && $invoice->status !== \App\Models\MerchantInvoice::VOID)
                <p class="mt-5 rounded-xl bg-primary/5 px-3 py-2.5 text-xs text-slate-500 dark:bg-primary/10 dark:text-slate-400">
                    <x-icon name="info" class="mr-1 inline h-3.5 w-3.5" /> Pay {{ $merchant->business_name ?: 'your provider' }} directly using the method they agreed with you — this page does not process payment.
                </p>
                @if ($merchant->owner?->whatsapp_number ?? null)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $merchant->owner->whatsapp_number) }}" target="_blank" rel="noopener"
                       class="mt-3 flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        <x-icon name="message-circle" class="h-4 w-4" /> Message {{ $merchant->business_name ?: 'provider' }}
                    </a>
                @endif
            @endif
        </div>
        <p class="mt-4 text-center text-xs text-slate-400">Powered by NaaraSim</p>
    </div>
</x-layouts.app>
