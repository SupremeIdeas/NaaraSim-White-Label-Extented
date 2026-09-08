@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Video URL (YouTube / Vimeo)</label>
        <input type="url" wire:model="config.url" placeholder="https://youtube.com/watch?v=…" class="{{ $inp }}"></div>
    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Caption (optional)</label>
        <input type="text" wire:model="config.caption" class="{{ $inp }}"></div>
</div>
