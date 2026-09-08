{{-- Add / edit contact + bulk import — a bottom sheet (mobile) / centered
     dialog (desktop), matching the product modals. Driven by Alpine `sheet`. --}}
<div x-show="sheet" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center"
     @keydown.escape.window="sheet = false" role="dialog" aria-modal="true" aria-label="Contact">
    <div class="absolute inset-0 bg-black/60" @click="sheet = false"></div>

    <div x-show="sheet" x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-8 opacity-0 sm:scale-95" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
         class="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
        <div class="mx-auto mt-3 h-1.5 w-10 shrink-0 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>

        <div class="flex items-center justify-between px-5 py-4">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $editingId ? 'Edit contact' : 'New contact' }}</h2>
            <button type="button" @click="sheet = false" aria-label="Close" class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"><x-icon name="x" class="h-5 w-5" /></button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-6">
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                    <input type="text" wire:model="name" placeholder="Ada Obi"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Number</label>
                    <input type="tel" wire:model="phone" placeholder="+2348012345678"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                    @error('phone') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="check" class="h-4 w-4" /> {{ $editingId ? 'Save changes' : 'Add contact' }}
            </button>

            @if ($editingId)
                <button type="button" wire:click="delete({{ $editingId }})" wire:confirm="Remove this contact?" @click="sheet = false"
                        class="mt-2 w-full rounded-2xl border border-red-200 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 dark:border-red-900/40 dark:hover:bg-red-950/20">Delete contact</button>
            @endif

            {{-- Bulk import --}}
            <div class="mt-6 rounded-2xl border border-dashed border-slate-300 p-4 dark:border-white/10">
                <p class="mb-1 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"><x-icon name="upload" class="h-4 w-4" /> Import a batch</p>
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Upload a <strong>.csv</strong> (name, number) or a <strong>.vcf</strong> (vCard).</p>
                <div class="flex flex-wrap items-center gap-2">
                    <input type="file" wire:model="upload" accept=".csv,.txt,.vcf"
                           class="block w-full max-w-[12rem] text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary dark:text-slate-300 dark:file:bg-primary/20 dark:file:text-teal-300">
                    <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import,upload" @disabled(! $upload)
                            class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-50">
                        <span wire:loading.remove wire:target="import" class="inline-flex items-center gap-2"><x-icon name="upload" class="h-4 w-4" /> Import</span>
                        <span wire:loading wire:target="import" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Importing…</span>
                    </button>
                </div>
                @error('upload') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

                {{-- Android-Chrome OS contact picker (progressive enhancement). --}}
                <div x-data="{ supported: (typeof navigator !== 'undefined' && 'contacts' in navigator && 'ContactsManager' in window),
                        async pick() { try { const sel = await navigator.contacts.select(['name','tel'], { multiple: true });
                            const rows = sel.map(c => ({ name: (c.name && c.name[0]) || '', phone: (c.tel && c.tel[0]) || '' })).filter(r => r.phone);
                            if (rows.length) { $wire.importPicked(rows); } } catch (e) {} } }"
                     x-show="supported" x-cloak class="mt-3">
                    <button type="button" @click="pick()" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:border-white/10 dark:text-slate-200"><x-icon name="inbox" class="h-4 w-4" /> Import from phone</button>
                </div>
            </div>
        </div>
    </div>
</div>
