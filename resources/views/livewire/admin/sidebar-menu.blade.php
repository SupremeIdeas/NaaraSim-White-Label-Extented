<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Sidebar menu</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The global glass slide-out beside the notification bell. Legal pages, social handles and account deletion are always shown; add extra links and settings here.</p>
    </div>

    @if ($saved)
        <div class="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Always-present legal links (read-only reassurance). --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Always shown</h2>
        <p class="mb-3 text-xs text-slate-400">These can't be removed — app-store review needs them reachable.</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($legalLinks as $l)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-[#243352] dark:text-slate-300">
                    <x-icon name="shield" class="h-3.5 w-3.5" /> {{ $l['label'] }}
                </span>
            @endforeach
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-600 dark:bg-red-950/40 dark:text-red-300">Delete my account</span>
        </div>
    </div>

    {{-- Settings --}}
    <form wire:submit="saveSettings" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Settings</h2>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Ratings / reviews URL</label>
            <input type="url" wire:model="reviews_url" placeholder="https://…"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('reviews_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Links display mode</label>
                <select wire:model="display_mode" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="list">List</option>
                    <option value="grid">Grid</option>
                </select>
            </div>
            <label class="flex items-end gap-2 pb-1">
                <x-ui.switch wire:model="blog_widget" label="Blog widget" />
                <span class="text-sm text-slate-600 dark:text-slate-300">Show latest blog posts</span>
            </label>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Save settings</button>
        </div>
    </form>

    {{-- Custom links --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Custom links</h2>

        <div class="mb-4 space-y-2">
            @forelse ($links as $i => $link)
                <div wire:key="link-{{ $i }}" class="flex items-center gap-3 rounded-xl border border-slate-100 p-2.5 dark:border-[#243352]">
                    <x-icon :name="$link['icon'] ?: 'chevron-right'" class="h-4 w-4 shrink-0 text-primary" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $link['label'] }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ $link['url'] }}</p>
                    </div>
                    <button type="button" wire:click="removeLink({{ $i }})" wire:confirm="Remove this link?"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-400">No custom links yet.</p>
            @endforelse
        </div>

        <form wire:submit="addLink" class="space-y-3 rounded-xl bg-slate-50 p-4 dark:bg-[#182742]">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Label</label>
                    <input type="text" wire:model="new_label" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('new_label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Icon <span class="text-slate-400">(sprite name)</span></label>
                    <input type="text" wire:model="new_icon" placeholder="chevron-right"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">URL</label>
                    <input type="url" wire:model="new_url" placeholder="https://…"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('new_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Add link</button>
            </div>
        </form>
    </div>
</div>
