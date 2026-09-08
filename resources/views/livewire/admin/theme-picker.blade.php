<div class="mx-auto max-w-5xl">
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Theme</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Pick one of the 20 skins for the whole platform. This changes colours, radius and typography
            for every user — the codebase, features and prices are untouched. It applies on the next page load.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($presets as $p)
            @php($t = $p['tokens']['colors'] ?? [])
            <div wire:key="theme-{{ $p['slug'] }}"
                 @class([
                    'relative flex flex-col overflow-hidden rounded-2xl border bg-white p-4 transition dark:bg-[#1A2840]',
                    'border-primary ring-2 ring-primary/40 dark:border-teal-400' => $p['slug'] === $active,
                    'border-slate-200 dark:border-[#2D4060]' => $p['slug'] !== $active,
                 ])>
                {{-- Swatch strip — the preset's core palette. --}}
                <div class="mb-3 flex h-14 overflow-hidden rounded-xl ring-1 ring-black/5 dark:ring-white/10">
                    @foreach (['primary', 'primary_dark', 'accent', 'navy', 'action'] as $key)
                        @if (! empty($t[$key]))
                            <span class="flex-1" style="background: rgb({{ $t[$key] }})" title="{{ $key }}"></span>
                        @endif
                    @endforeach
                </div>

                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $p['name'] }}</h2>
                        <p class="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $p['persona'] }}</p>
                    </div>
                    @if ($p['is_built_in'])
                        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500 dark:bg-white/10 dark:text-slate-300">Built-in</span>
                    @endif
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($p['slug'] === $active)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 px-3 py-2 text-sm font-semibold text-primary dark:bg-teal-500/15 dark:text-teal-300">
                            <x-icon name="check" class="h-4 w-4" /> Active
                        </span>
                    @else
                        <button type="button" wire:click="apply('{{ $p['slug'] }}')"
                                wire:confirm="Apply “{{ $p['name'] }}” to the live platform? Every logged-in user will see it on their next page load."
                                wire:loading.attr="disabled" wire:target="apply('{{ $p['slug'] }}')"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                            <x-icon name="check" class="h-4 w-4" wire:loading.remove wire:target="apply('{{ $p['slug'] }}')" />
                            <svg wire:loading wire:target="apply('{{ $p['slug'] }}')" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            Apply
                        </button>
                    @endif
                    <button type="button" wire:click="editHero('{{ $p['slug'] }}')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                        <x-icon name="image" class="h-4 w-4" /> Hero images
                    </button>
                    <button type="button" wire:click="editSections('{{ $p['slug'] }}')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                        <x-icon name="layers" class="h-4 w-4" /> Sections
                    </button>
                    <button type="button" wire:click="editColors('{{ $p['slug'] }}')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                        <x-icon name="settings" class="h-4 w-4" /> Colours
                        @if (! empty($p['color_overrides']))
                            <span class="ml-0.5 h-1.5 w-1.5 rounded-full bg-primary" title="Custom colours applied"></span>
                        @endif
                    </button>
                    <button type="button" wire:click="editHeader('{{ $p['slug'] }}')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                        <x-icon name="panel-top" class="h-4 w-4" /> Header
                        @if (! empty($p['header_settings']))
                            <span class="ml-0.5 h-1.5 w-1.5 rounded-full bg-primary" title="Custom header applied"></span>
                        @endif
                    </button>
                    @if (\App\Support\LandingHeroLibrary::has($p['section_styles']['landing_hero'] ?? 'default'))
                        <button type="button" wire:click="editLanding('{{ $p['slug'] }}')"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                            <x-icon name="file-text" class="h-4 w-4" /> Landing page
                        </button>
                    @endif
                    @foreach ([
                        ['about_page', 'About page'],
                        ['how_it_works_page', 'How It Works page'],
                        ['contact_page', 'Contact page'],
                    ] as [$pageKey, $pageLabel])
                        @if (\App\Support\ThemePageLibrary::has($pageKey, $p['section_styles'][$pageKey] ?? 'default'))
                            <button type="button" wire:click="editPage('{{ $p['slug'] }}', '{{ $pageKey }}')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                <x-icon name="file-text" class="h-4 w-4" /> {{ $pageLabel }}
                            </button>
                        @endif
                    @endforeach
                </div>
                <span class="mt-2 text-[11px] uppercase tracking-wide text-slate-400">{{ str_replace('_', ' ', $p['icon_family']['style'] ?? 'sprite') }} icons</span>
            </div>
        @endforeach
    </div>

    <p class="mt-5 text-xs text-slate-400 dark:text-slate-500">
        Each theme recolours and re-shapes the whole platform, and carries its own hero image per surface
        (Dashboard, eSIM, Numbers) — click “Hero images” on any theme to upload, replace or remove them.
        “Naara Official” is the permanent default and can’t be removed.
    </p>

    {{-- ONE modal engine (blueprint §31) — hero-image editor for whichever
         theme editHero() opened. showHeroModal is a real boolean (not the
         string slug) so the modal's own close paths (X, backdrop, Escape) can
         write straight back to it. --}}
    <x-ui.modal wire="showHeroModal" title="{{ $editingName }} — hero images" max-width="lg">
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Shown behind the headline on each surface when <strong>{{ $editingName }}</strong> is the active theme.
            WebP or JPG, under 600&nbsp;KB. Leave a slot blank to keep what's already saved for it.
        </p>

        <div class="space-y-4">
            @foreach ([
                ['dashboard', 'Dashboard', 'hero_dashboard'],
                ['esim', 'eSIM', 'hero_esim'],
                ['numbers', 'Numbers', 'hero_numbers'],
            ] as [$surface, $label, $field])
                <div class="rounded-xl border border-slate-200 p-3 dark:border-[#2D4060]">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $label }}</label>
                        @if ($currentHero[$surface] ?? null)
                            <button type="button" wire:click="removeHeroSurface('{{ $surface }}')"
                                    wire:confirm="Remove the {{ $label }} hero image for {{ $editingName }}?"
                                    class="text-[11px] font-medium text-red-600 hover:underline">Remove</button>
                        @endif
                    </div>
                    <div class="mb-2 flex aspect-[2/1] items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-[#2D4060] dark:bg-[#243352]">
                        @if ($this->{$field} && $this->{$field}->isPreviewable())
                            <img src="{{ $this->{$field}->temporaryUrl() }}" class="h-full w-full object-cover">
                        @elseif ($currentHero[$surface] ?? null)
                            <img src="{{ $currentHero[$surface] }}" class="h-full w-full object-cover">
                        @else
                            <span class="text-[11px] text-slate-400">No image for this surface</span>
                        @endif
                    </div>
                    <input type="file" wire:model="{{ $field }}" accept="image/webp,image/jpeg"
                           class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary dark:text-slate-400">
                    @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="open = false"
                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                Cancel
            </button>
            <button type="button" wire:click="saveHero" wire:loading.attr="disabled" wire:target="saveHero"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save hero images
            </button>
        </div>
    </x-ui.modal>

    {{-- Swappable-section editor (owner request): any theme — INCLUDING Naara
         Official — can point its header, bottom nav, or login screen at any
         OTHER theme's style family. The dropdown lists every whitelisted key
         across all 40 presets, not just this theme's own, so picking
         "Aries" here for Naara Official's header really does swap it in. --}}
    <x-ui.modal wire="showSectionsModal" title="{{ $sectionEditingName }} — sections" max-width="md">
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Swap this theme's header, bottom nav, or login screen for a style built for
            <strong>any</strong> theme — including borrowing one from a completely different persona.
            Colours, radius and typography stay <strong>{{ $sectionEditingName }}</strong>'s own; only that
            section's layout changes. Anything left on "Default" keeps today's shared look.
        </p>

        <div class="space-y-4">
            @foreach ([
                ['header', 'Header'],
                ['bottom_nav', 'Bottom nav'],
                ['login', 'Login screen'],
                ['login_bg', 'Login background effect'],
                ['footer', 'Footer'],
            ] as [$section, $label])
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $label }}</label>
                    <select wire:model="sectionStyles.{{ $section }}"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @foreach ($this->sectionStyleOptions()[$section] as $key => $optionLabel)
                            <option value="{{ $key }}">{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="open = false"
                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                Cancel
            </button>
            <button type="button" wire:click="saveSections" wire:loading.attr="disabled" wire:target="saveSections"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save sections
            </button>
        </div>
    </x-ui.modal>

    {{-- Advanced colour override editor (owner request, 2026-09-07): works
         on ANY theme, including Naara Official — each of the 6 brand
         colour tokens gets its own hex swatch + text input, pre-filled
         with the current effective colour (an override if one is saved,
         otherwise the theme's own seeded default), with a per-colour
         "Reset" back to that seeded default plus one "Reset all". Saving
         never touches the seeded palette itself — only the separate
         color_overrides layer — so a reset can never lose the original. --}}
    <x-ui.modal wire="showColorsModal" title="{{ $colorEditingName }} — colours" max-width="md">
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Override any of this theme's brand colours with your own hex code. Radius and typography stay
            <strong>{{ $colorEditingName }}</strong>'s own — only these 6 colours change. "Reset" returns a
            colour to the theme's original default at any time.
        </p>

        <div class="space-y-3">
            @foreach ($this->colorLabels() as $key => $label)
                <div class="flex items-center gap-3">
                    <input type="color" wire:model="colorValues.{{ $key }}"
                           class="h-10 w-10 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-0.5 dark:border-[#2D4060] dark:bg-[#243352]">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $label }}</label>
                        <input type="text" wire:model="colorValues.{{ $key }}" maxlength="7" placeholder="#000000"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-mono text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @error('colorValues.'.$key) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="button" wire:click="resetColor('{{ $key }}')"
                            class="shrink-0 text-[11px] font-medium text-slate-500 hover:text-primary hover:underline dark:text-slate-400">
                        Reset
                    </button>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex items-center justify-between gap-2">
            <button type="button" wire:click="resetAllColors" wire:confirm="Reset every colour on {{ $colorEditingName }} back to its default?"
                    class="text-xs font-medium text-slate-500 hover:text-red-600 hover:underline dark:text-slate-400">
                Reset all to default
            </button>
            <div class="flex gap-2">
                <button type="button" @click="open = false"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                    Cancel
                </button>
                <button type="button" wire:click="saveColors" wire:loading.attr="disabled" wire:target="saveColors"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <x-icon name="badge-check" class="h-4 w-4" /> Save colours
                </button>
            </div>
        </div>
    </x-ui.modal>

    {{-- Header editor (owner request, 2026-09-07): "full control" over the
         header — a colour independent of this theme's own brand colours (it
         even syncs the mobile browser chrome tint), bottom-corner curve, and
         glassmorphism depth. Works on naara-official too, but that theme's
         own header (the fade/blur bar, no bottom edge to round) never gets
         the curve controls — see the @unless below. --}}
    <x-ui.modal wire="showHeaderModal" title="{{ $headerEditingName }} — header" max-width="md">
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Override this theme's header — independent of its brand colours. A custom colour also tints the
            mobile browser's own address bar. "Reset" returns any field to <strong>{{ $headerEditingName }}</strong>'s
            own default at any time.
        </p>

        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <input type="color" wire:model="headerBg" value="{{ $headerBg ?: '#0a6e6e' }}"
                       class="h-10 w-10 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-0.5 dark:border-[#2D4060] dark:bg-[#243352]">
                <div class="min-w-0 flex-1">
                    <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">Header colour</label>
                    <input type="text" wire:model="headerBg" maxlength="7" placeholder="Theme default"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-mono text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('headerBg') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="resetHeaderField('bg')"
                        class="shrink-0 text-[11px] font-medium text-slate-500 hover:text-primary hover:underline dark:text-slate-400">
                    Reset
                </button>
            </div>

            @unless ($headerEditingStyle === 'default')
                <div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                        <input type="checkbox" wire:model.live="headerRoundBottom"
                               class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/40 dark:border-[#2D4060]">
                        Round the bottom corners
                    </label>

                    @if ($headerRoundBottom)
                        <div class="mt-3 space-y-3 pl-6">
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <label class="text-xs text-slate-500 dark:text-slate-400">Bottom-left curve — {{ $headerRadiusBl }}px</label>
                                </div>
                                <input type="range" wire:model="headerRadiusBl" min="{{ \App\Support\ThemePreset::HEADER_RADIUS_MIN }}" max="{{ \App\Support\ThemePreset::HEADER_RADIUS_MAX }}"
                                       class="w-full accent-primary">
                                @error('headerRadiusBl') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <label class="text-xs text-slate-500 dark:text-slate-400">Bottom-right curve — {{ $headerRadiusBr }}px</label>
                                </div>
                                <input type="range" wire:model="headerRadiusBr" min="{{ \App\Support\ThemePreset::HEADER_RADIUS_MIN }}" max="{{ \App\Support\ThemePreset::HEADER_RADIUS_MAX }}"
                                       class="w-full accent-primary">
                                @error('headerRadiusBr') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                    <button type="button" wire:click="resetHeaderField('radius')"
                            class="mt-2 text-[11px] font-medium text-slate-500 hover:text-primary hover:underline dark:text-slate-400">
                        Reset
                    </button>
                </div>
            @endunless

            <div>
                <div class="mb-1 flex items-center justify-between">
                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Glassmorphism depth — {{ $headerBlur }}px blur</label>
                    <button type="button" wire:click="resetHeaderField('blur')"
                            class="text-[11px] font-medium text-slate-500 hover:text-primary hover:underline dark:text-slate-400">
                        Reset
                    </button>
                </div>
                <input type="range" wire:model="headerBlur" min="{{ \App\Support\ThemePreset::HEADER_BLUR_MIN }}" max="{{ \App\Support\ThemePreset::HEADER_BLUR_MAX }}"
                       class="w-full accent-primary">
                @error('headerBlur') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-5 flex items-center justify-between gap-2">
            <button type="button" wire:click="resetAllHeader" wire:confirm="Reset the whole header on {{ $headerEditingName }} back to its default?"
                    class="text-xs font-medium text-slate-500 hover:text-red-600 hover:underline dark:text-slate-400">
                Reset all to default
            </button>
            <div class="flex gap-2">
                <button type="button" @click="open = false"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                    Cancel
                </button>
                <button type="button" wire:click="saveHeader" wire:loading.attr="disabled" wire:target="saveHeader"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <x-icon name="badge-check" class="h-4 w-4" /> Save header
                </button>
            </div>
        </div>
    </x-ui.modal>

    {{-- Per-theme landing-page content editor (owner request): entirely
         schema-driven off LandingHeroLibrary — the form below adapts to
         whatever fields the theme's assigned custom landing style declares,
         so a future landing style just needs a new registry entry, no
         change here. --}}
    <x-ui.modal wire="showLandingModal" title="{{ $landingEditingName }} — landing page" max-width="lg">
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            This theme has its own unique landing page — edit its text and image here.
            Leave the image blank to keep what's already saved.
        </p>

        <div class="space-y-4">
            @foreach ($this->landingFields() as $field)
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $field['label'] }}</label>

                    @if ($field['type'] === 'textarea')
                        <textarea wire:model="landingValues.{{ $field['key'] }}" rows="3" maxlength="{{ $field['max'] }}"
                                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                    @elseif ($field['type'] === 'select')
                        <select wire:model="landingValues.{{ $field['key'] }}"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @foreach ($field['options'] as $key => $optionLabel)
                                <option value="{{ $key }}">{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    @elseif ($field['type'] === 'image')
                        <div class="mb-2 flex aspect-[2/1] items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-[#2D4060] dark:bg-[#243352]">
                            @if ($this->landing_image_upload && $this->landing_image_upload->isPreviewable())
                                <img src="{{ $this->landing_image_upload->temporaryUrl() }}" class="h-full w-full object-cover">
                            @elseif ($landingValues[$field['key']] ?? null)
                                <img src="{{ $landingValues[$field['key']] }}" class="h-full w-full object-cover">
                            @else
                                <span class="text-[11px] text-slate-400">No image — a themed placeholder shows instead</span>
                            @endif
                        </div>
                        <input type="file" wire:model="landing_image_upload" accept="image/webp,image/jpeg"
                               class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary dark:text-slate-400">
                        @error('landing_image_upload') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @else
                        <input type="text" wire:model="landingValues.{{ $field['key'] }}" maxlength="{{ $field['max'] }}"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @endif
                    @error('landingValues.'.$field['key']) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="open = false"
                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                Cancel
            </button>
            <button type="button" wire:click="saveLanding" wire:loading.attr="disabled" wire:target="saveLanding"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save landing page
            </button>
        </div>
    </x-ui.modal>

    {{-- Per-theme content-page editor — About / How It Works / Contact
         (owner request): entirely schema-driven off ThemePageLibrary, same
         pattern as the landing editor above, so a future themed page needs
         no change here, only a new registry entry. --}}
    <x-ui.modal wire="showPageModal" title="{{ $pageEditingName }} — page content" max-width="lg">
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            This theme has its own unique layout for this page — edit its text here.
        </p>

        <div class="space-y-4">
            @foreach ($this->pageFields() as $field)
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $field['label'] }}</label>

                    @if ($field['type'] === 'textarea')
                        <textarea wire:model="pageValues.{{ $field['key'] }}" rows="3" maxlength="{{ $field['max'] }}"
                                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                    @elseif ($field['type'] === 'select')
                        <select wire:model="pageValues.{{ $field['key'] }}"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @foreach ($field['options'] as $key => $optionLabel)
                                <option value="{{ $key }}">{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" wire:model="pageValues.{{ $field['key'] }}" maxlength="{{ $field['max'] }}"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @endif
                    @error('pageValues.'.$field['key']) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="open = false"
                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                Cancel
            </button>
            <button type="button" wire:click="savePage" wire:loading.attr="disabled" wire:target="savePage"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save page
            </button>
        </div>
    </x-ui.modal>
</div>
