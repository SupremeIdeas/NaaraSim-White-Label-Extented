@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="mx-auto max-w-2xl px-4 py-6 sm:px-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Welcome animation</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">The Aurora entrance new users see once, right after signup.</p>
        </div>
        <a href="{{ route('welcome', ['preview' => 1]) }}" target="_blank"
            class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200">
            <x-icon name="zap" class="h-4 w-4" /> Live preview
        </a>
    </div>

    <div class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        {{-- Enable toggle --}}
        <label class="mb-5 flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-200">
            <input type="checkbox" wire:model="form.enabled" class="rounded border-slate-300 text-primary focus:ring-primary">
            Show the welcome animation after signup
        </label>

        {{-- Entrance style (two presets) --}}
        <div class="mb-5">
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Entrance style</label>
            <select wire:model="form.style" class="{{ $inp }}">
                <option value="aurora">Aurora — drifting brand blobs</option>
                <option value="spotlight">Spotlight — radial beam + shimmer</option>
            </select>
            @error('form.style') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-[11px] text-slate-400">Both use your colours below; Spotlight is calmer and more “premium hero”.</p>
        </div>

        {{-- Copy --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Welcome text</label>
                <input type="text" wire:model="form.welcome_text" class="{{ $inp }}">
                @error('form.welcome_text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Tagline</label>
                <input type="text" wire:model="form.tagline_text" class="{{ $inp }}">
                @error('form.tagline_text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Timings --}}
        <h2 class="mb-3 mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">Timing</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Logo reveal <span class="text-slate-400">(ms)</span></label>
                <input type="number" wire:model="form.logo_reveal_speed" class="{{ $inp }}">
                @error('form.logo_reveal_speed') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Tagline delay <span class="text-slate-400">(ms)</span></label>
                <input type="number" wire:model="form.tagline_reveal_delay" class="{{ $inp }}">
                @error('form.tagline_reveal_delay') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Total <span class="text-slate-400">(ms)</span></label>
                <input type="number" wire:model="form.animation_total_duration" class="{{ $inp }}">
                @error('form.animation_total_duration') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Aurora loop <span class="text-slate-400">(s)</span></label>
                <input type="number" wire:model="form.aurora_speed" class="{{ $inp }}">
                @error('form.aurora_speed') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Colours --}}
        <h2 class="mb-3 mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">Aurora colours</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Colour 1</label>
                <input type="color" wire:model="form.brand_color_1" class="h-10 w-full rounded-lg border border-slate-200 dark:border-white/10">
                @error('form.brand_color_1') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Colour 2</label>
                <input type="color" wire:model="form.brand_color_2" class="h-10 w-full rounded-lg border border-slate-200 dark:border-white/10">
                @error('form.brand_color_2') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex flex-wrap gap-2">
            <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="flex-1 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                Save settings
            </button>
            <button type="button" wire:click="resetDefaults" wire:confirm="Reset the welcome animation to defaults?"
                class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/15 dark:text-slate-300 dark:hover:bg-white/5">
                Reset to default
            </button>
        </div>
    </div>
</div>
