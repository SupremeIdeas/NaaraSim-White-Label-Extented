@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Heading</label>
            <input type="text" wire:model="config.heading" class="{{ $inp }}"></div>
        <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Style</label>
            <select wire:model="config.style" class="{{ $inp }}"><option value="bordered">Bordered</option><option value="plain">Plain</option></select></div>
    </div>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Questions</span>
            <button type="button" wire:click="addRepeaterItem" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:text-teal-300">+ Q&amp;A</button>
        </div>
        @foreach (($config['items'] ?? []) as $i => $item)
            <div class="rounded-xl border border-slate-200/70 p-3 dark:border-white/10" wire:key="faq-{{ $i }}">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400">#{{ $i + 1 }}</span>
                    <button type="button" wire:click="removeRepeaterItem({{ $i }})" class="text-slate-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                </div>
                <input type="text" wire:model="config.items.{{ $i }}.q" placeholder="Question" class="{{ $inp }}">
                <textarea wire:model="config.items.{{ $i }}.a" rows="2" placeholder="Answer" class="{{ $inp }} mt-2"></textarea>
            </div>
        @endforeach
    </div>
</div>
