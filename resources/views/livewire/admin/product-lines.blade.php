<div class="mx-auto max-w-4xl px-4 py-6" wire:key="product-lines">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Product lines</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                The Naara products shown on the homepage. Each is a slide with a deep modal (a friction → resolution → belonging arc). Live the moment you save.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="restoreDefaults" wire:confirm="Restore all product lines to their shipped defaults? This replaces your current edits."
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Restore defaults</button>
            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>

    @if ($uploadError)
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">{{ $uploadError }}</div>
    @endif

    <div class="space-y-5">
        @foreach ($products as $i => $p)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]" wire:key="prod-{{ $i }}">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon :name="$p['icon'] ?? 'signal'" class="h-5 w-5" /></span>
                        <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $p['title'] ?: 'Untitled' }}</span>
                        @if (! empty($p['is_draft']))
                            <span class="rounded-full bg-accent/15 px-2 py-0.5 text-[10px] font-bold uppercase text-accent-dark dark:text-accent" title="Copy awaiting owner approval">Draft copy</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="moveProduct({{ $i }}, -1)" aria-label="Move up" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5"><x-icon name="chevron-right" class="h-4 w-4 -rotate-90" /></button>
                        <button type="button" wire:click="moveProduct({{ $i }}, 1)" aria-label="Move down" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5"><x-icon name="chevron-right" class="h-4 w-4 rotate-90" /></button>
                        <button type="button" wire:click="removeProduct({{ $i }})" wire:confirm="Remove this product line?" aria-label="Remove" class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Title</span>
                        <input type="text" wire:model="products.{{ $i }}.title" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                        @error("products.{$i}.title") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Eyebrow</span>
                        <input type="text" wire:model="products.{{ $i }}.eyebrow" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                    </label>
                    <label class="block sm:col-span-2"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Summary (shown on the slide)</span>
                        <textarea rows="2" wire:model="products.{{ $i }}.summary" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white"></textarea>
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">CTA label</span>
                        <input type="text" wire:model="products.{{ $i }}.cta_label" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">CTA route name</span>
                        <input type="text" wire:model="products.{{ $i }}.cta_route" placeholder="catalogue / numbers / gift-cards" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                    </label>
                    <label class="flex items-center gap-2 sm:col-span-2">
                        <input type="checkbox" wire:model="products.{{ $i }}.is_draft" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-sm text-slate-600 dark:text-slate-300">Mark copy as draft (awaiting approval)</span>
                    </label>
                </div>

                {{-- Hero image --}}
                <div class="mt-4 flex items-center gap-4">
                    @if (! empty($p['hero_image']))
                        <img src="{{ $p['hero_image'] }}" alt="" class="h-16 w-24 rounded-lg object-cover ring-1 ring-slate-200 dark:ring-white/10">
                    @endif
                    <label class="text-sm text-slate-600 dark:text-slate-300">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Hero image</span>
                        <input type="file" wire:model="heroUploads.{{ $i }}" accept="image/webp,image/png,image/jpeg" class="text-xs">
                        <span wire:loading wire:target="heroUploads.{{ $i }}" class="ml-2 text-xs text-slate-400">Uploading…</span>
                    </label>
                </div>

                {{-- Body blocks (the 3-beat arc) --}}
                <div class="mt-5">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Modal body blocks</span>
                        <button type="button" wire:click="addBlock({{ $i }})" class="text-xs font-semibold text-primary dark:text-teal-300">+ Add block</button>
                    </div>
                    <div class="space-y-3">
                        @foreach ($p['modal_blocks'] ?? [] as $j => $block)
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-white/5 dark:bg-white/5" wire:key="block-{{ $i }}-{{ $j }}">
                                <div class="flex items-center gap-2">
                                    <input type="text" wire:model="products.{{ $i }}.modal_blocks.{{ $j }}.heading" placeholder="Heading" class="flex-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                                    <button type="button" wire:click="removeBlock({{ $i }}, {{ $j }})" aria-label="Remove block" class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10"><x-icon name="x" class="h-4 w-4" /></button>
                                </div>
                                <textarea rows="2" wire:model="products.{{ $i }}.modal_blocks.{{ $j }}.text" placeholder="Text" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white"></textarea>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Gallery --}}
                <div class="mt-5">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Modal gallery</span>
                        <label class="text-xs font-semibold text-primary dark:text-teal-300 cursor-pointer">+ Add image
                            <input type="file" wire:model="galleryUploads.{{ $i }}" accept="image/webp,image/png,image/jpeg" class="hidden">
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($p['modal_gallery'] ?? [] as $k => $img)
                            <div class="relative" wire:key="gal-{{ $i }}-{{ $k }}">
                                <img src="{{ $img }}" alt="" class="h-16 w-24 rounded-lg object-cover ring-1 ring-slate-200 dark:ring-white/10">
                                <button type="button" wire:click="removeGalleryImage({{ $i }}, {{ $k }})" aria-label="Remove image" class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white"><x-icon name="x" class="h-3 w-3" /></button>
                            </div>
                        @endforeach
                        <span wire:loading wire:target="galleryUploads.{{ $i }}" class="self-center text-xs text-slate-400">Uploading…</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" wire:click="addProduct" class="mt-5 w-full rounded-2xl border border-dashed border-slate-300 py-3 text-sm font-semibold text-slate-500 hover:bg-slate-50 dark:border-white/15 dark:text-slate-400 dark:hover:bg-white/5">
        + Add a product line
    </button>
</div>
