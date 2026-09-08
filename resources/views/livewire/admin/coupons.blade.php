<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Coupons</h1>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Discount codes for banner offers. Every discount is margin-guarded: the charged price is automatically clamped above provider cost + minimum profit, so a coupon can never sell at a loss.</p>

    {{-- Marketing nudges toggle (owner request): friendly first-purchase /
         comeback offers shown on the dashboard of not-yet-purchased accounts. --}}
    <div class="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/15 text-accent dark:bg-accent/25"><x-icon name="gift" class="h-5 w-5" /></span>
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Marketing nudges</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Show a friendly {{ \App\Support\MarketingCoupons::WELCOME_CODE }} / {{ \App\Support\MarketingCoupons::COMEBACK_CODE }} offer to accounts that haven't purchased yet.</p>
            </div>
        </div>
        <button type="button" wire:click="toggleNudges" role="switch" aria-checked="{{ $nudgesOn ? 'true' : 'false' }}"
                @class([
                    'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition',
                    'bg-primary' => $nudgesOn,
                    'bg-slate-300 dark:bg-[#2D4060]' => ! $nudgesOn,
                ])>
            <span @class(['inline-block h-5 w-5 transform rounded-full bg-white transition', 'translate-x-5' => $nudgesOn, 'translate-x-0.5' => ! $nudgesOn])></span>
        </button>
    </div>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    {{-- Create --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
            <x-icon name="gift" class="h-4 w-4 text-primary" /> New coupon
        </h2>

        <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Code</label>
                <div class="flex gap-2">
                    <input wire:model="code" type="text" placeholder="WELCOME10"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase tracking-wider text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <button type="button" wire:click="generateCode" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Generate</button>
                </div>
                @error('code') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Discount %</label>
                <input wire:model="percent_off" type="number" min="1" max="90" step="0.5"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('percent_off') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Whatever the percent, the price never drops below cost + minimum profit.</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Applies to</label>
                <select wire:model="applies_to" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="all">Everything</option>
                    <option value="esim">eSIM plans only</option>
                    <option value="number">Numbers only</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Total uses <span class="font-normal text-slate-400">(blank = ∞)</span></label>
                    <input wire:model="max_redemptions" type="number" min="1"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('max_redemptions') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Per-user limit</label>
                    <input wire:model="per_user_limit" type="number" min="1" max="100"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('per_user_limit') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Expires <span class="font-normal text-slate-400">(optional)</span></label>
                <input wire:model="expires_at" type="datetime-local"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('expires_at') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Create coupon</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Creating…</span>
                </button>
            </div>
        </form>
    </section>

    {{-- List --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-[#2D4060]">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Code</th>
                    <th class="px-4 py-2 font-medium">Discount</th>
                    <th class="px-4 py-2 font-medium">Scope</th>
                    <th class="px-4 py-2 font-medium">Used</th>
                    <th class="px-4 py-2 font-medium">Given away</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-[#243352] dark:bg-[#1A2840]">
                @forelse ($coupons as $coupon)
                    <tr wire:key="coupon-{{ $coupon->id }}" class="text-slate-700 dark:text-slate-200">
                        <td class="px-4 py-2.5 font-mono font-semibold uppercase">{{ $coupon->code }}</td>
                        <td class="px-4 py-2.5">{{ rtrim(rtrim(number_format((float) $coupon->percent_off, 2), '0'), '.') }}%</td>
                        <td class="px-4 py-2.5 capitalize">{{ $coupon->applies_to === 'all' ? 'Everything' : $coupon->applies_to }}</td>
                        <td class="px-4 py-2.5">{{ $coupon->times_redeemed }}{{ $coupon->max_redemptions ? ' / '.$coupon->max_redemptions : '' }}</td>
                        <td class="px-4 py-2.5">${{ number_format((float) ($coupon->total_saved ?? 0), 2) }}</td>
                        <td class="px-4 py-2.5">
                            <x-ui.tag :variant="$coupon->isRedeemable() ? 'live' : 'soon'">
                                {{ $coupon->isRedeemable() ? 'Live' : ($coupon->is_active ? 'Ended' : 'Paused') }}
                            </x-ui.tag>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="toggle({{ $coupon->id }})" wire:loading.attr="disabled"
                                    class="text-xs font-medium text-slate-500 underline hover:text-primary dark:text-slate-400">
                                {{ $coupon->is_active ? 'Pause' : 'Resume' }}
                            </button>
                            <button type="button" wire:click="delete({{ $coupon->id }})" wire:loading.attr="disabled"
                                    wire:confirm="Delete coupon {{ $coupon->code }}? A coupon that has been used is paused instead, to keep the audit trail."
                                    class="ml-2 text-xs font-medium text-red-500 underline hover:text-red-700 dark:text-red-400">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400 dark:text-slate-500">No coupons yet — create one above, then attach it to a banner.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
