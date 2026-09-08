<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Merchants</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Approve resellers and set the reseller margin. Your profit (retail − cost) is
        always kept; the merchant earns the reseller margin on top. MarginGuard still
        floors every price.
    </p>

    @if ($saved)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Settings --}}
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <label class="flex items-center justify-between gap-4">
            <span>
                <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">Enable the merchant programme</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">When off, no one can apply to become a merchant.</span>
            </span>
            <input type="checkbox" wire:model="enabled" class="h-5 w-9 cursor-pointer rounded-full">
        </label>
        <div class="mt-5 max-w-xs">
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Reseller margin %</label>
            <input type="number" step="0.1" min="0" wire:model="resellerMargin"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('resellerMargin') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            <p class="mt-1 text-[11px] text-slate-400">Added over retail to form the merchant price. The merchant earns this; you keep your usual profit.</p>
        </div>

        {{-- Eligibility to migrate — a user must meet ANY one. --}}
        <div class="mt-6 border-t border-slate-100 pt-5 dark:border-[#243352]">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Eligibility to migrate (meet any one)</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Min lifetime spend (USD)</label>
                    <input type="number" step="1" min="0" wire:model="minSpend"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('minSpend') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Fast-route fee (USD)</label>
                    <input type="number" step="1" min="0" wire:model="enrollmentFee"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('enrollmentFee') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Min referred users</label>
                    <input type="number" step="1" min="0" wire:model="minReferrals"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('minReferrals') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">V2 upgrade price (USD)</label>
                    <input type="number" step="1" min="0" wire:model="upgradePrice"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('upgradePrice') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Merchant-referral bonus (USD, one-time)</label>
                    <input type="number" step="0.5" min="0" wire:model="merchantReferralBonus"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400">Flat NaaraCredit bonus when a merchant's invitee becomes a merchant. 0 = off. One-time, single-hop — not a downline.</p>
                </div>
            </div>
            <p class="mt-2 text-[11px] text-slate-400">A user unlocks the programme by hitting the spend threshold, paying the one-time fast-route fee from their wallet, or reaching the referral target.</p>

            {{-- Auto-promotion (BUILD-4 §4.3) — off by default; the recommended
                 flow is the "Ready to promote" queue on the Users page. --}}
            <label class="mt-3 flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                <input type="checkbox" wire:model="autoPromote" class="h-5 w-9 cursor-pointer rounded-full">
                Auto-promote eligible users to Merchant V1
            </label>
            <p class="mt-1 text-[11px] text-slate-400">When on, a daily job promotes every user who meets an eligibility path. Off by default — most admins prefer the one-click "Ready to promote" queue on the Users page.</p>

            {{-- Deferred-verification payout rule (BUILD-4 §1). Identity is verified
                 at payout time, not at signup. This optional rule additionally
                 requires business KYB for large single payouts — ships OFF. --}}
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-[#2D4060] dark:bg-[#152238]">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <input type="checkbox" wire:model="kybOverThreshold" class="h-5 w-9 cursor-pointer rounded-full">
                    Require business KYB for large payouts
                </label>
                <p class="mt-1 text-[11px] text-slate-400">Merchants verify identity when they first cash out (a verified bank account = KYC L2). With this on, any single payout <strong>over the amount below</strong> also needs full business (KYB) verification. Leave off until your compliance stance is set.</p>
                <div class="mt-3 max-w-xs">
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">KYB threshold — single payout (USD)</label>
                    <input type="number" step="1" min="0" wire:model="kybThreshold"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('kybThreshold') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                class="mt-5 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">Save settings</button>
    </div>

    {{-- Pending applications --}}
    <h2 class="mt-6 text-sm font-semibold text-slate-900 dark:text-slate-100">Pending applications</h2>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">Business</th>
                    <th class="px-4 py-3">Owner</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pending as $m)
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="m-pending-{{ $m->id }}">
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $m->business_name }} <span class="text-slate-400">/{{ $m->slug }}</span></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $m->owner?->email ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="approve({{ $m->id }})" wire:confirm="Approve this merchant?"
                                        class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Approve</button>
                                <button type="button" wire:click="reject({{ $m->id }})" wire:confirm="Reject this application?"
                                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-600 dark:border-[#2D4060] dark:text-slate-300">Reject</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">No applications awaiting review.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Active / suspended --}}
    <h2 class="mt-6 text-sm font-semibold text-slate-900 dark:text-slate-100">Merchants</h2>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">Business</th>
                    <th class="px-4 py-3 text-right">Customers</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($active as $m)
                    @php($badge = $m->status === 'active'
                        ? 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300'
                        : 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300')
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="m-active-{{ $m->id }}">
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $m->business_name }} <span class="text-slate-400">/{{ $m->slug }}</span></td>
                        <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ $m->customers_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize {{ $badge }}">{{ $m->status }}</span>
                            @if ($m->isV2())<span class="nx-badge ml-1">V2</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($m->isV2())
                                <button type="button" wire:click="downgrade({{ $m->id }})" wire:confirm="Return this merchant to the standard tier?"
                                        class="mr-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300">Downgrade</button>
                            @else
                                <button type="button" wire:click="upgrade({{ $m->id }})" wire:confirm="Grant Merchant V2 (client management) for free?"
                                        class="mr-1 rounded-lg border border-primary/40 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/5 dark:text-teal-300">Grant V2</button>
                            @endif
                            @if ($m->status === 'active')
                                <button type="button" wire:click="suspend({{ $m->id }})" wire:confirm="Suspend this merchant?"
                                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-600 dark:border-[#2D4060] dark:text-slate-300">Suspend</button>
                            @else
                                <button type="button" wire:click="approve({{ $m->id }})" wire:confirm="Reactivate this merchant?"
                                        class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Reactivate</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No merchants yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
