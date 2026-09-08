<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Numbers cards</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">The six landing bento cards. Edit copy, badge, image and bullets, or toggle a card off — no deploy. The layout order is fixed.</p>
    </div>

    @if ($saved)
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-300">{{ $saved }}</div>
    @endif

    {{-- Platform-wide Contacts default view (users can still toggle their own). --}}
    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="mb-1 text-sm font-bold text-slate-900 dark:text-white">Contacts default view</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">The view new users see first in their contact book. Each user can still switch it themselves — this only sets the starting point.</p>
        <div class="flex items-center gap-3">
            <select wire:model="contacts_default_view" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                <option value="list">List</option>
                <option value="grid">Grid</option>
            </select>
            <button type="button" wire:click="saveContactsView" wire:loading.attr="disabled" wire:target="saveContactsView"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">Save</button>
        </div>
        @error('contacts_default_view') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="space-y-4">
        @foreach ($order as $key)
            @php($f = $form[$key])
            <div wire:key="card-{{ $key }}" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <img src="{{ $f['image_path'] ?: \App\Support\NumbersBento::defaults()[$key]['image'] }}" class="h-12 w-16 shrink-0 rounded-lg object-cover">
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">{{ $f['title'] }}</p>
                            <p class="text-xs text-slate-400">{{ $key }}</p>
                        </div>
                    </div>
                    {{-- Visibility toggle --}}
                    <button type="button" wire:click="toggle('{{ $key }}')"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $f['is_active'] ? 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300' : 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-400' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $f['is_active'] ? 'bg-green-500' : 'bg-slate-400' }}"></span>
                        {{ $f['is_active'] ? 'Visible' : 'Hidden' }}
                    </button>
                </div>

                @if ($saved === $key)
                    <div class="mb-3 flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2 text-xs text-green-700 dark:bg-green-950/40 dark:text-green-300">
                        <x-icon name="badge-check" class="h-4 w-4" /> Saved.
                    </div>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Badge (optional)</label>
                        <input type="text" wire:model="form.{{ $key }}.badge_label" maxlength="24" placeholder="e.g. POPULAR"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Title</label>
                        <input type="text" wire:model="form.{{ $key }}.title" maxlength="60"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @error("form.{$key}.title") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Subtitle</label>
                    <textarea wire:model="form.{{ $key }}.subtitle" rows="2" maxlength="500"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                    @error("form.{$key}.subtitle") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if (in_array($key, \App\Support\NumbersBento::TOGGLABLE, true))
                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Opens as</label>
                        <select wire:model="form.{{ $key }}.display_mode"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100 sm:w-64">
                            <option value="modal">Modal (pop-up sheet)</option>
                            <option value="page">Dedicated page</option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">A dedicated page replaces the bento grid with this flow full-page, like the eSIM catalogue's purchase screen — the flow itself is unchanged either way.</p>
                    </div>
                @endif

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Bullets (one per line, max 4)</label>
                        <textarea wire:model="form.{{ $key }}.bullets" rows="3"
                                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                        @if ($key === 'verify')<p class="mt-1 text-[11px] text-slate-400">A live “+N more” is appended automatically from the service catalogue.</p>@endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Replace image (WebP/JPG/PNG, ≤800&nbsp;KB)</label>
                        <input type="file" wire:model="images.{{ $key }}" accept="image/webp,image/jpeg,image/png"
                               class="block w-full text-[11px] text-slate-500 file:mr-2 file:rounded file:border-0 file:bg-primary/10 file:px-2 file:py-1 file:text-[11px] file:font-medium file:text-primary">
                        @error("images.{$key}") <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="button" wire:click="save('{{ $key }}')" wire:loading.attr="disabled" wire:target="save('{{ $key }}'),images.{{ $key }}"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="save('{{ $key }}')">Save card</span>
                        <span wire:loading wire:target="save('{{ $key }}')" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>
