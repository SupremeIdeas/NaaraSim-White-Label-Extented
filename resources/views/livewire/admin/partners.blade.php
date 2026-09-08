<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Partners</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Profit-sharing partners earn an admin-set share of platform-wide profit each period. The share % is confidential — partners only ever see dollar figures.</p>
    </div>

    @if ($saved)
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-300">{{ $saved }}</div>
    @endif

    {{-- Global settings --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Program settings</h2>
        <div class="flex flex-wrap items-end gap-4">
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" wire:model="enabled" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352]"> Program enabled
            </label>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Default cadence</label>
                <select wire:model="defaultCadence" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="monthly">Monthly</option><option value="weekly">Weekly</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Default payout mode</label>
                <select wire:model="defaultMode" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="manual">Admin-approved</option><option value="auto">Automated</option>
                </select>
            </div>
            <button type="button" wire:click="saveSettings" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Save</button>
        </div>
    </div>

    {{-- Add a partner --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="mb-1 text-sm font-bold text-slate-900 dark:text-white">Add a partner</h2>
        <p class="mb-3 text-xs text-slate-400">Existing user? Just enter their email. New person? Add a name too and we'll create the account with a one-time password — they land on /partner at first login.</p>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[16rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
                <input type="email" wire:model="newEmail" placeholder="person@example.com" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('newEmail') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div class="min-w-[10rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name <span class="text-slate-400">(new account)</span></label>
                <input type="text" wire:model="newName" placeholder="Optional" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            <div class="w-28">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Share %</label>
                <input type="number" step="0.1" min="0" max="100" wire:model="newShare" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('newShare') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <button type="button" wire:click="addPartner" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add partner</button>
        </div>
        @if ($newTempPassword)
            <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm dark:border-primary/40" x-data="{ copied: false }">
                <span class="text-slate-600 dark:text-slate-300">New account created. Temporary password:</span>
                <code class="rounded bg-white px-2 py-1 font-mono font-semibold text-primary dark:bg-[#243352] dark:text-teal-300">{{ $newTempPassword }}</code>
                <button type="button" @click="navigator.clipboard.writeText('{{ $newTempPassword }}'); copied = true; setTimeout(() => copied = false, 1500)"
                        class="inline-flex items-center gap-1 rounded-lg bg-primary px-2.5 py-1 text-xs font-semibold text-white"><x-icon name="copy" class="h-3.5 w-3.5" /> <span x-text="copied ? 'Copied' : 'Copy'"></span></button>
            </div>
        @endif
    </div>

    {{-- Partner list --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400 dark:bg-white/5">
                <tr><th class="px-4 py-3">Partner</th><th class="px-4 py-3">Share</th><th class="px-4 py-3">Cadence</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Balance</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($partners as $p)
                    <tr wire:key="p-{{ $p->id }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-900 dark:text-white">{{ $p->owner?->name }}</p>
                            <p class="text-xs text-slate-400">{{ $p->owner?->email }}</p>
                        </td>
                        @if ($editingId === $p->id)
                            <td class="px-4 py-3"><input type="number" step="0.1" min="0" max="100" wire:model="editShare" class="w-20 rounded border border-slate-300 bg-white px-2 py-1 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></td>
                            <td class="px-4 py-3"><select wire:model="editCadence" class="rounded border border-slate-300 bg-white px-2 py-1 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"><option value="monthly">Monthly</option><option value="weekly">Weekly</option></select></td>
                            <td class="px-4 py-3"><select wire:model="editMode" class="rounded border border-slate-300 bg-white px-2 py-1 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"><option value="manual">Approve</option><option value="auto">Auto</option></select></td>
                            <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-200">${{ number_format($p->admin_balance, 2) }}</td>
                            <td class="px-4 py-3" colspan="2">
                                <button type="button" wire:click="saveTerms" class="rounded bg-primary px-3 py-1 text-xs font-semibold text-white">Save</button>
                                <button type="button" wire:click="$set('editingId', null)" class="ml-1 text-xs text-slate-400">Cancel</button>
                            </td>
                        @else
                            <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-300">{{ number_format((float) $p->profit_share_pct, 2) }}%</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ ucfirst($p->payout_cadence) }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $p->payout_mode === 'auto' ? 'Automated' : 'Approve' }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-200">${{ number_format($p->admin_balance, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ['active' => 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300', 'suspended' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300', 'pending' => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300'][$p->status] ?? '' }}">{{ ucfirst($p->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="edit({{ $p->id }})" class="text-xs font-semibold text-primary hover:underline dark:text-teal-300">Edit</button>
                                @if ($p->status === 'active')
                                    <button type="button" wire:click="suspend({{ $p->id }})" class="ml-2 text-xs font-semibold text-amber-600 hover:underline">Suspend</button>
                                @else
                                    <button type="button" wire:click="activate({{ $p->id }})" class="ml-2 text-xs font-semibold text-green-600 hover:underline">Activate</button>
                                @endif
                                <button type="button" wire:click="remove({{ $p->id }})" wire:confirm="Remove this partner?" class="ml-2 text-xs font-semibold text-red-600 hover:underline">Remove</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No partners yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $partners->links() }}</div>
</div>
