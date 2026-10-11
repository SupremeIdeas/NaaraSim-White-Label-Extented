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

                    {{-- Your own animation (GIF / WebP / Lottie JSON, per light + dark mode) --}}
                    <button type="button" wire:click="selectPreset('custom')" wire:key="preset-custom"
                            class="group relative flex h-28 flex-col items-center justify-center gap-1 overflow-hidden rounded-2xl border border-dashed p-2 transition {{ $isCustom ? 'border-primary ring-2 ring-primary/40' : 'border-slate-300 hover:border-slate-400 dark:border-white/20 dark:hover:border-white/30' }} bg-[#0D1B2A]">
                        <span class="flex flex-1 items-center justify-center text-white/80"><x-icon name="upload" class="h-7 w-7" /></span>
                        <span class="relative z-10 text-[10px] font-semibold text-white/90">Your own animation</span>
                        @if ($custom !== [])
                            <span class="absolute right-1.5 top-1.5 z-10 rounded-full bg-emerald-500/90 px-1.5 py-0.5 text-[8px] font-bold uppercase text-white">Uploaded</span>
                        @endif
                    </button>
                </div>
                @error('preset') <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            {{-- Upload panel: one file per mode --}}
            @if ($isCustom)
                <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]" wire:key="custom-panel">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Your own animation</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Upload a GIF, animated WebP or Lottie JSON for each mode. Visitors get the file that matches their light or dark mode;
                            if you only upload one, it is used for both. GIF and WebP up to {{ $customMaxImageKb / 1024 }} MB, Lottie up to {{ $customMaxLottieKb }} KB.
                            Lottie files must be exported without expressions and with images embedded.
                        </p>
                    </div>

                    @if ($customError)
                        <p class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300" role="alert">{{ $customError }}</p>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['light' => 'Light mode', 'dark' => 'Dark mode'] as $variant => $variantLabel)
                            @php($meta = $custom[$variant] ?? null)
                            @php($field = 'upload'.ucfirst($variant))
                            <div class="rounded-2xl border border-slate-200 p-3 dark:border-white/10" wire:key="custom-{{ $variant }}">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $variantLabel }}</span>
                                    @if ($meta)
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500 dark:bg-white/10 dark:text-slate-300">{{ $meta['kind'] === 'lottie' ? 'Lottie' : strtoupper($meta['kind']) }}</span>
                                    @endif
                                </div>
                                <div class="flex h-32 items-center justify-center overflow-hidden rounded-xl {{ $variant === 'dark' ? 'bg-[#0D1B2A]' : 'bg-white ring-1 ring-slate-200' }}"
                                     @if ($variant === 'light') style="background:#fff" @endif>
                                    @if ($meta)
                                        <div class="nx-pl nx-pl--custom" style="transform:scale(.75)"
                                             x-data x-init="$nextTick(() => document.dispatchEvent(new Event('nx:lottie-init')))"
                                             wire:key="custom-pv-{{ $variant }}-{{ $meta['id'] }}">
                                            @include('components.preloaders.custom', ['cfg' => ['custom' => [$variant => $meta]], 'previewMode' => $variant])
                                        </div>
                                    @else
                                        <span class="px-3 text-center text-xs text-slate-400">
                                            {{ ($custom !== []) ? 'Using the other mode\'s file' : 'Nothing uploaded yet' }}
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-primary px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-dark">
                                        <x-icon name="upload" class="h-4 w-4" />
                                        <span wire:loading.remove wire:target="{{ $field }}">{{ $meta ? 'Replace' : 'Upload file' }}</span>
                                        <span wire:loading wire:target="{{ $field }}">Uploading…</span>
                                        <input type="file" class="sr-only" wire:model="{{ $field }}" accept="{{ $customAccept }}">
                                    </label>
                                    @if ($meta)
                                        <button type="button" wire:click="removeCustom('{{ $variant }}')" wire:loading.attr="disabled"
                                                class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Remove</button>
                                    @endif
                                </div>
                                @error($field) <p class="mt-2 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Customization panel --}}
            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Customize</h2>

                {{-- Brand colours (artwork you upload keeps its own colours) --}}
                @unless ($isCustom)
                <label class="flex items-center gap-3">
                    <input type="checkbox" wire:model.live="useBrandColor" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Use brand colours</span>
                </label>
                @endunless

                @unless ($useBrandColor || $isCustom)
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
                    <button type="button" wire:click="$set('bgColor', null)" class="text-xs font-normal text-slate-400 underline">{{ $isCustom ? 'Follow light / dark mode' : 'Use brand navy' }}</button>
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
                @if ($isCustom)
                    {{-- Both modes side by side, each on the surface a visitor in that mode would see. --}}
                    <div class="grid gap-3">
                        @foreach (['light' => '#ffffff', 'dark' => '#0D1B2A'] as $mode => $surface)
                            <div>
                                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ ucfirst($mode) }} mode</span>
                                <div class="flex h-36 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10" style="background:{{ $pvBgRgb ? $pvBg : $surface }}">
                                    @if ($custom !== [])
                                        <div class="nx-pl nx-pl--custom" style="{{ $pvVars }}opacity:var(--nx-pl-opacity,1);transform:scale(var(--nx-pl-scale,1));"
                                             x-data x-init="$nextTick(() => document.dispatchEvent(new Event('nx:lottie-init')))"
                                             wire:key="pv-custom-{{ $mode }}-{{ md5(json_encode($custom).$size) }}">
                                            @include('components.preloaders.custom', ['cfg' => $pv, 'previewMode' => $mode])
                                        </div>
                                    @else
                                        <span class="px-4 text-center text-xs text-slate-400">Upload a file to preview it</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                <div class="flex h-72 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10"
                     style="background:{{ $pvBg }};">
                    <div class="nx-pl nx-pl--{{ $preset }}" style="{{ $pvVars }}opacity:var(--nx-pl-opacity,1);transform:scale(var(--nx-pl-scale,1));" wire:key="pv-{{ $preset }}-{{ $useBrandColor ? 'b' : 'm' }}">
                        @includeIf('components.preloaders.'.$preset, ['cfg' => $pv])
                    </div>
                </div>
                @endif
                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Preview uses your current settings — this is exactly what visitors will see.</p>
            </div>
        @endunless
    </div>
</div>
