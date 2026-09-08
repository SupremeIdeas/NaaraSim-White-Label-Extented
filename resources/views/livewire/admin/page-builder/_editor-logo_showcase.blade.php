@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Heading (optional)</label>
        <input type="text" wire:model="config.heading" placeholder="Trusted by" class="{{ $inp }}"></div>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Logos</span>
            <button type="button" wire:click="addRepeaterItem" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:text-teal-300">+ Logo</button>
        </div>
        @foreach (($config['logos'] ?? []) as $i => $logo)
            <div class="flex items-center gap-2 rounded-xl border border-slate-200/70 p-3 dark:border-white/10" wire:key="logo-{{ $i }}">
                @if (! empty($logo['src']))<img src="{{ $logo['src'] }}" class="h-8 w-16 object-contain">@endif
                <input type="text" wire:model="config.logos.{{ $i }}.alt" placeholder="Alt text" class="{{ $inp }} max-w-[140px]">
                <input type="file" wire:model="upload" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-2 file:py-1 file:text-xs file:text-primary dark:text-slate-400">
                <button type="button" wire:click="uploadRepeaterImage({{ $i }})" class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary dark:text-teal-300">Set</button>
                <button type="button" wire:click="removeRepeaterItem({{ $i }})" class="shrink-0 text-slate-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
            </div>
        @endforeach
    </div>
</div>
