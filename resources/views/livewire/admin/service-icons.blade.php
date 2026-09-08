<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Service icons</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Every service a customer can buy a number for shows a logo automatically — a built-in mark, or the
        provider's own artwork once your APIs are live. Upload an official logo here to override any of them,
        or add a brand-new service. Accepts <strong>WebP, SVG or PNG</strong> (also JPG), square, up to 1&nbsp;MB.
        Services with a logo show first in the picker; the ones below still on a letter avatar are the ones to fix.
    </p>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Upload / add --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Service</label>
                <select wire:model="uploadSlug" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm capitalize dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="">Add a new one…</option>
                    @foreach ($rows as $row)
                        <option value="{{ $row['slug'] }}">{{ $row['slug'] }}</option>
                    @endforeach
                </select>
                @if ($uploadSlug === '')
                    <input type="text" wire:model="newSlug" placeholder="e.g. bumble"
                           class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('newSlug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @endif
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Logo file</label>
                <input type="file" wire:model="upload" accept="image/webp,image/svg+xml,image/png,image/jpeg"
                       class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white dark:text-slate-400">
                @error('upload') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <x-ui.btn variant="primary" type="button" wire:click="save" target="save" icon="upload">Save logo</x-ui.btn>
        </div>
    </div>

    {{-- Filter: all vs only the ones still missing a logo. --}}
    <div class="mb-3 flex items-center justify-between">
        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
            {{ $missingCount }} {{ Str::plural('service', $missingCount) }} still on a letter avatar
        </p>
        <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
            <input type="checkbox" wire:model.live="onlyMissing" class="rounded border-slate-300 text-primary focus:ring-primary/40">
            Show only missing
        </label>
    </div>

    {{-- Current set --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
        @forelse ($rows as $row)
            @php($slug = $row['slug'])
            <div wire:key="svc-{{ $slug }}" class="nx-card relative !p-4 text-center">
                @if ($row['state'] === 'missing')
                    <span class="absolute right-2 top-2 rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">Needs icon</span>
                @endif
                <span class="mx-auto flex h-12 w-12 items-center justify-center text-primary dark:text-teal-300">
                    <x-service-icon :slug="$slug" class="h-10 w-10" />
                </span>
                <p class="mt-2 text-xs font-semibold capitalize text-slate-700 dark:text-slate-200">{{ $slug }}</p>
                @if ($row['state'] === 'custom')
                    <button type="button" wire:click="remove('{{ $slug }}')" class="mt-1 text-[11px] font-medium text-red-500 hover:underline">Remove override</button>
                @elseif ($row['state'] === 'bundled')
                    <p class="mt-1 text-[11px] text-slate-400">Built-in mark</p>
                @else
                    <button type="button" wire:click="$set('uploadSlug', '{{ $slug }}')" class="mt-1 text-[11px] font-medium text-primary hover:underline dark:text-teal-300">Add a logo</button>
                @endif
            </div>
        @empty
            <p class="col-span-full py-8 text-center text-sm text-slate-500 dark:text-slate-400">Every service has a logo.</p>
        @endforelse
    </div>
</div>
