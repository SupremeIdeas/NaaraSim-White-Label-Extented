@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Eyebrow</label>
        <input type="text" wire:model="config.eyebrow" class="{{ $inp }}" maxlength="80">
    </div>
    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Headline</label>
        <input type="text" wire:model="config.headline" class="{{ $inp }}" maxlength="120">
        @error('config.headline') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Body</label>
        <textarea wire:model="config.body" rows="3" class="{{ $inp }}" maxlength="600"></textarea>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Image side</label>
            <select wire:model="config.image_side" class="{{ $inp }}">
                <option value="right">Right</option>
                <option value="left">Left</option>
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Background</label>
            <select wire:model="config.bg" class="{{ $inp }}">
                <option value="transparent">None</option>
                <option value="tint">Brand tint</option>
                <option value="dark">Dark</option>
            </select>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">CTA label</label>
            <input type="text" wire:model="config.cta_label" placeholder="Optional" class="{{ $inp }}">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">CTA target</label>
            <input type="text" wire:model="config.cta_target" placeholder="URL, /path, or route" class="{{ $inp }}">
        </div>
    </div>

    {{-- Image (optional — graceful text-only fallback when empty) --}}
    <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Image <span class="font-normal normal-case opacity-60">(optional — omitted = clean text-only)</span></label>
        @if (! empty($config['image']))
            <div class="group relative mb-2 aspect-video max-w-xs overflow-hidden rounded-lg border border-slate-200 dark:border-white/10">
                <img src="{{ $config['image'] }}" alt="" class="h-full w-full object-cover">
                <button type="button" wire:click="clearImage" class="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100"><x-icon name="x" class="h-3 w-3" /></button>
            </div>
        @endif
        <div class="flex items-center gap-2">
            <input type="file" wire:model="upload" accept="image/webp,image/png,image/jpeg"
                class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary dark:text-slate-400">
            <button type="button" wire:click="addImage" wire:loading.attr="disabled" wire:target="upload,addImage"
                class="shrink-0 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/20 dark:text-teal-300">Set</button>
        </div>
        <div wire:loading wire:target="upload" class="mt-1 text-xs text-slate-400">Uploading…</div>
        @error('upload') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
