<div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6">
    {{-- Header ---------------------------------------------------------------- --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Page builder</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                Assemble any page from sections, preview it, then publish. Drafts stay private until you publish.
            </p>
        </div>
        <div class="flex items-center gap-2">
            @unless ($hasLive)
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Not published yet</span>
            @endunless
            <button type="button" wire:click="publish" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="upload" class="h-4 w-4" wire:loading.remove wire:target="publish" />
                <x-ui.spinner class="h-4 w-4" wire:loading wire:target="publish" />
                Publish
            </button>
        </div>
    </div>

    {{-- Page switcher --------------------------------------------------------- --}}
    <div class="mb-6 flex flex-wrap items-center gap-2">
        @foreach ($pages as $key => $label)
            <button type="button" wire:click="selectPage('{{ $key }}')"
                @class([
                    'rounded-full px-3.5 py-1.5 text-sm font-medium transition',
                    'bg-primary text-white shadow-sm' => $page === $key,
                    'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10' => $page !== $key,
                ])>
                {{ $label }}
            </button>
        @endforeach
        <div class="flex items-center gap-1.5 rounded-full bg-slate-100 py-1 pl-3 pr-1 dark:bg-white/5">
            <input type="text" wire:model="newPageKey" placeholder="new-page-slug"
                class="w-32 border-0 bg-transparent p-0 text-sm text-slate-700 placeholder:text-slate-400 focus:ring-0 dark:text-slate-200">
            <button type="button" wire:click="createPage"
                class="rounded-full bg-white p-1.5 text-slate-600 shadow-sm hover:text-primary dark:bg-white/10 dark:text-slate-300">
                <x-icon name="plus" class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>
    @error('newPageKey') <p class="-mt-4 mb-4 text-xs text-red-600">{{ $message }}</p> @enderror

    <div class="grid gap-6 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
        {{-- ===== Builder column ============================================== --}}
        <div class="space-y-4">
            {{-- Section list --}}
            <div class="rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-white/10 dark:bg-slate-900/60">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Sections</h2>
                    <button type="button" wire:click="$toggle('showLibrary')"
                        class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/20 dark:text-teal-300">
                        <x-icon name="plus" class="h-3.5 w-3.5" /> Add section
                    </button>
                </div>

                {{-- Add-section library --}}
                @if ($showLibrary)
                    <div class="mb-3 grid gap-2 rounded-xl bg-slate-50 p-2 dark:bg-white/5">
                        @foreach ($library as $type => $meta)
                            <button type="button" wire:click="addSection('{{ $type }}')"
                                class="flex items-start gap-3 rounded-lg p-2.5 text-left transition hover:bg-white dark:hover:bg-white/10">
                                <span class="mt-0.5 rounded-lg bg-primary/10 p-2 text-primary dark:text-teal-300">
                                    <x-icon name="{{ $meta['icon'] }}" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $meta['label'] }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $meta['description'] }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif

                @if ($sections->isEmpty())
                    <p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
                        No sections yet. Click <span class="font-semibold">Add section</span> to start building this page.
                    </p>
                @else
                    <ul class="space-y-2" wire:key="sort-{{ $sections->pluck('id')->implode('-') }}"
                        x-data="{ dragId: null, order: @js($sections->pluck('id')->values()),
                            drop(target) {
                                if (this.dragId === null || this.dragId === target) return;
                                const o = [...this.order];
                                const from = o.indexOf(this.dragId), to = o.indexOf(target);
                                if (from < 0 || to < 0) return;
                                o.splice(to, 0, o.splice(from, 1)[0]);
                                $wire.reorder(o); this.dragId = null;
                            } }">
                        @foreach ($sections as $s)
                            <li draggable="true"
                                @dragstart="dragId = {{ $s->id }}" @dragover.prevent
                                @drop.prevent="drop({{ $s->id }})"
                                :class="dragId === {{ $s->id }} && 'opacity-50'"
                                @class([
                                    'flex items-center gap-2 rounded-xl border p-2.5 transition',
                                    'border-primary bg-primary/5 dark:bg-primary/10' => $editing && $editing->id === $s->id,
                                    'border-slate-200/70 bg-slate-50 dark:border-white/10 dark:bg-white/5' => ! ($editing && $editing->id === $s->id),
                                    'opacity-60' => ! $s->is_active,
                                ])>
                                <span class="cursor-grab text-slate-400" title="Drag to reorder"><x-icon name="grid" class="h-4 w-4" /></span>
                                <button type="button" wire:click="edit({{ $s->id }})" class="flex min-w-0 flex-1 items-center gap-2 text-left">
                                    <x-icon name="{{ $library[$s->type]['icon'] ?? 'grid' }}" class="h-4 w-4 text-primary dark:text-teal-300" />
                                    <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $library[$s->type]['label'] ?? $s->type }}</span>
                                    @unless ($s->is_active)<span class="rounded bg-slate-200 px-1.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-white/10 dark:text-slate-400">hidden</span>@endunless
                                </button>
                                <div class="flex items-center gap-0.5">
                                    <button type="button" wire:click="moveUp({{ $s->id }})" class="rounded p-1 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" title="Move up"><x-icon name="chevron-right" class="h-3.5 w-3.5 -rotate-90" /></button>
                                    <button type="button" wire:click="moveDown({{ $s->id }})" class="rounded p-1 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" title="Move down"><x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-90" /></button>
                                    <button type="button" wire:click="toggle({{ $s->id }})" class="rounded p-1 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" title="Show / hide"><x-icon name="{{ $s->is_active ? 'check' : 'x' }}" class="h-3.5 w-3.5" /></button>
                                    <button type="button" wire:click="remove({{ $s->id }})" wire:confirm="Remove this section?" class="rounded p-1 text-slate-400 hover:text-red-600" title="Remove"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Section editor --}}
            @if ($editing)
                <div class="rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-white/10 dark:bg-slate-900/60" wire:key="editor-{{ $editing->id }}">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Edit · {{ $library[$editing->type]['label'] ?? $editing->type }}</h2>
                        <button type="button" wire:click="closeEditor" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"><x-icon name="x" class="h-4 w-4" /></button>
                    </div>

                    @include('livewire.admin.page-builder._editor-'.$editing->type, ['heroPresets' => $heroPresets])

                    <button type="button" wire:click="saveSection" wire:loading.attr="disabled"
                        class="mt-4 w-full rounded-full bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                        Save section
                    </button>
                </div>
            @endif

            {{-- Version history --}}
            @if ($versions->isNotEmpty())
                <div class="rounded-2xl border border-slate-200/70 bg-white p-4 dark:border-white/10 dark:bg-slate-900/60">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Version history</h2>
                    <ul class="space-y-1.5">
                        @foreach ($versions as $v)
                            <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-white/5">
                                <span class="text-slate-600 dark:text-slate-300">
                                    #{{ $v->id }} · {{ $v->created_at->diffForHumans() }}
                                    @if ($v->is_live)<span class="ml-1 rounded bg-emerald-100 px-1.5 font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">live</span>@endif
                                    @if ($v->label)<span class="ml-1 text-slate-400">— {{ $v->label }}</span>@endif
                                </span>
                                @unless ($v->is_live)
                                    <button type="button" wire:click="rollback({{ $v->id }})" wire:confirm="Roll back to version #{{ $v->id }} and re-publish?"
                                        class="inline-flex items-center gap-1 font-semibold text-primary hover:underline dark:text-teal-300">
                                        <x-icon name="refresh" class="h-3 w-3" /> Restore
                                    </button>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- ===== Preview column ============================================== --}}
        <div class="lg:sticky lg:top-6 lg:self-start">
            <div class="mb-3 flex items-center justify-center gap-1 rounded-full bg-slate-100 p-1 dark:bg-white/5">
                @foreach (['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop'] as $device => $label)
                    <button type="button" wire:click="$set('preview', '{{ $device }}')"
                        @class([
                            'rounded-full px-4 py-1.5 text-xs font-semibold transition',
                            'bg-white text-primary shadow-sm dark:bg-white/15 dark:text-teal-300' => $preview === $device,
                            'text-slate-500 hover:text-slate-700 dark:text-slate-400' => $preview !== $device,
                        ])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="rounded-2xl border border-slate-200/70 bg-slate-100 p-3 dark:border-white/10 dark:bg-slate-950/40 sm:p-5">
                <div class="nx-preview-frame is-{{ $preview }} shadow-xl" wire:key="preview-{{ $page }}-{{ $preview }}">
                    @if (empty($previewSections))
                        <div class="flex min-h-[320px] items-center justify-center p-10 text-center text-sm text-slate-400">
                            Add a section to see the live preview here.
                        </div>
                    @else
                        <div class="nx-preview-body">
                            @include('partials.sections.render', ['sections' => $previewSections])
                        </div>
                    @endif
                </div>
                <p class="mt-3 text-center text-xs text-slate-400">
                    Live preview of the current draft · {{ ucfirst($preview) }} width
                </p>
            </div>
        </div>
    </div>
</div>
