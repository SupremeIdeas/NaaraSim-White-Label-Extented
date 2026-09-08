<div class="col-span-full flex flex-col items-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white py-14 text-center dark:border-white/10 dark:bg-white/5">
    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-teal-500/15 dark:text-teal-300"><x-icon name="users" class="h-6 w-6" gradient /></span>
    <p class="font-semibold text-slate-800 dark:text-slate-100">
        {{ $search !== '' ? 'No contacts match your search' : 'No contacts yet' }}
    </p>
    <p class="max-w-xs text-sm text-slate-500 dark:text-slate-400">
        {{ $search !== '' ? 'Try a different name or number.' : 'Add someone or import a CSV / vCard — then call them straight from here.' }}
    </p>
    @if ($search === '')
        <button type="button" @click="openAdd()" class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
            <x-icon name="plus" class="h-4 w-4" /> Add a contact
        </button>
    @endif
</div>
