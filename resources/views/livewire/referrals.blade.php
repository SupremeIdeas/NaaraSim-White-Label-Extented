<div class="mx-auto max-w-2xl" x-data="{ copied: false }">
    {{-- Hero: refer-and-earn illustration beside the copy (self-hosted Lottie,
         reduced-motion aware). Text flexes; the animation stays a fixed size. --}}
    <div class="mb-6 flex items-center gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Refer &amp; earn</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Share NaaraSim. When a friend makes their first purchase, you earn store credit — a share of the profit.</p>
        </div>
        <x-lottie name="refer-earn" label="Refer and earn" class="h-24 w-24 shrink-0 sm:h-36 sm:w-36" />
    </div>

    <div class="rounded-2xl border border-slate-200 nx-glass-tile p-6 shadow-sm dark:border-[var(--brand-card-border-dark)]">
        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Your referral link</label>
        <div class="flex items-center gap-2">
            <input type="text" readonly value="{{ $link }}" x-ref="link"
                   class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-200">
            <button type="button"
                    @click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 1500)"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
                <x-icon name="copy" class="h-4 w-4" />
                <span x-text="copied ? 'Copied' : 'Copy'"></span>
            </button>
        </div>
        <div class="mt-3 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <x-icon name="gift" class="h-4 w-4 text-accent-dark dark:text-accent" /> Code: <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ $code }}</span>
        </div>
    </div>

    <h2 class="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Your referrals</h2>
    <div class="space-y-2">
        @forelse ($referrals as $referral)
            <div wire:key="ref-{{ $referral->id }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                <span class="text-slate-700 dark:text-slate-200">Referral #{{ $referral->referred_id }}</span>
                <span @class([
                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $referral->rewarded,
                    'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-400' => ! $referral->rewarded,
                ])>
                    <x-icon :name="$referral->rewarded ? 'check' : 'refresh'" class="h-3.5 w-3.5" />
                    {{ $referral->rewarded ? 'Rewarded' : 'Pending' }}
                </span>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400 dark:border-[var(--brand-card-border-dark)] dark:text-slate-500">
                No referrals yet. Share your link to start earning.
            </div>
        @endforelse
    </div>

    {{-- Real, withdrawable referral earnings + payout status (BUILD-22 §1/§4). --}}
    <div class="mt-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Your cash earnings</h2>
        <livewire:payout-dashboard earner-type="referral" />
    </div>
</div>
