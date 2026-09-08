<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">eSIM hero</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Title, description, and up to {{ $maxImages }} images for the storefront’s interchanging-reveal hero. WebP or JPG, ≤600&nbsp;KB. Clear all to fall back to the shipped images.</p>
    </div>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        {{-- Catalogue section heading + subheading (below the hero banner). --}}
        <div class="grid gap-4 rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-white/5 dark:bg-white/[0.03] sm:grid-cols-2">
            <div class="sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Catalogue section heading</p>
                <p class="text-[11px] text-slate-400">The big heading + line shown below the hero banner on the eSIM page.</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Section heading</label>
                <input type="text" wire:model="section_title" maxlength="60" placeholder="eSIM Plans" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('section_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Section subheading</label>
                <input type="text" wire:model="section_subtitle" maxlength="160" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('section_subtitle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Hero title</label>
            <input type="text" wire:model="title" maxlength="120" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Description</label>
            <textarea wire:model="description" rows="2" maxlength="250" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <p class="mb-2 text-xs font-medium text-slate-600 dark:text-slate-300">Current images ({{ count($images) }}/{{ $maxImages }})</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($images as $i => $src)
                    <div class="group relative aspect-[16/10] overflow-hidden rounded-lg border border-slate-200 dark:border-[#2D4060]">
                        <img src="{{ $src }}" class="h-full w-full object-cover">
                        <button type="button" wire:click="removeImage({{ $i }})" class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                        <span class="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 text-[10px] font-semibold text-white">{{ $i + 1 }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if (count($images) < $maxImages)
            <div>
                <p class="mb-2 text-xs font-medium text-slate-600 dark:text-slate-300">Add images</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (['slot0', 'slot1', 'slot2', 'slot3'] as $slot)
                        <div>
                            <input type="file" wire:model="{{ $slot }}" accept="image/webp,image/jpeg"
                                   class="block w-full text-[11px] text-slate-500 file:mr-2 file:rounded file:border-0 file:bg-primary/10 file:px-2 file:py-1 file:text-[11px] file:font-medium file:text-primary">
                            @error($slot) <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save hero</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </div>
    </form>
</div>
