@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    <div class="rounded-lg bg-amber-50 p-2.5 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
        <x-icon name="shield" class="mr-1 inline h-3.5 w-3.5" />
        Pasted HTML is sanitized on save — scripts, iframes, event handlers and unsafe URLs are stripped automatically.
    </div>
    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">HTML</label>
        <textarea wire:model="config.html" rows="10" spellcheck="false"
            class="{{ $inp }} font-mono text-xs" placeholder="<h2>Heading</h2>&#10;<p>Your content…</p>"></textarea>
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Width</label>
        <select wire:model="config.max_width" class="{{ $inp }}">
            <option value="container">Contained</option>
            <option value="full">Full width</option>
        </select>
    </div>
</div>
