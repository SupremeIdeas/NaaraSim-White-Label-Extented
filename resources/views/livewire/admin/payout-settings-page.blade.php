@php
    $inp = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100';
@endphp
<div class="mx-auto max-w-4xl pb-24">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Payout settings</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Every payout rule in one place. Each shows its default; "Reset" puts it back. Changes are logged and your other super admins are told.</p>
        </div>
        <a href="{{ route('admin.payouts', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate class="text-sm font-semibold text-primary hover:underline">Back to Payouts</a>
    </div>

    <div class="mt-4">
        <label for="ps-search" class="sr-only">Search settings</label>
        <input id="ps-search" type="search" wire:model.live.debounce.250ms="search" placeholder="Search settings (e.g. timeout, cap, country)" class="{{ $inp }}">
    </div>

    @if ($saved)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $saved }}</div>
    @endif

    @forelse ($groups as $id => $g)
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]" aria-labelledby="ps-{{ $id }}">
            <h2 id="ps-{{ $id }}" class="text-base font-bold text-slate-900 dark:text-slate-100">{{ $g['title'] }}</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $g['blurb'] }}</p>

            <div class="mt-4 divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($g['fields'] as $f)
                    @php
                        $slot = \App\Livewire\Admin\PayoutSettingsPage::slot($f['key']);
                        $custom = \App\Support\PayoutSettingsSchema::isCustom($f);
                        $dirtyNow = in_array($f['key'], $dirtyKeys, true);
                    @endphp
                    <div class="grid gap-2 py-3 sm:grid-cols-[1fr_220px] sm:items-start" wire:key="f-{{ $slot }}">
                        <div>
                            <label for="f-{{ $slot }}" class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                {{ $f['label'] }}
                                @if ($f['danger'] ?? false)<span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-300">Affects money flow</span>@endif
                                @if ($custom)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">Changed from default</span>@endif
                                @if ($dirtyNow)<span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">Unsaved</span>@endif
                            </label>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $f['help'] }}</p>
                            <p class="mt-1 text-[11px] text-slate-400">Default: {{ is_bool($f['default']) ? ($f['default'] ? 'On' : 'Off') : ($f['default'] === '' ? 'none' : $f['default']) }}@if ($f['unit']) {{ $f['unit'] }}@endif
                                @if ($custom) · <button type="button" wire:click="resetField('{{ $slot }}')" wire:confirm="Reset &quot;{{ $f['label'] }}&quot; to its default?" class="font-semibold text-primary hover:underline">Reset</button>@endif</p>
                            @error('values.'.$slot)<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($f['type'] === 'bool')
                                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input id="f-{{ $slot }}" type="checkbox" wire:model.live="values.{{ $slot }}" class="h-5 w-9 cursor-pointer rounded-full">
                                    <span>{{ ($values[$slot] ?? false) ? 'On' : 'Off' }}</span>
                                </label>
                            @elseif ($f['type'] === 'select')
                                <select id="f-{{ $slot }}" wire:model.live="values.{{ $slot }}" class="{{ $inp }}">
                                    @foreach ($f['options'] as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                                </select>
                            @elseif ($f['type'] === 'int' || $f['type'] === 'float')
                                <input id="f-{{ $slot }}" type="number" step="{{ $f['type'] === 'int' ? 1 : 'any' }}" min="{{ $f['min'] }}" max="{{ $f['max'] }}" wire:model.live.debounce.400ms="values.{{ $slot }}" class="{{ $inp }}">
                                @if ($f['unit'])<span class="shrink-0 text-xs text-slate-400">{{ $f['unit'] }}</span>@endif
                            @elseif ($f['type'] === 'json')
                                <textarea id="f-{{ $slot }}" rows="2" wire:model.blur="values.{{ $slot }}" class="{{ $inp }} font-mono text-xs"></textarea>
                            @else
                                <input id="f-{{ $slot }}" type="text" wire:model.blur="values.{{ $slot }}" class="{{ $inp }}" placeholder="{{ $f['type'] === 'csv' ? 'e.g. KP, IR' : '' }}">
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <p class="mt-6 rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-white/15">No settings match "{{ $search }}".</p>
    @endforelse

    {{-- Sticky save bar --}}
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 p-3 backdrop-blur dark:border-[#2D4060] dark:bg-[#1A2840]/95 lg:left-64">
        <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-slate-600 dark:text-slate-300">
                @if (count($dirtyKeys))
                    <span class="font-semibold">{{ count($dirtyKeys) }}</span> unsaved change{{ count($dirtyKeys) === 1 ? '' : 's' }}
                @else
                    No unsaved changes
                @endif
                @if ($needsConfirm)
                    <label class="mt-1 flex items-center gap-2 text-xs text-red-700 dark:text-red-300">
                        <input type="checkbox" wire:model.live="confirmed" class="rounded"> I understand these changes affect how money moves.
                    </label>
                    @error('confirmed')<span class="text-xs text-red-600" role="alert">{{ $message }}</span>@enderror
                @endif
            </div>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" @disabled(! count($dirtyKeys))
                class="flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-50">
                <x-icon name="check" wire:loading.remove wire:target="save" class="h-4 w-4" />
                <x-ui.spinner wire:loading wire:target="save" class="h-4 w-4" />
                Save changes
            </button>
        </div>
    </div>
</div>
