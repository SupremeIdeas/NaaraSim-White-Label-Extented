<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Banner placements</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">The "More from Naara" banner carousel — choose where it shows and how much of each slide it displays. Turning a placement off removes it from that page immediately, no deploy.</p>
    </div>

    @foreach ($placements as $key => $label)
        @php($f = $form[$key])
        <div wire:key="placement-{{ $key }}" class="mb-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-900 dark:text-white">{{ $label }}</p>
                    <p class="text-xs text-slate-400">{{ $key }}</p>
                </div>
                <button type="button" wire:click="toggle('{{ $key }}')"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $f['enabled'] ? 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300' : 'bg-slate-100 text-slate-500 dark:bg-white/5 dark:text-slate-400' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $f['enabled'] ? 'bg-green-500' : 'bg-slate-400' }}"></span>
                    {{ $f['enabled'] ? 'On' : 'Off' }}
                </button>
            </div>

            @if ($saved === $key)
                <div class="mb-3 flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2 text-xs text-green-700 dark:bg-green-950/40 dark:text-green-300">
                    <x-icon name="badge-check" class="h-4 w-4" /> Saved.
                </div>
            @endif

            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[220px] flex-1">
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Display style</label>
                    <select wire:model="form.{{ $key }}.display_style"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @foreach ($displayStyles as $styleKey => $styleLabel)
                            <option value="{{ $styleKey }}">{{ $styleLabel }}</option>
                        @endforeach
                    </select>
                    @error("form.{$key}.display_style") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="save('{{ $key }}')" wire:loading.attr="disabled" wire:target="save('{{ $key }}')"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    Save
                </button>
            </div>
        </div>
    @endforeach
</div>
