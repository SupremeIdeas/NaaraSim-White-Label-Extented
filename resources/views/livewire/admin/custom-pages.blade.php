<div class="mx-auto max-w-5xl">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Pages</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Author custom-HTML pages served at <code class="rounded bg-slate-100 px-1 dark:bg-[#243352]">/p/&lt;slug&gt;</code>.</p>
        </div>
        <button type="button" wire:click="newPage"
                class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
            <x-icon name="file-text" class="h-4 w-4" /> New page
        </button>
    </div>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
        {{-- List --}}
        <div class="space-y-2">
            @forelse ($pages as $page)
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]" wire:key="pg-{{ $page->id }}">
                    <div class="flex items-center justify-between gap-2">
                        <button type="button" wire:click="edit({{ $page->id }})" class="text-left">
                            <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $page->title }}</span>
                            <span class="block text-xs text-slate-400">/p/{{ $page->slug }}</span>
                        </button>
                        <div class="flex items-center gap-1.5">
                            @if ($page->is_published)
                                <a href="{{ url('/p/'.$page->slug) }}" target="_blank" rel="noopener" class="rounded p-1.5 text-slate-400 hover:text-primary" title="View">
                                    <x-icon name="link" class="h-4 w-4" />
                                </a>
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700 dark:bg-green-950/50 dark:text-green-300">Live</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Draft</span>
                            @endif
                            <button type="button" wire:click="delete({{ $page->id }})" wire:confirm="Delete this page permanently?"
                                    class="rounded p-1.5 text-red-400 hover:text-red-600" title="Delete">
                                <x-icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400 dark:border-[#2D4060]">No pages yet.</div>
            @endforelse
        </div>

        {{-- Editor --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $editingId ? 'Edit page' : 'New page' }}</h2>
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Title</label>
                    <input type="text" wire:model="title" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Slug <span class="text-slate-400">(URL: /p/…)</span></label>
                    <input type="text" wire:model="slug" placeholder="auto from title" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('slug') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Custom HTML</label>
                    <textarea wire:model="html" rows="12" spellcheck="false"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-900 dark:border-[#2D4060] dark:bg-[#0D1B2A] dark:text-slate-100"
                              placeholder="&lt;section class=&quot;text-center&quot;&gt;&#10;  &lt;h1&gt;Your page&lt;/h1&gt;&#10;&lt;/section&gt;"></textarea>
                    <p class="mt-1 text-[11px] text-slate-400">Tailwind classes work. Inline &lt;script&gt; is blocked by the site security policy (visitors are protected).</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Meta description (SEO)</label>
                    <input type="text" wire:model="meta" maxlength="200" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div class="flex flex-wrap gap-4 pt-1">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" wire:model="isPublished" class="rounded text-primary"> Published</label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" wire:model="inNav" class="rounded text-primary"> Show in menu</label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" wire:model="fullWidth" class="rounded text-primary"> Full-width</label>
                </div>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <x-icon name="check" class="h-4 w-4" /> Save page
                </button>
            </div>
        </div>
    </div>
</div>
