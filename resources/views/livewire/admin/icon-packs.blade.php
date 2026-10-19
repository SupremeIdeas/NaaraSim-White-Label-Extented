<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Icon packs</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">One set of icon art in many colourways. <strong>Follow the theme</strong> (recommended) picks the pack that matches the site theme, so changing the theme recolours every menu with no extra step.</p>
    @php
        $packs = \App\Support\IconPacks\IconPacks::all();
        $activePack = \App\Support\IconPacks\IconPacks::activeSlug();
    @endphp
    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]" data-icon-pack-card>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <label for="icon-pack" class="mb-1 block text-sm font-semibold text-slate-800 dark:text-slate-100">Icon pack</label>
                <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Currently showing: <strong>{{ $packs[$activePack]['label'] ?? $activePack }}</strong>.</p>
                <select id="icon-pack" wire:model="pack" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" data-icon-pack-select>
                    <option value="follow-theme">Follow the theme (recommended)</option>
                    @foreach ($packs as $slug => $p)
                        <option value="{{ $slug }}">{{ $p['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="nx-btn nx-btn--primary">
                <span wire:loading.remove wire:target="save">Save pack</span><span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2" aria-label="Preview of the pack in use">
            @foreach (['app-more-numbers', 'app-more-esims', 'app-more-wallet', 'app-more-home', 'app-more-help-support'] as $pv)
                <span class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 dark:border-[#2D4060] dark:bg-[#243352]"><img src="{{ \App\Support\IconPacks\IconPacks::url('more', $pv, $pack === 'follow-theme' ? null : $pack) }}" alt="" width="32" height="32" loading="lazy"></span>
            @endforeach
        </div>
    </div>
</div>
