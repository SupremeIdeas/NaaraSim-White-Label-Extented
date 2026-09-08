@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Quote</label>
        <textarea wire:model="config.quote" rows="3" class="{{ $inp }}"></textarea></div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Attribution</label>
            <input type="text" wire:model="config.attribution" class="{{ $inp }}"></div>
        <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Treatment</label>
            <select wire:model="config.treatment" class="{{ $inp }}">
                <option value="gradient">Brand gradient</option>
                <option value="dark">Dark navy</option>
                <option value="minimal">Minimal</option>
            </select></div>
    </div>
</div>
