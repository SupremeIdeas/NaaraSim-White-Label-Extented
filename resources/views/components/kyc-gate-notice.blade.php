@props(['level' => 2, 'action' => 'add a payout account'])
{{-- BUILD-4 §5.1 — a clear, inline explanation shown at a withdraw / add-bank-
     account entry point when the user hasn't reached the required KYC level, with
     a direct link into verification. Renders nothing once they're verified, so it
     can sit safely above any payout control. --}}
@php($__verified = auth()->check() && app(\App\Services\Kyc\KycService::class)->hasLevel(auth()->user(), (int) $level))
@unless ($__verified)
    <div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/40']) }}>
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
            <x-icon name="shield" class="h-4 w-4" />
        </span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Verify your identity to {{ $action }}</p>
            <p class="mt-0.5 text-xs text-amber-700/80 dark:text-amber-300/80">A quick identity check keeps payouts safe and going to the right person. It only takes a moment.</p>
            <a href="{{ route('account.verify') }}" wire:navigate
               class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-amber-700">
                <x-icon name="badge-check" class="h-3.5 w-3.5" /> Verify identity
            </a>
        </div>
    </div>
@endunless
