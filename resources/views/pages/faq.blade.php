<x-layouts.app title="FAQ — NaaraSim">
    <main class="mx-auto max-w-3xl px-4 py-12">
        <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Home
        </a>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">Frequently asked questions</h1>

        @php
            $faqs = [
                ['What does NaaraSim sell?', 'eSIM data plans for 190+ countries, plus virtual phone numbers and SMS verification numbers — data and numbers in one app.'],
                ['How fast can I get connected?', 'Purchase to connected in under three minutes. Buy a plan, scan the QR, and your data starts on first connection abroad.'],
                ['What happens if my code never arrives?', 'If no code arrives within '.\App\Jobs\PollSmsOtpJob::TIMEOUT_MINUTES.' minutes of ordering, your wallet is automatically refunded — no ticket required. See our <a href="'.route('refund-policy').'" class="font-semibold text-primary hover:underline">refund &amp; reliability policy</a> for the full details.'],
                ['Which currencies can I pay in?', 'You fund one wallet and see prices in both USD and NGN. Top up via card, bank transfer, USSD, or mobile money.'],
            ];
        @endphp

        <div class="mt-8 space-y-4">
            @foreach ($faqs as [$q, $a])
                <details class="group rounded-xl border border-slate-200 bg-white p-5 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                    <summary class="flex cursor-pointer items-center justify-between font-semibold text-slate-900 dark:text-slate-100">
                        {{ $q }}
                        <x-icon name="chevron-right" class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-90" />
                    </summary>
                    {{-- $a is hardcoded content above, not user input — safe to render unescaped
                         (needed for the refund-policy answer's inline link). --}}
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{!! $a !!}</p>
                </details>
            @endforeach
        </div>
    </main>
</x-layouts.app>
