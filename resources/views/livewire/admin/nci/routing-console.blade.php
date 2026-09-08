<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Routing Console</h1>
    <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">A read-only simulation of the order the router would try per product family right now — the real ordering logic, no purchase executed.</p>

    {{-- BUILD-19 §1 — NCI kill switch. Prominent because it changes how every
         order routes: OFF makes ordering ignore learned scores and fall back to
         plain latency / success-rate. Circuit breakers stay active either way. --}}
    <div @class([
        'mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4',
        'border-emerald-300 bg-emerald-50 dark:border-emerald-700/60 dark:bg-emerald-900/20' => $nciEnabled,
        'border-amber-300 bg-amber-50 dark:border-amber-700/60 dark:bg-amber-900/20' => ! $nciEnabled,
    ])>
        <div class="flex items-start gap-3">
            <span @class([
                'mt-0.5 flex h-8 w-8 items-center justify-center rounded-full',
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-800/50 dark:text-emerald-300' => $nciEnabled,
                'bg-amber-100 text-amber-700 dark:bg-amber-800/50 dark:text-amber-300' => ! $nciEnabled,
            ])>
                <x-icon name="{{ $nciEnabled ? 'zap' : 'pause' }}" class="h-4 w-4" />
            </span>
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    NCI Intelligence: <span class="uppercase">{{ $nciEnabled ? 'On' : 'Off' }}</span>
                </p>
                <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                    @if ($nciEnabled)
                        Learned reliability scores are influencing routing order. Circuit breakers active.
                    @else
                        Learned scores are ignored — routing falls back to latency &amp; success-rate. Circuit breakers still active.
                    @endif
                </p>
            </div>
        </div>
        @if (auth()->user()?->hasAnyRole(['super_admin', 'admin']) || auth()->user()?->can('nci.override'))
            <button type="button" wire:click="toggleNci" wire:loading.attr="disabled" wire:target="toggleNci"
                    wire:confirm="{{ $nciEnabled ? 'Turn NCI OFF? Routing will ignore learned scores until re-enabled.' : 'Turn NCI ON? Routing will resume using learned scores.' }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-white transition disabled:opacity-60',
                        'bg-amber-600 hover:bg-amber-700' => $nciEnabled,
                        'bg-emerald-600 hover:bg-emerald-700' => ! $nciEnabled,
                    ])>
                <x-icon name="{{ $nciEnabled ? 'pause' : 'zap' }}" class="h-4 w-4" wire:loading.remove wire:target="toggleNci" />
                <svg wire:loading wire:target="toggleNci" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                {{ $nciEnabled ? 'Turn NCI off' : 'Turn NCI on' }}
            </button>
        @endif
    </div>

    <div class="space-y-4">
        @foreach ($families as $f)
            <div wire:key="rc-{{ $f['key'] }}" class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $f['name'] }}</h2>
                    <span class="text-[11px] uppercase text-slate-400">{{ $f['stack'] }}</span>
                </div>
                @if (count($f['order']))
                    <ol class="flex flex-wrap items-center gap-2">
                        @foreach ($f['order'] as $i => $provider)
                            <li class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-200">
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary dark:bg-primary/20 dark:text-teal-300">{{ $i + 1 }}</span>
                                    <span class="capitalize">{{ $provider }}</span>
                                </span>
                                @if (! $loop->last)<x-icon name="chevron-right" class="h-3.5 w-3.5 text-slate-300 dark:text-slate-600" />@endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="text-xs text-slate-400">No eligible providers (all excluded or none configured).</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Manual preference (nci.override) --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Manual preference</h2>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Pin a provider to the front of a stack's order for a few hours. It never resurrects an open circuit or skips the live call.</p>

        <div class="mt-3 space-y-2">
            @foreach ($prefs as $stack => $pref)
                @if ($pref)
                    <div class="flex items-center justify-between rounded-xl bg-primary/5 px-3 py-2 text-sm dark:bg-teal-500/10">
                        <span><span class="uppercase text-slate-400">{{ $stack }}</span> → <span class="font-semibold capitalize text-primary dark:text-teal-300">{{ $pref['provider'] }}</span> <span class="text-xs text-slate-400">until {{ \Illuminate\Support\Carbon::parse($pref['until'])->diffForHumans() }}</span></span>
                        <button type="button" wire:click="clearPreference('{{ $stack }}')" class="text-xs font-medium text-slate-400 underline hover:text-red-500">Clear</button>
                    </div>
                @endif
            @endforeach
        </div>

        <form wire:submit="setPreference" class="mt-4 flex flex-wrap items-end gap-2">
            <div>
                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-400">Stack</label>
                <select wire:model="prefStack" class="rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="esim">eSIM</option><option value="sms">SMS</option><option value="permanent">Permanent</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-400">Provider key</label>
                <input type="text" wire:model="prefProvider" placeholder="esimgo" class="w-36 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('prefProvider') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-400">Hours</label>
                <input type="number" wire:model="prefHours" min="1" max="168" class="w-20 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark">Set preference</button>
        </form>
    </div>
</div>
