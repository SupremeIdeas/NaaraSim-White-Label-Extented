<div class="mx-auto max-w-5xl px-4 py-6" wire:key="preloader-studio">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Preloader Studio</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Give each kind of page its own loading animation. Brand-coloured by default, fully tunable, live the moment you save.
        </p>
    </div>

    {{-- Page-type selector --}}
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($pageTypes as $slug => $label)
            <button type="button" wire:click="loadType('{{ $slug }}')"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $type === $slug ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Inherit-from-default (non-default types only) --}}
    @if ($type !== 'default')
        <label class="mb-5 flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-[#16233d]">
            <input type="checkbox" wire:model.live="inherit" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Inherit from Default — use the same preloader as every other page</span>
        </label>
    @endif

    <div class="grid gap-6 @if (! ($type !== 'default' && $inherit)) lg:grid-cols-[1fr_18rem] @endif">
        <div @class(['space-y-6', 'pointer-events-none opacity-40' => $type !== 'default' && $inherit])>
            {{-- Enabled --}}
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model.live="enabled" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Show a preloader on these pages</span>
            </label>

            {{-- Preset gallery --}}
            <div>
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Choose a preset</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($presets as $slug => $meta)
                        <button type="button" wire:click="selectPreset('{{ $slug }}')" wire:key="preset-{{ $slug }}"
                                class="group relative flex h-28 flex-col items-center justify-center gap-1 overflow-hidden rounded-2xl border p-2 transition {{ $preset === $slug ? 'border-primary ring-2 ring-primary/40' : 'border-slate-200 hover:border-slate-300 dark:border-white/10 dark:hover:border-white/20' }} bg-[#0D1B2A]">
                            <div class="flex flex-1 items-center justify-center">
                                <div class="nx-pl nx-pl--{{ $slug }}" style="transform:scale(.5)">
                                    @includeIf('components.preloaders.'.$slug, ['cfg' => ['loading_text' => 'Naara']])
                                </div>
                            </div>
                            <span class="relative z-10 text-[10px] font-semibold text-white/90">{{ $meta['label'] }}</span>
                            @if (! empty($meta['heavy']))
                                <span class="absolute right-1.5 top-1.5 z-10 rounded-full bg-accent/90 px-1.5 py-0.5 text-[8px] font-bold uppercase text-white" title="GPU-heavy — best for short overlays">Heavy</span>
                            @endif
                            @if (! empty($meta['favorite']))
                                <span class="absolute left-1.5 top-1.5 z-10 text-accent" title="Supreme Ideas favorite"><x-icon name="star" class="h-3 w-3" /></span>
                            @endif
                        </button>
                    @endforeach
                </div>
                @error('preset') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Customization panel --}}
            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Customize</h2>

                {{-- Brand colours --}}
                <label class="flex items-center gap-3">
                    <input type="checkbox" wire:model.live="useBrandColor" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Use brand colours</span>
                </label>

                @unless ($useBrandColor)
                    <div class="flex flex-wrap gap-3 pl-7">
                        @for ($i = 0; $i < ($selectedMeta['c'] ?? 1); $i++)
                            <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                Colour {{ $i + 1 }}
                                <input type="color" wire:model.live="colors.{{ $i }}" class="h-8 w-10 cursor-pointer rounded border border-slate-200 bg-transparent dark:border-white/10">
                            </label>
                        @endfor
                    </div>
                @endunless

                {{-- Neutral toggle for allow_neutral presets (e.g. dot-blast) --}}
                @if (! empty($selectedMeta['allow_neutral']))
                    <label class="flex items-center gap-3">
                        <input type="checkbox" wire:model.live="useNeutral" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Use white / neutral instead of brand colour</span>
                    </label>
                @endif

                {{-- Loading text for text presets --}}
                @if (! empty($selectedMeta['text']))
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Loading text</span>
                        <input type="text" maxlength="24" wire:model.live="loadingText"
                               class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                        @error('loadingText') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </label>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- Size --}}
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Size</span>
                        <select wire:model.live="size" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                            <option value="sm">Small</option>
                            <option value="md">Medium</option>
                            <option value="lg">Large</option>
                        </select>
                    </label>

                    {{-- Speed --}}
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Speed <span class="text-slate-400">({{ number_format($speed, 1) }}×)</span></span>
                        <input type="range" min="0.5" max="2" step="0.1" wire:model.live="speed" class="w-full accent-primary">
                    </label>

                    {{-- Opacity --}}
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Opacity <span class="text-slate-400">({{ round($opacity * 100) }}%)</span></span>
                        <input type="range" min="0.2" max="1" step="0.05" wire:model.live="opacity" class="w-full accent-primary">
                    </label>

                    {{-- Background opacity --}}
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Background dim <span class="text-slate-400">({{ round($bgOpacity * 100) }}%)</span></span>
                        <input type="range" min="0.2" max="1" step="0.05" wire:model.live="bgOpacity" class="w-full accent-primary">
                    </label>
                </div>

                {{-- Background colour --}}
                <label class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                    Background colour
                    <input type="color" wire:model.live="bgColor" class="h-8 w-10 cursor-pointer rounded border border-slate-200 bg-transparent dark:border-white/10">
                    <button type="button" wire:click="$set('bgColor', null)" class="text-xs font-normal text-slate-400 underline">Use brand navy</button>
                </label>

                {{-- Blur --}}
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" wire:model.live="blur" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Blur the page behind</span>
                    </label>
                    @if ($blur)
                        <select wire:model.live="blurStyle" class="rounded-xl border border-slate-200 bg-white px-2 py-1 text-xs dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                            <option value="light">Light</option>
                            <option value="medium">Medium</option>
                            <option value="heavy">Heavy</option>
                        </select>
                    @endif
                </div>
            </div>

            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            @if ($saved === $type)
                <span class="ml-3 text-sm font-medium text-green-600 dark:text-green-400">Saved — live now.</span>
            @endif
        </div>

        {{-- Live preview pane (mirrors the real overlay) --}}
        @unless ($type !== 'default' && $inherit)
            @php($pv = $this->previewCfg())
            @php($pvVars = \App\Support\PreloaderSettings::resolveCssVars($pv))
            @php($pvBgRgb = $pv['bg_color'] ? \App\Support\BrandSettings::hexToChannels($pv['bg_color']) : null)
            @php($pvBg = $pvBgRgb ? 'rgb('.$pvBgRgb.' / '.$pv['bg_opacity'].')' : 'rgb(var(--brand-navy))')
            <div class="lg:sticky lg:top-6">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Live preview</h2>
                <div class="flex h-72 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10"
                     style="background:{{ $pvBg }};">
                    <div class="nx-pl nx-pl--{{ $preset }}" style="{{ $pvVars }}opacity:var(--nx-pl-opacity,1);transform:scale(var(--nx-pl-scale,1));" wire:key="pv-{{ $preset }}-{{ $useBrandColor ? 'b' : 'm' }}">
                        @includeIf('components.preloaders.'.$preset, ['cfg' => $pv])
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Preview uses your current settings — this is exactly what visitors will see.</p>
            </div>
        @endunless
    </div>
</div>
