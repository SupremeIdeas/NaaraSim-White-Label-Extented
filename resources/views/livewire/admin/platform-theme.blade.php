<div class="mx-auto max-w-5xl"
     x-data="platformThemeEditor(@js($config))">
    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Dashboard theme</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">The background behind the whole in-app dashboard. Changes preview live; nothing is saved until you press Save.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="resetAll" wire:confirm="Reset the dashboard theme to the platform default?"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Reset to default</button>
            <button type="button" @click="save()" wire:loading.attr="disabled" wire:target="save"
                    class="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>

    @if ($saved)
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-300">{{ $saved }}</div>
    @endif

    {{-- ============ Mode picker ============ --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach (['default' => ['Default', 'The built-in premium treatment'], 'static_gradient' => ['Static gradient', 'Tune the brand blooms'], 'animated_gradient' => ['Animated', 'Blooms gently drift'], 'image' => ['Image', 'Upload a wallpaper']] as $key => $meta)
            <button type="button" @click="mode = '{{ $key }}'"
                    :class="mode === '{{ $key }}' ? 'border-primary ring-2 ring-primary/30' : 'border-slate-200 dark:border-white/10'"
                    class="rounded-2xl border bg-white p-4 text-left transition dark:bg-[#1A2840]">
                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $meta[0] }}</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $meta[1] }}</p>
            </button>
        @endforeach
    </div>

    {{-- ============ Live preview (light + dark side by side) ============ --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        @foreach (['light' => 'Light', 'dark' => 'Dark'] as $t => $tLabel)
            <div>
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $tLabel }} preview</p>
                <div class="dbg-preview {{ $t === 'dark' ? 'dbg-preview--dark' : '' }} relative flex h-44 items-center justify-center rounded-2xl border border-slate-200 dark:border-white/10"
                     :class="mode === 'animated_gradient' ? 'dbg-preview--animated' : ''"
                     :style="previewStyle('{{ $t }}')">
                    {{-- Image-mode preview overlays the uploaded wallpaper. --}}
                    <template x-if="mode === 'image' && imageUrl('{{ $t }}')">
                        <img :src="imageUrl('{{ $t }}')" alt="" class="absolute inset-0 h-full w-full rounded-2xl object-cover">
                    </template>
                    {{-- A sample glass card to show the show-through. --}}
                    <div class="nx-glass relative z-10 px-5 py-4 text-center">
                        <p class="text-sm font-bold {{ $t === 'dark' ? 'text-white' : 'text-slate-900' }}">Sample card</p>
                        <p class="text-xs {{ $t === 'dark' ? 'text-slate-300' : 'text-slate-500' }}">glass over the background</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============ Image mode ============ --}}
    <div x-show="mode === 'image'" x-cloak class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#1A2840]">
        <h2 class="mb-1 text-sm font-bold text-slate-900 dark:text-white">Wallpapers</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Upload a <strong>.webp</strong> for each theme (both required for image mode). Keep each under <strong>900 KB</strong> — it renders behind every page, so a light file keeps the app fast.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['image_light' => 'Light wallpaper', 'image_dark' => 'Dark wallpaper'] as $field => $label)
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</label>
                    <input type="file" wire:model="{{ $field }}" accept="image/webp"
                           class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary dark:text-slate-300 dark:file:bg-primary/20 dark:file:text-teal-300">
                    <div wire:loading wire:target="{{ $field }}" class="mt-1 text-xs text-slate-400">Uploading…</div>
                    @error($field) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @if ($config[$field] ?? '')
                        <p class="mt-1 text-[11px] text-green-600 dark:text-green-400">A wallpaper is set.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ============ Gradient tuning (static / animated) ============ --}}
    <div x-show="mode === 'static_gradient' || mode === 'animated_gradient'" x-cloak
         class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#1A2840]">
        {{-- Presets --}}
        <div class="mb-5">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Presets</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($presets as $key => $p)
                    <button type="button" @click="applyPreset(@js($p))"
                            class="rounded-full border border-slate-200 px-4 py-1.5 text-sm font-medium text-slate-700 transition hover:border-primary/40 hover:bg-primary/5 dark:border-white/10 dark:text-slate-200">{{ $p['label'] }}</button>
                @endforeach
            </div>
        </div>

        {{-- Light / dark tabs + link toggle --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 dark:border-white/5">
            <div class="flex items-center gap-1 rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-white/10 dark:bg-white/5">
                <button type="button" @click="tab = 'light'" :class="tab === 'light' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-400'" class="rounded-full px-4 py-1.5 text-sm font-semibold transition">Light</button>
                <button type="button" @click="tab = 'dark'" :class="tab === 'dark' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-400'" class="rounded-full px-4 py-1.5 text-sm font-semibold transition">Dark</button>
            </div>
            <label class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                <input type="checkbox" x-model="customize_dark" @change="if (!customize_dark) syncDark()" class="rounded border-slate-300 text-primary focus:ring-primary/40">
                Customise dark separately
            </label>
        </div>

        {{-- The sliders operate on the active tab's tuning object. --}}
        <template x-for="theme in ['light','dark']" :key="theme">
            <div x-show="tab === theme" class="space-y-4">
                <div :class="theme === 'dark' && !customize_dark ? 'pointer-events-none opacity-50' : ''">
                    <p x-show="theme === 'dark' && !customize_dark" class="mb-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-white/5 dark:text-slate-400">Dark is linked to light. Tick “Customise dark separately” to edit it.</p>

                    {{-- Intensity --}}
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 flex justify-between text-xs font-medium text-slate-600 dark:text-slate-300"><span>Upper bloom intensity</span><span x-text="t(theme).up_intensity.toFixed(2)"></span></span>
                            <input type="range" min="0.5" max="1.5" step="0.05" x-model.number="t(theme).up_intensity" @input="afterEdit(theme)" class="w-full accent-[color:rgb(var(--brand-primary))]">
                        </label>
                        <label class="block">
                            <span class="mb-1 flex justify-between text-xs font-medium text-slate-600 dark:text-slate-300"><span>Lower bloom intensity</span><span x-text="t(theme).lo_intensity.toFixed(2)"></span></span>
                            <input type="range" min="0.5" max="1.5" step="0.05" x-model.number="t(theme).lo_intensity" @input="afterEdit(theme)" class="w-full accent-[color:rgb(var(--brand-primary))]">
                        </label>
                        {{-- Feather --}}
                        <label class="block">
                            <span class="mb-1 flex justify-between text-xs font-medium text-slate-600 dark:text-slate-300"><span>Upper feather</span><span x-text="t(theme).up_feather + '%'"></span></span>
                            <input type="range" min="70" max="92" step="1" x-model.number="t(theme).up_feather" @input="afterEdit(theme)" class="w-full accent-[color:rgb(var(--brand-primary))]">
                        </label>
                        <label class="block">
                            <span class="mb-1 flex justify-between text-xs font-medium text-slate-600 dark:text-slate-300"><span>Lower feather</span><span x-text="t(theme).lo_feather + '%'"></span></span>
                            <input type="range" min="70" max="92" step="1" x-model.number="t(theme).lo_feather" @input="afterEdit(theme)" class="w-full accent-[color:rgb(var(--brand-primary))]">
                        </label>
                        {{-- Spread --}}
                        <label class="block">
                            <span class="mb-1 flex justify-between text-xs font-medium text-slate-600 dark:text-slate-300"><span>Upper spread</span><span x-text="t(theme).up_size + '%'"></span></span>
                            <input type="range" min="45" max="90" step="1" x-model.number="t(theme).up_size" @input="afterEdit(theme)" class="w-full accent-[color:rgb(var(--brand-primary))]">
                        </label>
                        <label class="block">
                            <span class="mb-1 flex justify-between text-xs font-medium text-slate-600 dark:text-slate-300"><span>Lower spread</span><span x-text="t(theme).lo_size + '%'"></span></span>
                            <input type="range" min="45" max="90" step="1" x-model.number="t(theme).lo_size" @input="afterEdit(theme)" class="w-full accent-[color:rgb(var(--brand-primary))]">
                        </label>
                    </div>

                    {{-- Extra colour stop --}}
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Extra colour stop</span>
                            <select x-model="t(theme).extra_color" @change="afterEdit(theme)" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                                @foreach ($extraPalette as $c)
                                    <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 flex justify-between text-xs font-medium text-slate-600 dark:text-slate-300"><span>Extra stop strength</span><span x-text="t(theme).extra_alpha.toFixed(2)"></span></span>
                            <input type="range" min="0" max="0.35" step="0.01" x-model.number="t(theme).extra_alpha" @input="afterEdit(theme)" class="w-full accent-[color:rgb(var(--brand-accent))]">
                        </label>
                    </div>

                    <button type="button" @click="resetTheme(theme)" class="mt-4 text-xs font-semibold text-primary hover:underline dark:text-teal-300">Reset {{ '' }}<span x-text="theme"></span> to default</button>
                </div>
            </div>
        </template>
    </div>

    {{-- Card glassmorphism (owner request): how strong the .nx-glass-tile
         surface looks wherever it's already applied (eSIM catalogue cards,
         the desktop side menu, and similar wide containers) — NOT the More
         menu (deliberately solid — see app-shell.blade.php), the Numbers
         section, or the NaaraSim support widget, none of which use this
         class. A plain server round-trip; two sliders don't need the
         Alpine live-preview machinery above. --}}
    <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Card glassmorphism</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">The translucent, blurred surface on wide card containers across the app. Tune to taste — the defaults are the shipped look.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="resetGlass" wire:confirm="Reset card glassmorphism to the shipped default?"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Reset to default</button>
                <button type="button" wire:click="saveGlass" wire:loading.attr="disabled" wire:target="saveGlass"
                        class="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark">
                    <span wire:loading.remove wire:target="saveGlass">Save</span>
                    <span wire:loading wire:target="saveGlass">Saving…</span>
                </button>
            </div>
        </div>
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label class="mb-1 flex items-center justify-between text-xs font-medium text-slate-600 dark:text-slate-300">
                    <span>Opacity</span> <span x-text="$wire.glass_opacity + '%'"></span>
                </label>
                <input type="range" min="{{ \App\Support\GlassmorphismSettings::MIN_OPACITY }}" max="{{ \App\Support\GlassmorphismSettings::MAX_OPACITY }}"
                       wire:model.live="glass_opacity" class="w-full accent-primary">
                <p class="mt-1 text-[11px] text-slate-400">Lower = more see-through (more glass); higher = more solid.</p>
            </div>
            <div>
                <label class="mb-1 flex items-center justify-between text-xs font-medium text-slate-600 dark:text-slate-300">
                    <span>Blur</span> <span x-text="$wire.glass_blur + 'px'"></span>
                </label>
                <input type="range" min="{{ \App\Support\GlassmorphismSettings::MIN_BLUR }}" max="{{ \App\Support\GlassmorphismSettings::MAX_BLUR }}"
                       wire:model.live="glass_blur" class="w-full accent-primary">
                <p class="mt-1 text-[11px] text-slate-400">How strongly the background behind the card is blurred.</p>
            </div>
        </div>
        {{-- Live sample so the effect is judged against real page background,
             not an isolated swatch. --}}
        <div class="relative mt-5 overflow-hidden rounded-2xl bg-gradient-to-br from-teal-100 via-slate-100 to-amber-100 p-8 dark:from-[#16233d] dark:via-[#141f36] dark:to-[#111a2e]">
            <div class="rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10"
                 :style="`--nx-glass-opacity-light:${$wire.glass_opacity / 100};--nx-glass-opacity-dark:${Math.max({{ \App\Support\GlassmorphismSettings::MIN_OPACITY }}, $wire.glass_opacity - 7) / 100};--nx-glass-blur:${$wire.glass_blur}px;`">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Sample card</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">This is how a .nx-glass-tile container looks with the current settings.</p>
            </div>
        </div>
    </div>

    {{-- Alpine editor: owns all tuning state, drives the live preview from the
         SAME --dbg-* formula the real background uses, and hands the whole
         config to Livewire on save. --}}
    @script
    <script>
        Alpine.data('platformThemeEditor', (initial) => ({
            mode: initial.mode,
            customize_dark: initial.customize_dark,
            light: initial.light,
            dark: initial.dark,
            image_light: initial.image_light || '',
            image_dark: initial.image_dark || '',
            tab: 'light',

            defaults: @js($defaults),

            t(theme) { return theme === 'dark' ? this.dark : this.light; },

            // JS mirror of PlatformTheme::deriveDark (kept in step for the preview).
            deriveDark() {
                this.dark = {
                    up_intensity: this.light.up_intensity,
                    lo_intensity: Math.max(0.5, +(this.light.lo_intensity * 0.85).toFixed(3)),
                    up_feather: this.light.up_feather,
                    lo_feather: this.light.lo_feather,
                    up_size: this.light.up_size,
                    lo_size: this.light.lo_size,
                    extra_color: this.light.extra_color,
                    extra_alpha: this.light.extra_color === 'action' ? 0 : this.light.extra_alpha,
                };
            },
            syncDark() { this.deriveDark(); },
            afterEdit(theme) { if (theme === 'light' && !this.customize_dark) this.deriveDark(); },

            applyPreset(p) {
                const { label, ...tuning } = p;
                this.light = { ...tuning };
                if (!this.customize_dark) this.deriveDark();
            },
            resetTheme(theme) {
                const { extra_color, extra_alpha, ...rest } = this.defaults;
                this[theme] = { ...this.defaults };
                if (theme === 'light' && !this.customize_dark) this.deriveDark();
            },

            // Inline --dbg-* for a preview swatch (same variable names the CSS reads).
            previewStyle(theme) {
                const v = this.t(theme);
                return [
                    `--dbg-up-intensity:${v.up_intensity}`,
                    `--dbg-lo-intensity:${v.lo_intensity}`,
                    `--dbg-up-feather:${v.up_feather}%`,
                    `--dbg-lo-feather:${v.lo_feather}%`,
                    `--dbg-up-size:${v.up_size}% ${Math.round(v.up_size * 0.85)}%`,
                    `--dbg-lo-size:${v.lo_size}% ${Math.round(v.lo_size * 0.85)}%`,
                    `--dbg-extra-color:var(--brand-${v.extra_color})`,
                    `--dbg-extra-alpha:${v.extra_alpha}`,
                ].join(';');
            },
            imageUrl(theme) { return theme === 'dark' ? this.image_dark : this.image_light; },

            save() {
                this.$wire.save({
                    mode: this.mode,
                    customize_dark: this.customize_dark,
                    light: this.light,
                    dark: this.dark,
                    image_light: this.image_light,
                    image_dark: this.image_dark,
                });
            },
        }));
    </script>
    @endscript
</div>
