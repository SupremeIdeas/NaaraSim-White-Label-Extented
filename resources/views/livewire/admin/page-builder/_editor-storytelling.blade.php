@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    <div class="grid gap-2 sm:grid-cols-2">
        <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Heading (optional)</label>
            <input type="text" wire:model="config.heading" class="{{ $inp }}"></div>
        <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Tone</label>
            <select wire:model="config.tone" class="{{ $inp }}">
                <option value="auto">Auto (theme-aware)</option>
                <option value="on-dark">On dark (light text)</option>
            </select></div>
    </div>
    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Subheading (optional)</label>
        <input type="text" wire:model="config.subheading" class="{{ $inp }}"></div>

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Slides</span>
            <button type="button" wire:click="addRepeaterItem" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:text-teal-300">+ Slide</button>
        </div>
        @foreach (($config['slides'] ?? []) as $i => $slide)
            <div class="rounded-xl border border-slate-200/70 p-3 dark:border-white/10" wire:key="story-slide-{{ $i }}">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400">Slide {{ $i + 1 }}</span>
                    <button type="button" wire:click="removeRepeaterItem({{ $i }})" class="text-slate-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="text" wire:model="config.slides.{{ $i }}.eyebrow" placeholder="Eyebrow (small label)" class="{{ $inp }}">
                    <input type="text" wire:model="config.slides.{{ $i }}.title" placeholder="Title" class="{{ $inp }}">
                </div>
                <textarea rows="2" wire:model="config.slides.{{ $i }}.body" placeholder="Body — one tight idea" class="{{ $inp }} mt-2"></textarea>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <input type="text" wire:model="config.slides.{{ $i }}.cta_label" placeholder="CTA label (optional)" class="{{ $inp }}">
                    <input type="text" wire:model="config.slides.{{ $i }}.cta_target" placeholder="CTA target: URL, /path, route" class="{{ $inp }}">
                </div>
                <textarea rows="2" wire:model="config.slides.{{ $i }}.modal_body" placeholder="Deeper copy for the ‘+’ modal (optional)" class="{{ $inp }} mt-2"></textarea>
                <div class="mt-2 flex items-center gap-2">
                    @if (! empty($slide['image']))<img src="{{ $slide['image'] }}" class="h-10 w-14 rounded object-cover">@endif
                    <input type="file" wire:model="upload" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-2 file:py-1 file:text-xs file:text-primary dark:text-slate-400">
                    <button type="button" wire:click="uploadRepeaterImage({{ $i }})" class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary dark:text-teal-300">Set</button>
                </div>
            </div>
        @endforeach
    </div>
</div>
