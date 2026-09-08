<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Naara Gift hero</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">The same hero system as the customer dashboard home — title, description, and an optional light/dark image — shown at the top of the Naara Gift storefront. Customised independently from the dashboard hero.</p>
    </div>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <div class="grid gap-4 sm:grid-cols-[1fr,10rem]">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Hero title</label>
                <input type="text" wire:model="title" maxlength="40"
                       placeholder="{{ \App\Support\GiftHeroBackground::DEFAULT_TITLE }}"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                <p class="mt-1 text-[11px] text-slate-400">Leave blank for the default. The first word renders plain; the rest renders in the accent gradient.</p>
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Title size</label>
                <select wire:model="title_size" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @foreach (\App\Support\GiftHeroBackground::TITLE_SIZES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('title_size') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Description line under the title</label>
            <input type="text" wire:model="description" maxlength="120"
                   placeholder="{{ \App\Support\GiftHeroBackground::DEFAULT_DESCRIPTION }}"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            <p class="mt-1 text-[11px] text-slate-400">One short sentence. Leave blank to use the default.</p>
            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 p-3 dark:border-[#2D4060]">
            <span>
                <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">Show hero image</span>
                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Turn the image off without removing the uploaded art.</span>
            </span>
            <x-ui.switch wire:model="enabled" label="Show hero image" />
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <p class="text-xs font-medium text-slate-600 dark:text-slate-300">Hero image (optional)</p>
                @if ($isSet)
                    <button type="button" wire:click="removeImages" wire:confirm="Remove the hero images and return to the text-only header?"
                            class="text-xs font-medium text-red-600 hover:underline">Remove image</button>
                @endif
            </div>
            <p class="mb-3 text-[11px] text-slate-400">WebP or JPG, 1600×800px recommended (2:1) — will crop to fit, under 600&nbsp;KB. Switches automatically with the user's light/dark theme. Leave blank for a clean title-only header.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([['hero_light', 'Light mode', $currentLight], ['hero_dark', 'Dark mode', $currentDark]] as [$field, $label, $current])
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ $label }}</label>
                        <div class="mb-2 flex aspect-[2/1] items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-[#2D4060] dark:bg-[#243352]">
                            @if ($this->{$field} && $this->{$field}->isPreviewable())
                                <img src="{{ $this->{$field}->temporaryUrl() }}" class="h-full w-full object-cover">
                            @elseif ($current)
                                <img src="{{ $current }}" class="h-full w-full object-cover">
                            @else
                                <span class="text-[11px] text-slate-400">No image — title-only header</span>
                            @endif
                        </div>
                        <input type="file" wire:model="{{ $field }}" accept="image/webp,image/jpeg"
                               class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary dark:text-slate-400">
                        @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="badge-check" class="h-4 w-4" /> Save hero</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </div>
    </form>
</div>
