@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="space-y-4">
    {{-- Preset --}}
    <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Preset</label>
        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
            @foreach ($heroPresets as $key => $preset)
                <button type="button" wire:click="applyPreset('{{ $key }}')"
                    @class([
                        'rounded-lg border px-2.5 py-2 text-left text-xs transition',
                        'border-primary bg-primary/5 text-primary dark:text-teal-300' => ($config['preset'] ?? '') === $key,
                        'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-white/10 dark:text-slate-300' => ($config['preset'] ?? '') !== $key,
                    ])>
                    <span class="block font-semibold">{{ $preset['label'] }}</span>
                    <span class="mt-0.5 block leading-tight opacity-70">{{ $preset['description'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Background mode --}}
    <div>
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Background</label>
        <div class="flex flex-wrap gap-1.5">
            @foreach (['animation' => 'Animation', 'image' => 'Background image', 'images' => 'Image slideshow', 'static' => 'Static'] as $mode => $label)
                <button type="button" wire:click="setMode('{{ $mode }}')"
                    @class([
                        'rounded-full px-3 py-1.5 text-xs font-medium transition',
                        'bg-primary text-white' => ($config['mode'] ?? '') === $mode,
                        'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300' => ($config['mode'] ?? '') !== $mode,
                    ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Scheme + alignment --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Colour</label>
            <select wire:model="config.scheme" class="{{ $inp }}">
                <option value="brand">Brand gradient</option>
                <option value="light">Light</option>
                <option value="dark">Dark navy</option>
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Align</label>
            <select wire:model="config.align" class="{{ $inp }}">
                <option value="center">Centered</option>
                <option value="left">Left</option>
            </select>
        </div>
    </div>

    {{-- Copy --}}
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
        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Sub-headline</label>
        <textarea wire:model="config.subheadline" rows="2" class="{{ $inp }}" maxlength="280"></textarea>
    </div>

    {{-- CTAs --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Primary button</label>
            <input type="text" wire:model="config.cta_primary_label" placeholder="Label" class="{{ $inp }} mb-1.5">
            <input type="text" wire:model="config.cta_primary_target" placeholder="URL, /path, or route name" class="{{ $inp }}">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Secondary button</label>
            <input type="text" wire:model="config.cta_secondary_label" placeholder="Label" class="{{ $inp }} mb-1.5">
            <input type="text" wire:model="config.cta_secondary_target" placeholder="URL, /path, or route name" class="{{ $inp }}">
        </div>
    </div>

    {{-- Images (image / slideshow modes) --}}
    @if (in_array($config['mode'] ?? '', ['image', 'images'], true))
        <div>
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Images {{ ($config['mode'] ?? '') === 'image' ? '(first image is used)' : '' }}
            </label>
            @php $imgs = array_values((array) ($config['images'] ?? [])); @endphp
            @if ($imgs)
                <div class="mb-2 grid grid-cols-3 gap-2">
                    @foreach ($imgs as $i => $src)
                        <div class="group relative aspect-video overflow-hidden rounded-lg border border-slate-200 dark:border-white/10">
                            <img src="{{ $src }}" alt="" class="h-full w-full object-cover">
                            <button type="button" wire:click="removeImage({{ $i }})"
                                class="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100">
                                <x-icon name="x" class="h-3 w-3" />
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="flex items-center gap-2">
                <input type="file" wire:model="upload" accept="image/webp,image/png,image/jpeg"
                    class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary dark:text-slate-400">
                <button type="button" wire:click="addImage" wire:loading.attr="disabled" wire:target="upload,addImage"
                    class="shrink-0 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/20 dark:text-teal-300">Add</button>
            </div>
            <div wire:loading wire:target="upload" class="mt-1 text-xs text-slate-400">Uploading…</div>
            @error('upload') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif
</div>
