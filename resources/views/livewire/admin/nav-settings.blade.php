<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Bottom nav</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Drag to reorder, or turn an item off to replace it with whatever moves up next.
            A hidden or reordered item still only shows for viewers who are actually eligible
            for it (role, feature flags, entitlements) — this never exposes a link someone
            shouldn't have.
        </p>
    </div>

    {{-- ============================ MAIN BOTTOM NAV ============================ --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#0f2030]">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Main navigation</h2>
            <button type="button" wire:click="resetMain" wire:confirm="Reset the main bottom nav to platform defaults?"
                    class="text-xs font-medium text-slate-400 hover:text-red-600 dark:hover:text-red-400">Reset to defaults</button>
        </div>

        <ul class="space-y-1.5" wire:key="main-{{ implode('-', $mainOrder) }}"
            x-data="{ dragId: null, order: @js($mainOrder),
                drop(target) {
                    if (this.dragId === null || this.dragId === target) return;
                    const o = [...this.order];
                    const from = o.indexOf(this.dragId), to = o.indexOf(target);
                    if (from < 0 || to < 0) return;
                    o.splice(to, 0, o.splice(from, 1)[0]);
                    this.order = o;
                    $wire.reorderMain(o); this.dragId = null;
                } }">
            @foreach ($mainOrder as $i => $key)
                @if ($i === 4)
                    <li class="my-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
                        Bottom bar above · More menu below
                        <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
                    </li>
                @endif
                <li draggable="true"
                    @dragstart="dragId = '{{ $key }}'" @dragover.prevent
                    @drop.prevent="drop('{{ $key }}')"
                    :class="dragId === '{{ $key }}' && 'opacity-50'"
                    @class([
                        'flex items-center gap-3 rounded-xl border p-2.5 transition',
                        'border-slate-200/70 bg-slate-50 dark:border-white/10 dark:bg-white/5' => ! in_array($key, $mainHidden, true),
                        'border-dashed border-slate-200 opacity-50 dark:border-white/10' => in_array($key, $mainHidden, true),
                    ])>
                    <span class="cursor-grab text-slate-400" title="Drag to reorder"><x-icon name="grid" class="h-4 w-4" /></span>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                        <x-icon name="{{ $mainCatalog[$key]['icon'] ?? 'grid' }}" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $mainCatalog[$key]['label'] ?? $key }}</span>
                    <button type="button" wire:click="toggleMain('{{ $key }}')" role="switch"
                            aria-checked="{{ in_array($key, $mainHidden, true) ? 'false' : 'true' }}"
                            aria-label="Toggle {{ $mainCatalog[$key]['label'] ?? $key }}"
                            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ in_array($key, $mainHidden, true) ? 'bg-slate-300 dark:bg-[#2D4060]' : 'bg-primary' }}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ in_array($key, $mainHidden, true) ? 'translate-x-0.5' : 'translate-x-4' }}"></span>
                    </button>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ======================== NUMBERS SECTION BOTTOM NAV ======================== --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#0f2030]">
        <div class="mb-1 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Numbers section navigation</h2>
            <button type="button" wire:click="resetNumbers" wire:confirm="Reset the Numbers bottom nav to platform defaults?"
                    class="text-xs font-medium text-slate-400 hover:text-red-600 dark:hover:text-red-400">Reset to defaults</button>
        </div>
        <p class="mb-3 text-xs text-slate-400 dark:text-slate-500">Always exactly 4 shown — the top 4 active rows here.</p>

        <ul class="space-y-1.5" wire:key="numbers-{{ implode('-', $numbersOrder) }}"
            x-data="{ dragId: null, order: @js($numbersOrder),
                drop(target) {
                    if (this.dragId === null || this.dragId === target) return;
                    const o = [...this.order];
                    const from = o.indexOf(this.dragId), to = o.indexOf(target);
                    if (from < 0 || to < 0) return;
                    o.splice(to, 0, o.splice(from, 1)[0]);
                    this.order = o;
                    $wire.reorderNumbers(o); this.dragId = null;
                } }">
            @foreach ($numbersOrder as $i => $key)
                @if ($i === 4)
                    <li class="my-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
                        Shown · Not shown
                        <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
                    </li>
                @endif
                <li draggable="true"
                    @dragstart="dragId = '{{ $key }}'" @dragover.prevent
                    @drop.prevent="drop('{{ $key }}')"
                    :class="dragId === '{{ $key }}' && 'opacity-50'"
                    @class([
                        'flex items-center gap-3 rounded-xl border p-2.5 transition',
                        'border-slate-200/70 bg-slate-50 dark:border-white/10 dark:bg-white/5' => ! in_array($key, $numbersHidden, true),
                        'border-dashed border-slate-200 opacity-50 dark:border-white/10' => in_array($key, $numbersHidden, true),
                    ])>
                    <span class="cursor-grab text-slate-400" title="Drag to reorder"><x-icon name="grid" class="h-4 w-4" /></span>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                        <x-icon name="{{ $numbersCatalog[$key]['icon'] ?? 'grid' }}" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $numbersCatalog[$key]['label'] ?? $key }}</span>
                    <button type="button" wire:click="toggleNumbers('{{ $key }}')" role="switch"
                            aria-checked="{{ in_array($key, $numbersHidden, true) ? 'false' : 'true' }}"
                            aria-label="Toggle {{ $numbersCatalog[$key]['label'] ?? $key }}"
                            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition {{ in_array($key, $numbersHidden, true) ? 'bg-slate-300 dark:bg-[#2D4060]' : 'bg-primary' }}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ in_array($key, $numbersHidden, true) ? 'translate-x-0.5' : 'translate-x-4' }}"></span>
                    </button>
                </li>
            @endforeach
        </ul>
    </section>
</div>
