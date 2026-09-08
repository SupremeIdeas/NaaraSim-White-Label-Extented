<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Branding</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Upload your logos and set your brand name — they show across the whole platform instantly, no redeploy.
        PNG (transparent), JPG, WebP or SVG. Upload a <b>light</b> version (for light backgrounds) and a
        <b>dark</b> version (for dark mode). Leave a slot empty to keep the current file.
    </p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Brand name</label>
            <input type="text" wire:model="brand_name" placeholder="NaaraSim"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('brand_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        @php
            $groups = [
                ['Naara family logo', [['family_light', 'Light background', 'family_light', false], ['family_dark', 'Dark background', 'family_dark', true]], 'The umbrella “Naara” mark — shown on the home dashboard, the marketing/front-end site, and the Aurora welcome screen. Clicking it always returns to the dashboard.'],
                ['NaaraSim product logo', [['product_light', 'Light background', 'product_light', false], ['product_dark', 'Dark background', 'product_dark', true]], 'The connectivity mark — shown in the eSIM and Numbers sections and their inner pages.'],
                ['Naara Gift store logo', [['gift_light', 'Light background', 'gift_light', false], ['gift_dark', 'Dark background', 'gift_dark', true]], 'A separate mark just for the Naara Gift storefront. Leave blank to use the gift icon + "Naara Gift" wordmark.'],
                ['Supreme Ideas Agency logo', [['agency_light', 'Light background', 'agency_light', false], ['agency_dark', 'Dark background', 'agency_dark', true]], 'The "from Supreme Ideas" endorsement mark.'],
            ];
        @endphp

        @foreach ($groups as [$title, $slots, $hint])
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $title }}</h2>
                <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">{{ $hint }} Use a transparent PNG/SVG; both variants render at the same height, so keep them the same proportions.</p>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($slots as [$field, $label, $brandKey, $darkPreview])
                        @php $current = $brand[$brandKey] ?? ''; @endphp
                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ $label }}</label>
                            <div class="mb-2 flex h-20 items-center justify-center overflow-hidden rounded-lg border border-slate-200 p-3 dark:border-[#2D4060] {{ $darkPreview ? 'bg-navy' : 'bg-slate-50' }}">
                                @if ($this->{$field})
                                    <img src="{{ $this->{$field}->temporaryUrl() }}" class="max-h-14 w-auto object-contain">
                                @elseif ($current)
                                    <img src="{{ $current }}" class="max-h-14 w-auto object-contain">
                                @else
                                    <span class="text-xs {{ $darkPreview ? 'text-slate-400' : 'text-slate-400' }}">No logo yet</span>
                                @endif
                            </div>
                            <input type="file" wire:model="{{ $field }}" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                   class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-primary-dark dark:text-slate-400">
                            <div wire:loading wire:target="{{ $field }}" class="mt-1 text-[11px] text-slate-400">Uploading…</div>
                            @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Favicon / app icon</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">A square image (512×512 recommended) for the browser tab and installed app icon.</p>
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-[#2D4060] dark:bg-navy">
                    @if ($this->favicon)
                        <img src="{{ $this->favicon->temporaryUrl() }}" class="h-full w-full object-contain">
                    @elseif ($brand['favicon'] ?? '')
                        <img src="{{ $brand['favicon'] }}" class="h-full w-full object-contain">
                    @else
                        <x-icon name="image" class="h-6 w-6 text-slate-300" />
                    @endif
                </div>
                <input type="file" wire:model="favicon" accept="image/png,image/webp"
                       class="block text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-primary-dark dark:text-slate-400">
            </div>
            @error('favicon') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Premium dashboard hero backgrounds (owner request). Optional light +
             dark art behind the dashboard greeting, under a gradient overlay.
             Leave blank to keep the default heading. --}}
        <div class="rounded-xl border border-slate-200 p-4 dark:border-[#2D4060]">
            <div class="mb-1 flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Dashboard home hero <span class="font-normal text-slate-400">(optional)</span></p>
                <div class="flex items-center gap-3">
                    {{-- On/off switch: hide the hero image without deleting the
                         uploaded art (owner request). --}}
                    <label class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                        <x-ui.switch wire:model="hero_enabled" label="Show hero image on dashboard" /> Show
                    </label>
                    @if (\App\Support\HeroBackground::isSet())
                        <button type="button" wire:click="removeHero" wire:confirm="Remove the hero images and return to the title-only heading?"
                                class="text-xs font-medium text-red-600 hover:underline">Remove image</button>
                    @endif
                </div>
            </div>
            <p class="mb-3 text-[11px] text-slate-400">Shown as a <strong>real image</strong> under the hero title on the customer dashboard home, above the eSIM / Number buttons. <strong>WebP or JPG, 1600×800px recommended (2:1) — will crop to fit</strong>, under 600&nbsp;KB. Switches automatically with the user’s light/dark theme. Leave blank for a clean title-only header.</p>

            {{-- Headline override + size (owner request). Blank = keep the
                 shipped "My Connectivity"; the size preset scales it up/down
                 without touching any other layout. --}}
            <div class="mb-4 grid gap-4 sm:grid-cols-[1fr,10rem]">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Hero title</label>
                    <input type="text" wire:model="hero_title" maxlength="40"
                           placeholder="{{ \App\Support\HeroBackground::DEFAULT_TITLE }}"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400">The big headline above the description. Leave blank for the default. The first word renders plain; the rest renders in the accent gradient.</p>
                    @error('hero_title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Title size</label>
                    <select wire:model="hero_title_size"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @foreach (\App\Support\HeroBackground::TITLE_SIZES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('hero_title_size') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- CTA button size override (owner request): the "Buy eSIM" /
                 "Get Number" pills below the hero. 'md' is the shipped size. --}}
            <div class="mb-4 grid gap-4 sm:grid-cols-[1fr,10rem]">
                <p class="self-center text-[11px] text-slate-400">Size of the "Buy eSIM" / "Get Number" buttons under the hero title.</p>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Button size</label>
                    <select wire:model="hero_cta_size"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @foreach (\App\Support\HeroBackground::CTA_SIZES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('hero_cta_size') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Description line under the title (BUILD-13 §3). --}}
            <div class="mb-4">
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Description line under the title</label>
                <input type="text" wire:model="hero_description" maxlength="120"
                       placeholder="{{ \App\Support\HeroBackground::DEFAULT_DESCRIPTION }}"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                <p class="mt-1 text-[11px] text-slate-400">One short sentence. Leave blank to use the default.</p>
                @error('hero_description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([['hero_light', 'Light mode', \App\Support\HeroBackground::light()], ['hero_dark', 'Dark mode', \App\Support\HeroBackground::dark()]] as [$field, $label, $current])
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">{{ $label }}</label>
                        {{-- Preview mirrors the dashboard's real-image treatment: a genuine
                             2:1 image block, not a faded background layer. --}}
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

        {{-- Logo sizing — scale each mark to taste (owner request). Live preview
             follows the slider; the chosen scale applies on Save, everywhere the
             mark shows. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Logo sizing</h2>
            <p class="mb-4 mt-0.5 text-xs text-slate-500 dark:text-slate-400">Scale each logo from 50% to 200%. The preview updates as you drag; the size applies across the platform when you save.</p>
            <div class="space-y-5">
                @foreach ([
                    ['family', 'Naara family', 'family'],
                    ['product', 'NaaraSim', 'product'],
                    ['gift', 'Naara Gift', 'gift'],
                ] as [$var, $label, $model])
                    @php($previewSrc = \App\Support\BrandSettings::resolvedLogo($var, 'light') ?: '/brand/naara-'.($var === 'product' ? 'family' : $var).'-light.png')
                    <div x-data="{ s: @entangle('logo_scale_'.$model).live }" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-5">
                        <div class="sm:w-40">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}</span>
                                <span class="text-xs font-semibold text-primary dark:text-teal-300" x-text="Math.round(s*100)+'%'"></span>
                            </div>
                            <input type="range" min="0.5" max="2" step="0.05" x-model.number="s"
                                   class="mt-1 w-full accent-primary">
                        </div>
                        {{-- Live preview box --}}
                        <div class="flex h-16 flex-1 items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 px-4 dark:border-[#2D4060] dark:bg-[#243352]">
                            <img src="{{ $previewSrc }}" alt="{{ $label }} preview"
                                 class="h-9 w-auto object-contain" :style="`transform: scale(${s}); transform-origin: left center`">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save branding
            </button>
        </div>
    </form>

    {{-- Brand theme (Module 26): colours, control roundness, preloader --}}
    <form wire:submit="saveTheme" class="mt-8 space-y-6 border-t border-slate-200 pt-8 dark:border-[#2D4060]">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Brand colours &amp; style</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Recolours the entire platform — buttons, links, accents, everything — instantly, no rebuild.</p>
            </div>
            <button type="button" wire:click="resetTheme" wire:confirm="Reset brand colours and roundness to the NaaraSim defaults?"
                    class="text-xs font-medium text-slate-400 underline hover:text-red-500">Reset to defaults</button>
        </div>

        {{-- White-label brand word — swaps "Naara" for a new business name in
             product/sub-brand names (e.g. "Naara Rent" → "{word} Rent"). --}}
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-[#2D4060] dark:bg-[#243352]/40">
            <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Brand word (white-label)</label>
            <input type="text" wire:model.live="brand_word" maxlength="40" placeholder="Naara"
                   class="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('brand_word') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                Reselling the platform to a new business? Set their name here — every product name swaps automatically, e.g.
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ trim($brand_word ?: 'Naara') }} Rent</span>,
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ trim($brand_word ?: 'Naara') }} Line</span>,
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ trim($brand_word ?: 'Naara') }} Verify</span>.
            </p>
        </div>

        {{-- 25 curated palette presets (+ the custom fields below). One click loads
             a palette into the colour fields; Save theme applies it sitewide. --}}
        <div>
            <p class="mb-2 text-xs font-semibold text-slate-600 dark:text-slate-300">Palette presets</p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach (\App\Support\BrandSettings::PALETTES as $pName => $pColors)
                    <button type="button" wire:click="applyPalette(@js($pName))"
                            class="group flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-left transition hover:border-primary/50 hover:shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                        <span class="flex shrink-0 -space-x-1">
                            <span class="h-5 w-5 rounded-full ring-2 ring-white dark:ring-[#1A2840]" style="background:{{ $pColors['primary'] }}"></span>
                            <span class="h-5 w-5 rounded-full ring-2 ring-white dark:ring-[#1A2840]" style="background:{{ $pColors['accent'] }}"></span>
                            <span class="h-5 w-5 rounded-full ring-2 ring-white dark:ring-[#1A2840]" style="background:{{ $pColors['action'] }}"></span>
                        </span>
                        <span class="min-w-0 truncate text-[11px] font-medium text-slate-600 group-hover:text-primary dark:text-slate-300">{{ $pName }}</span>
                    </button>
                @endforeach
            </div>
            <p class="mt-2 text-[11px] text-slate-400">Or set any colours by hand below for a fully custom palette.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['color_primary', 'Primary (teal)', $color_primary],
                ['color_accent', 'Accent (gold)', $color_accent],
                ['color_navy', 'Dark surface (navy)', $color_navy],
                ['color_action', 'Action (coral)', $color_action],
            ] as [$field, $label, $val])
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model.live="{{ $field }}" class="h-9 w-11 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-0.5 dark:border-[#2D4060] dark:bg-[#243352]">
                        <input type="text" wire:model.live="{{ $field }}" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 font-mono text-xs uppercase text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                    @error($field) <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Control roundness</label>
                <select wire:model="radius" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="0rem">Square</option>
                    <option value="0.25rem">Subtle</option>
                    <option value="0.5rem">Default</option>
                    <option value="0.75rem">Rounded</option>
                    <option value="1rem">Pill-ish</option>
                </select>
            </div>
            <label class="flex items-end justify-between gap-4">
                <span>
                    <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">Loading screen</span>
                    <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Show a brand-coloured loader while each page loads.</span>
                </span>
                <x-ui.switch wire:model="preloader_enabled" label="Show loading screen" class="mb-1" />
            </label>
            <div class="transition-opacity" x-data x-bind:class="$wire.preloader_enabled ? '' : 'pointer-events-none opacity-50'">
                <label class="block text-sm font-medium text-slate-800 dark:text-slate-100">Loader style</label>
                <p class="mt-0.5 mb-1 text-xs text-slate-500 dark:text-slate-400">The <span class="font-medium">pulsing logo</span> uses your uploaded favicon.</p>
                <select wire:model="preloader_style"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="pulse-logo">Pulsing logo (recommended)</option>
                    <option value="spinner">Spinner ring</option>
                    <option value="bars">Bars</option>
                    <option value="progress">Progress bar</option>
                </select>
            </div>
        </div>

        {{-- Live preview --}}
        <div class="rounded-2xl border border-slate-200 p-5 dark:border-[#2D4060]">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Live preview</p>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" style="background:{{ $color_primary }};border-radius:calc({{ $radius }} + 0.25rem)" class="px-4 py-2 text-sm font-semibold text-white">Primary button</button>
                <button type="button" style="color:{{ $color_primary }};box-shadow:inset 0 0 0 1.5px {{ $color_primary }};border-radius:calc({{ $radius }} + 0.25rem)" class="bg-transparent px-4 py-2 text-sm font-semibold">Ghost</button>
                <span style="background:{{ $color_accent }};border-radius:999px" class="px-3 py-1 text-xs font-bold text-white">Accent tag</span>
                <span style="background:{{ $color_action }};border-radius:999px" class="px-3 py-1 text-xs font-bold text-white">Action</span>
                <span style="background:{{ $color_navy }};border-radius:{{ $radius }}" class="px-4 py-2 text-xs font-medium text-white">Dark surface</span>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveTheme"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="saveTheme" class="inline-flex items-center gap-2"><x-icon name="badge-check" class="h-4 w-4" /> Save theme</span>
                <span wire:loading wire:target="saveTheme" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </div>
    </form>

    {{-- Site-wide font system (owner request): pick a Google Font or upload a
         custom web font, per slot. Left blank, the shipped Naara default
         (Supreme Display for titles, Didact Gothic for body) stays untouched —
         this whole section is a purely additive override. --}}
    <form wire:submit="saveFonts" class="mt-8 space-y-6 border-t border-slate-200 pt-8 dark:border-[#2D4060]">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Fonts</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Pick a Google Font or upload a custom web font for titles and body text, applied instantly across the whole platform. Leave a slot on "Naara default" to keep the shipped look.</p>
            </div>
            <button type="button" wire:click="resetFonts" wire:confirm="Reset both fonts to the Naara defaults?"
                    class="text-xs font-medium text-slate-400 underline hover:text-red-500">Reset to defaults</button>
        </div>

        @php($fontSlots = [
            ['display', 'Titles & headings', 'Used for every heading and the wordmark. Default: Supreme Display.'],
            ['sans', 'Body text', 'Used for paragraphs, labels and buttons across the platform. Default: Didact Gothic.'],
        ])
        @foreach ($fontSlots as [$slot, $label, $hint])
            @php($sourceField = "font_{$slot}_source")
            @php($googleField = "font_{$slot}_google")
            @php($customField = "font_{$slot}_custom")
            @php($currentCustomUrl = $brand["font_{$slot}_custom"] ?? '')
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]" x-data="{ source: @entangle($sourceField) }">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $label }}</h3>
                <p class="mb-3 mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>

                <div class="mb-4 flex flex-wrap gap-2">
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium dark:border-[#2D4060]" :class="source === '' ? 'border-primary bg-primary/10 text-primary' : 'text-slate-500 dark:text-slate-400'">
                        <input type="radio" wire:model="{{ $sourceField }}" value="" class="hidden"> Naara default
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium dark:border-[#2D4060]" :class="source === 'google' ? 'border-primary bg-primary/10 text-primary' : 'text-slate-500 dark:text-slate-400'">
                        <input type="radio" wire:model="{{ $sourceField }}" value="google" class="hidden"> Google Font
                    </label>
                    <label class="flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium dark:border-[#2D4060]" :class="source === 'custom' ? 'border-primary bg-primary/10 text-primary' : 'text-slate-500 dark:text-slate-400'">
                        <input type="radio" wire:model="{{ $sourceField }}" value="custom" class="hidden"> Upload font
                    </label>
                </div>

                <div x-show="source === 'google'" x-cloak>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Google Font name</label>
                    <input type="text" wire:model="{{ $googleField }}" placeholder="e.g. Poppins" maxlength="60"
                           class="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400">Exact name as it appears on <span class="font-medium">fonts.google.com</span> — letters, numbers and spaces only.</p>
                    @error($googleField) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div x-show="source === 'custom'" x-cloak>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Font file</label>
                    <input type="file" wire:model="{{ $customField }}" accept=".woff2,.woff,.ttf,.otf"
                           class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-primary-dark dark:text-slate-400">
                    <div wire:loading wire:target="{{ $customField }}" class="mt-1 text-[11px] text-slate-400">Uploading…</div>
                    @if ($currentCustomUrl)
                        <p class="mt-1 text-[11px] text-slate-400">Current file: <a href="{{ $currentCustomUrl }}" target="_blank" class="underline">view</a>. Upload a new one to replace it.</p>
                    @endif
                    <p class="mt-1 text-[11px] text-slate-400">WOFF2, WOFF, TTF or OTF — up to 2&nbsp;MB.</p>
                    @error($customField) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveFonts"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="saveFonts" class="inline-flex items-center gap-2"><x-icon name="badge-check" class="h-4 w-4" /> Save fonts</span>
                <span wire:loading wire:target="saveFonts" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </div>
    </form>
</div>
