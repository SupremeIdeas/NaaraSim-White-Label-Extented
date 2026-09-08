<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Auth &amp; Footer</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">The media panel shown beside the login/register form, and the footer links across the site. “Supreme Ideas Agency” attribution always shows and can’t be removed.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{-- Auth panel --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                <x-icon name="image" class="h-4 w-4 text-primary" /> Sign-in media panel
            </h2>

            <div class="mt-4 mb-4">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Login treatment</label>
                <select wire:model="login_style" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="auto">Auto — your uploaded media if set, else the animated WebGL scene</option>
                    <option value="webgl">Always the animated WebGL scene</option>
                    <option value="image">Image / text only (lighter, faster — no WebGL)</option>
                </select>
                <p class="mt-1 text-xs text-slate-400">Both treatments stay supported; this only picks which one the login page shows.</p>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Panel type</label>
                    <select wire:model.live="media_type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <option value="image">Image (WebP / JPEG)</option>
                        <option value="video">Video (MP4 / WebM, muted loop)</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ $media_type === 'video' ? 'Video file (≤ 8 MB)' : 'Image file (≤ 8 MB)' }}
                    </label>
                    <input wire:model="media" type="file" accept="{{ $media_type === 'video' ? 'video/mp4,video/webm' : 'image/webp,image/jpeg,image/png' }}"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300">
                    <div wire:loading wire:target="media" class="mt-1 text-[11px] text-slate-400">Uploading…</div>
                    @error('media') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    @if ($hasMedia)
                        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">A panel is set. Upload to replace it.</p>
                    @else
                        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">None set — a branded gradient shows until you upload one.</p>
                    @endif
                </div>
                @if ($media_type === 'video')
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Video poster <span class="font-normal text-slate-400">(optional still)</span></label>
                        <input wire:model="poster" type="file" accept="image/webp,image/jpeg,image/png"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300">
                        @error('poster') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                @endif
            </div>

            <div class="mt-4 grid gap-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Headline</label>
                    <input wire:model="headline" type="text"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('headline') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Subtext</label>
                    <textarea wire:model="subtext" rows="2"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                    @error('subtext') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        {{-- Footer columns --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-icon name="grid" class="h-4 w-4 text-primary" /> Footer link columns
                </h2>
                <button type="button" wire:click="addColumn" @disabled(count($columns) >= 4)
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                    <x-icon name="check" class="h-3.5 w-3.5" /> Add column
                </button>
            </div>

            <div class="mt-4 space-y-5">
                @forelse ($columns as $ci => $column)
                    <div wire:key="col-{{ $ci }}" class="rounded-xl border border-slate-200 p-4 dark:border-[#2D4060]">
                        <div class="flex items-center gap-2">
                            <input wire:model="columns.{{ $ci }}.heading" type="text" placeholder="Section heading"
                                   class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            <button type="button" wire:click="removeColumn({{ $ci }})" class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40" aria-label="Remove column">
                                <x-icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                        @error("columns.$ci.heading") <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror

                        <div class="mt-3 space-y-2">
                            @foreach ($column['links'] as $li => $link)
                                <div wire:key="col-{{ $ci }}-link-{{ $li }}" class="flex items-center gap-2">
                                    <input wire:model="columns.{{ $ci }}.links.{{ $li }}.label" type="text" placeholder="Label"
                                           class="w-1/3 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                                    <input wire:model="columns.{{ $ci }}.links.{{ $li }}.url" type="text" placeholder="/path or https://…"
                                           class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                                    <button type="button" wire:click="removeLink({{ $ci }}, {{ $li }})" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-[#243352]" aria-label="Remove link">
                                        <x-icon name="x" class="h-4 w-4" />
                                    </button>
                                </div>
                            @endforeach
                            @error("columns.$ci.links.*.label") <span class="block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            @error("columns.$ci.links.*.url") <span class="block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            <button type="button" wire:click="addLink({{ $ci }})" class="text-xs font-medium text-primary hover:underline dark:text-teal-300">+ Add link</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 dark:text-slate-500">No columns — add one, or leave empty for a minimal footer.</p>
                @endforelse
            </div>
        </section>

        {{-- Legal row --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <x-icon name="shield" class="h-4 w-4 text-primary" /> Legal links <span class="font-normal text-slate-400">(bottom bar)</span>
                </h2>
                <button type="button" wire:click="addLegal" @disabled(count($legal) >= 6)
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                    <x-icon name="check" class="h-3.5 w-3.5" /> Add link
                </button>
            </div>
            <div class="mt-4 space-y-2">
                @foreach ($legal as $i => $link)
                    <div wire:key="legal-{{ $i }}" class="flex items-center gap-2">
                        <input wire:model="legal.{{ $i }}.label" type="text" placeholder="Label"
                               class="w-1/3 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <input wire:model="legal.{{ $i }}.url" type="text" placeholder="/legal or https://…"
                               class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <button type="button" wire:click="removeLegal({{ $i }})" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-[#243352]" aria-label="Remove legal link">
                            <x-icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
                @endforeach
                @error('legal.*.label') <span class="block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                @error('legal.*.url') <span class="block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
        </section>

        <button type="submit" wire:loading.attr="disabled" wire:target="save,media,poster"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Save changes</span>
            <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
        </button>
    </form>
</div>
