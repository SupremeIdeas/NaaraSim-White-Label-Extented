@php($previewStyle = isset($presets[$style]) ? $style : \App\Support\ThemeToggleSettings::DEFAULT)
<div class="mx-auto max-w-4xl px-4 py-6" wire:key="theme-toggle-studio">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Theme Toggle Studio</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Pick the style of the site-wide dark/light switch. One choice covers every header, login page, and menu — live the moment you save.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_16rem]">
        <div class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Choose a style</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($presets as $slug => $meta)
                    <button type="button" wire:click="selectStyle('{{ $slug }}')" wire:key="tgl-preset-{{ $slug }}"
                            class="group flex items-center justify-between gap-4 rounded-2xl border p-4 text-left transition {{ $style === $slug ? 'border-primary ring-2 ring-primary/40' : 'border-slate-200 hover:border-slate-300 dark:border-white/10 dark:hover:border-white/20' }} bg-white dark:bg-[#16233d]">
                        <span>
                            <span class="block text-sm font-semibold text-slate-800 dark:text-white">{{ $meta['label'] }}</span>
                            <span class="block text-xs text-slate-400 dark:text-slate-500">{{ $meta['category'] }}</span>
                        </span>
                        <span class="pointer-events-none shrink-0" wire:key="tgl-swatch-{{ $slug }}">
                            @include('components.theme-toggle.'.$slug)
                        </span>
                    </button>
                @endforeach
            </div>

            <button type="button" wire:click="save" wire:loading.attr="disabled"
                    class="mt-3 inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            @if ($saved === $style)
                <span class="ml-3 text-sm font-medium text-green-600 dark:text-green-400">Saved — live now.</span>
            @endif
        </div>

        {{-- Live preview pane --}}
        <div class="lg:sticky lg:top-6">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Live preview</h2>
            <div class="flex h-40 items-center justify-center gap-6 rounded-2xl border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-[#0D1B2A]" wire:key="tgl-preview-{{ $previewStyle }}">
                @include('components.theme-toggle.'.$previewStyle)
            </div>
            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Click the switch above to preview both light and dark states — nothing is saved until you press Save.</p>
        </div>
    </div>
</div>
