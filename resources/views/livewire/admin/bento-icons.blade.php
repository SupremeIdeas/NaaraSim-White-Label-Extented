<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Bento icons</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">The 3D illustrated icon on every product card. Upload a replacement image and tune its opacity per card — changes go live everywhere with no deploy. Icons are transparent WebP/PNG, shown at their shipped default until you upload your own.</p>
    </div>

    @php($groups = [
        'showcase' => ['Homepage cards', 'The icon sits as a background watermark behind the text — opacity is the fade of that watermark (default 80%).'],
        'tile' => ['Action tiles', 'The icon sits in the foreground of the compact tiles (Browse by country / Check compatibility / Buy eSIM / Get number). Opacity default 100%.'],
    ])

    @foreach ($groups as $group => [$groupTitle, $groupHint])
        <div class="mb-4 mt-2">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $groupTitle }}</h2>
            <p class="text-xs text-slate-400 dark:text-slate-500">{{ $groupHint }}</p>
        </div>

        <div class="mb-8 space-y-4">
            @foreach ($cards as $key => $def)
                @continue($def['group'] !== $group)
                @php($icon = \App\Support\BentoIcons::icon($key))
                <div wire:key="bento-{{ $key }}"
                     x-data="{ op: @entangle('opacity.'.$key).live, sc: @entangle('scale.'.$key).live }"
                     class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        {{-- Live preview on a card-like surface (reflects size + opacity). --}}
                        <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-[#f0f7f7] dark:border-white/10 dark:from-[#1a2840] dark:to-[#10243c]">
                            @if ($icon)
                                <img src="{{ $icon }}" alt="{{ $def['label'] }}"
                                     class="h-14 w-14 object-contain transition-transform"
                                     :style="`opacity: ${op/100}; transform: scale(${sc})`">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-white/5">—</div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ $def['label'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $key }}</p>
                                </div>
                                @if ($saved === $key)
                                    <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-950/40 dark:text-green-300">Saved</span>
                                @endif
                            </div>

                            {{-- Upload --}}
                            <label class="mt-3 block text-xs font-medium text-slate-600 dark:text-slate-300">Replace icon (transparent WebP/PNG, ≤ 800 KB)</label>
                            <input type="file" wire:model="images.{{ $key }}" accept="image/webp,image/png,image/jpeg"
                                   class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-300 dark:file:bg-primary/20 dark:file:text-teal-200">
                            <div wire:loading wire:target="images.{{ $key }}" class="mt-1 text-xs text-slate-400">Uploading…</div>
                            @error('images.'.$key) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                            {{-- Opacity slider --}}
                            <div class="mt-4">
                                <div class="flex items-center justify-between text-xs font-medium text-slate-600 dark:text-slate-300">
                                    <span>Opacity</span>
                                    <span class="tabular-nums text-primary dark:text-teal-300" x-text="op + '%'"></span>
                                </div>
                                <input type="range" min="20" max="100" step="5" x-model.number="op"
                                       class="mt-1.5 w-full accent-primary">
                            </div>
                            @error('opacity.'.$key) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                            {{-- Size (boldness) slider --}}
                            <div class="mt-4">
                                <div class="flex items-center justify-between text-xs font-medium text-slate-600 dark:text-slate-300">
                                    <span>Size (boldness)</span>
                                    <span class="tabular-nums text-primary dark:text-teal-300" x-text="Number(sc).toFixed(2) + '×'"></span>
                                </div>
                                <input type="range" min="0.5" max="3" step="0.05" x-model.number="sc"
                                       class="mt-1.5 w-full accent-primary">
                            </div>
                            @error('scale.'.$key) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                            <div class="mt-4 flex items-center gap-2">
                                <button type="button" wire:click="save('{{ $key }}')" wire:loading.attr="disabled" wire:target="save('{{ $key }}'),images.{{ $key }}"
                                        class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                                    <span wire:loading.remove wire:target="save('{{ $key }}')">Save</span>
                                    <span wire:loading wire:target="save('{{ $key }}')">Saving…</span>
                                </button>
                                <button type="button" wire:click="revert('{{ $key }}')"
                                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-white/5">
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
