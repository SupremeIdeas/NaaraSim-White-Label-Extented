<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Identity (KYC)</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Choose your verification provider and review identity checks. Approving marks
        the user verified — L2 unlocks withdrawals, L3 (business) unlocks merchants.
    </p>

    @if ($saved)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Provider --}}
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Active provider</label>
        <div class="flex flex-wrap items-end gap-3">
            <select wire:model="provider" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                <option value="manual">Manual review (default)</option>
                <option value="smileid">Smile ID</option>
                <option value="dojah">Dojah</option>
            </select>
            <button type="button" wire:click="saveProvider" wire:loading.attr="disabled" wire:target="saveProvider"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">Save</button>
        </div>
        <p class="mt-2 text-[11px] text-slate-400">Smile ID / Dojah require their keys on Admin → API keys. Without keys, checks fall back to manual review.</p>
    </div>

    {{-- Pending review --}}
    <h2 class="mt-6 text-sm font-semibold text-slate-900 dark:text-slate-100">Pending review</h2>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Level</th>
                    <th class="px-4 py-3">Via</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pending as $v)
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="kyc-pending-{{ $v->id }}">
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $v->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $v->level === 3 ? 'Business (L3)' : 'Individual (L2)' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400 capitalize">{{ $v->provider }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="approve({{ $v->id }})" wire:confirm="Approve this identity check?"
                                        class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Approve</button>
                                <button type="button" wire:click="reject({{ $v->id }})" wire:confirm="Reject this identity check?"
                                        class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-600 dark:border-[#2D4060] dark:text-slate-300">Reject</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">Nothing awaiting review.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent --}}
    <h2 class="mt-6 text-sm font-semibold text-slate-900 dark:text-slate-100">Recent decisions</h2>
    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-[#2D4060]">
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Level</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">When</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $v)
                    @php($badge = $v->status === 'approved'
                        ? 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300'
                        : 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300')
                    <tr class="border-b border-slate-50 dark:border-[#22314e]" wire:key="kyc-recent-{{ $v->id }}">
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $v->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">L{{ $v->level }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize {{ $badge }}">{{ $v->status }}</span></td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ $v->reviewed_at?->diffForHumans() ?? $v->updated_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No decisions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
