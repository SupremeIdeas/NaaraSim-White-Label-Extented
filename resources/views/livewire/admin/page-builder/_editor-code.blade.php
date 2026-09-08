@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Caption</label>
            <input type="text" wire:model="config.caption" class="{{ $inp }}"></div>
        <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Language</label>
            <input type="text" wire:model="config.language" placeholder="bash, php, json…" class="{{ $inp }}"></div>
    </div>
    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Code</label>
        <textarea wire:model="config.code" rows="8" spellcheck="false" class="{{ $inp }} font-mono text-xs"></textarea></div>
</div>
