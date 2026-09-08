@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Heading</label>
        <input type="text" wire:model="config.heading" class="{{ $inp }}"></div>
    <div class="grid gap-2 sm:grid-cols-2">
        <input type="text" wire:model="config.see_all_label" placeholder="'See all' label (optional)" class="{{ $inp }}">
        <input type="text" wire:model="config.see_all_target" placeholder="'See all' target" class="{{ $inp }}">
    </div>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cards</span>
            <button type="button" wire:click="addRepeaterItem" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:text-teal-300">+ Card</button>
        </div>
        @foreach (($config['cards'] ?? []) as $i => $card)
            <div class="rounded-xl border border-slate-200/70 p-3 dark:border-white/10" wire:key="carousel-card-{{ $i }}">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400">Card {{ $i + 1 }}</span>
                    <button type="button" wire:click="removeRepeaterItem({{ $i }})" class="text-slate-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="text" wire:model="config.cards.{{ $i }}.title" placeholder="Title" class="{{ $inp }}">
                    <input type="text" wire:model="config.cards.{{ $i }}.subtitle" placeholder="Subtitle / tag" class="{{ $inp }}">
                </div>
                <input type="text" wire:model="config.cards.{{ $i }}.target" placeholder="Tap target: URL, /path, route" class="{{ $inp }} mt-2">
                <div class="mt-2 flex items-center gap-2">
                    @if (! empty($card['image']))<img src="{{ $card['image'] }}" class="h-10 w-8 rounded object-cover">@endif
                    <input type="file" wire:model="upload" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-2 file:py-1 file:text-xs file:text-primary dark:text-slate-400">
                    <button type="button" wire:click="uploadRepeaterImage({{ $i }})" class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary dark:text-teal-300">Set</button>
                </div>
            </div>
        @endforeach
    </div>
</div>
