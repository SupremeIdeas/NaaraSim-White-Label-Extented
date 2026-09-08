@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Floating nav</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">The pill bar on the public site. Repoint any slot to any page — no code change.</p>
        </div>
        <button type="button" wire:click="addSlot" class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/20 dark:text-teal-300"><x-icon name="plus" class="h-3.5 w-3.5" /> Add slot</button>
    </div>

    <div class="space-y-3">
        @foreach ($slots as $id => $slot)
            <div class="rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-white/10 dark:bg-slate-900/60" wire:key="slot-{{ $id }}">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Label</label>
                        <input type="text" wire:model="slots.{{ $id }}.label" class="{{ $inp }}"></div>
                    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Icon</label>
                        <input type="text" wire:model="slots.{{ $id }}.icon" placeholder="globe, wifi, phone…" class="{{ $inp }}"></div>
                    <div class="sm:col-span-2"><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Target <span class="font-normal text-slate-400">(route name, /path, URL, or "wizard")</span></label>
                        <input type="text" wire:model="slots.{{ $id }}.target" class="{{ $inp }}"></div>
                    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Visible to</label>
                        <select wire:model="slots.{{ $id }}.visibility" class="{{ $inp }}">
                            <option value="all">Everyone</option><option value="auth">Signed-in only</option><option value="guest">Signed-out only</option>
                        </select></div>
                    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Order</label>
                        <input type="number" wire:model="slots.{{ $id }}.position" class="{{ $inp }}"></div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" wire:model="slots.{{ $id }}.is_active" class="rounded border-slate-300 text-primary focus:ring-primary"> Active</label>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" wire:model="slots.{{ $id }}.is_center" class="rounded border-slate-300 text-primary focus:ring-primary"> Glowing centerpiece</label>
                    <button type="button" wire:click="remove({{ $id }})" wire:confirm="Remove this slot?" class="ml-auto text-slate-400 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex gap-2">
        <button type="button" wire:click="save" class="flex-1 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary-dark">Save floating nav</button>
        <button type="button" wire:click="resetDefaults" wire:confirm="Reset the floating nav to defaults?" class="rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-600 dark:border-white/15 dark:text-slate-300">Reset</button>
    </div>
</div>
