<div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.nci.registry') }}" wire:navigate class="mb-4 inline-flex items-center gap-1 text-xs font-medium text-slate-400 hover:text-primary dark:hover:text-teal-300">
        <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" /> Provider Registry
    </a>

    {{-- Header: real name + the primary "Open Provider Dashboard" action --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold capitalize text-slate-900 dark:text-slate-100">{{ $row->provider_key }}</h1>
                @if ($row->paused_at)
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300">
                        <x-icon name="moon" class="h-3 w-3" /> Paused {{ $row->paused_at->diffForHumans() }}
                    </span>
                @endif
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <span class="uppercase">{{ $row->stack }}</span> ·
                <span>{{ $tierLabel }}</span>
                @foreach ($families as $fam)<span class="rounded-full bg-primary/10 px-2 py-0.5 font-medium text-primary dark:bg-primary/20 dark:text-teal-300">{{ $fam }}</span>@endforeach
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                @if ($row->docs_url)<a href="{{ $row->docs_url }}" target="_blank" rel="noopener" class="text-primary hover:underline dark:text-teal-300">API docs ↗</a>@endif
                @if ($row->contact_email)<span class="text-slate-500 dark:text-slate-400">{{ $row->contact_email }}</span>@endif
            </div>
        </div>
        <div class="flex flex-col items-end gap-2">
            @if ($row->dashboard_login_url)
                <a href="{{ $row->dashboard_login_url }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark">
                    <x-icon name="link" class="h-4 w-4" /> Open Provider Dashboard ↗
                </a>
            @else
                <span class="text-xs text-slate-400">No dashboard URL — add one in the registry seed.</span>
            @endif

            @if ($canOverride)
                @if ($row->paused_at)
                    <button type="button" wire:click="resume" wire:confirm="Resume {{ $row->provider_key }}? It rejoins routing immediately."
                            class="inline-flex items-center gap-2 rounded-xl border border-green-200 px-4 py-2 text-sm font-semibold text-green-600 transition hover:bg-green-50 dark:border-green-900/40 dark:hover:bg-green-950/20">
                        <x-icon name="check" class="h-4 w-4" /> Resume provider
                    </button>
                @else
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="pauseReason" placeholder="Reason (optional)"
                               class="w-40 rounded-lg border border-slate-200 px-2 py-1.5 text-xs dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <button type="button" wire:click="pause" wire:confirm="Pause {{ $row->provider_key }}? It is excluded from routing until you resume it — no auto-recovery."
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/20 dark:text-slate-300 dark:hover:bg-white/5">
                            <x-icon name="moon" class="h-4 w-4" /> Pause provider
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-4 flex flex-wrap gap-1 border-b border-slate-200 dark:border-[#2D4060]">
        @foreach (['overview' => 'Overview', 'circuit' => 'Circuit & Outcomes', 'nci' => 'NCI Intelligence', 'config' => 'Config'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition {{ $tab === $key ? 'border-primary text-primary dark:text-teal-300' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- OVERVIEW --}}
    @if ($tab === 'overview')
        <div class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-[11px] uppercase tracking-wide text-slate-400">Status</p><div class="mt-1"><x-nci.pill kind="status" :value="$row->status" /></div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-[11px] uppercase tracking-wide text-slate-400">Balance</p><p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ is_null($row->balance) ? '—' : '$'.number_format((float) $row->balance, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-[11px] uppercase tracking-wide text-slate-400">Latency</p><p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ is_null($row->latency_ms) ? '—' : $row->latency_ms.'ms' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-[11px] uppercase tracking-wide text-slate-400">Last checked</p><p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $row->last_checked_at?->diffForHumans() ?? '—' }}</p>
            </div>
        </div>

        {{-- Credentials — masked; reveal requires the admin's password --}}
        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Credentials</h2>
                <div class="flex items-center gap-3">
                    @if ($revealed)
                        <button type="button" wire:click="hideSecrets" class="text-xs font-medium text-slate-400 underline">Hide</button>
                    @endif
                    @if (Auth::user()?->hasRole('super_admin') && collect($credentials)->contains('has', true))
                        <button type="button" wire:click="clearKeys"
                                wire:confirm="Clear every stored API key for {{ $row->provider_key }}? It reverts to Coming Soon until new keys are saved."
                                class="text-xs font-medium text-red-500 underline hover:text-red-700 dark:text-red-400">
                            Clear API keys
                        </button>
                    @endif
                </div>
            </div>
            @forelse ($credentials as $c)
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 dark:border-[#243352]">
                    <div class="min-w-0">
                        <p class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $c['label'] }}</p>
                        <p class="font-mono text-xs text-slate-500 dark:text-slate-400">
                            @if (! $c['has']) <span class="text-slate-300 dark:text-slate-600">not set</span>
                            @elseif ($revealed && $c['secret']) {{ $c['raw'] }}
                            @else {{ $c['preview'] }} @endif
                        </p>
                    </div>
                    @if ($c['secret'] && $c['has'])<span class="shrink-0 text-[10px] uppercase text-amber-500">secret</span>@endif
                </div>
            @empty
                <p class="mt-2 text-xs text-slate-400">No credential fields registered for this provider.</p>
            @endforelse

            @unless ($revealed)
                <form wire:submit="reveal" class="mt-4 flex items-end gap-2">
                    <div class="flex-1">
                        <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-400">Re-enter your password to reveal secrets</label>
                        <input type="password" wire:model="revealPassword" autocomplete="current-password"
                               class="w-full max-w-xs rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @error('revealPassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900 dark:bg-white/10 dark:hover:bg-white/15">Reveal</button>
                </form>
            @endunless
        </div>
    @endif

    {{-- CIRCUIT & OUTCOMES --}}
    @if ($tab === 'circuit')
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Circuit</span>
                    <x-nci.pill kind="circuit" :value="$row->circuit_breaker_state" />
                    <span class="inline-flex items-center gap-1 text-xs text-slate-400">24h:
                        <x-icon name="check" class="h-3 w-3 text-emerald-500" /> {{ $row->success_count_24h }}
                        <span class="text-slate-300 dark:text-slate-600">/</span>
                        <x-icon name="x" class="h-3 w-3 text-red-500" /> {{ $row->failure_count_24h }}
                    </span>
                </div>
                @if ($canOverride)
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openCircuit" wire:confirm="Force this provider's circuit OPEN? Live routing will skip it until reset."
                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:border-red-900/40 dark:hover:bg-red-950/20">Open circuit</button>
                        <button type="button" wire:click="closeCircuit"
                                class="rounded-lg border border-green-200 px-3 py-1.5 text-xs font-semibold text-green-600 hover:bg-green-50 dark:border-green-900/40 dark:hover:bg-green-950/20">Reset to closed</button>
                    </div>
                @endif
            </div>
        </div>
        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="border-b border-slate-100 p-4 dark:border-[#243352]"><h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Recent outcomes</h2></div>
            <div class="divide-y divide-slate-50 dark:divide-[#243352]/70">
                @forelse ($outcomes as $o)
                    <div class="flex items-center justify-between px-4 py-2 text-sm">
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $o->outcome === 'success' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                            <span class="capitalize text-slate-700 dark:text-slate-200">{{ $o->outcome }}</span>
                            @if ($o->error_code)<span class="font-mono text-xs text-slate-400">{{ $o->error_code }}</span>@endif
                        </span>
                        <span class="text-xs text-slate-400">{{ $o->occurred_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="p-4 text-xs text-slate-400">No recorded attempts yet.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- NCI INTELLIGENCE (read-only) --}}
    @if ($tab === 'nci')
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="mb-3 text-xs text-slate-400">NCI's output is read-only here — the learning engine owns these; admin only views them.</p>
            <div class="grid gap-3 sm:grid-cols-4">
                <div><p class="text-[11px] uppercase tracking-wide text-slate-400">Score</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ is_null($row->nci_score) ? '—' : round($row->nci_score * 100) }}</p></div>
                <div><p class="text-[11px] uppercase tracking-wide text-slate-400">Confidence</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ is_null($row->nci_confidence) ? '—' : round($row->nci_confidence * 100).'%' }}</p></div>
                <div><p class="text-[11px] uppercase tracking-wide text-slate-400">Risk</p><div class="mt-1"><x-nci.pill kind="risk" :value="$row->nci_risk_rating" /></div></div>
                <div><p class="text-[11px] uppercase tracking-wide text-slate-400">Samples</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $row->nci_sample_size }}</p></div>
            </div>
            <p class="mt-3 text-xs text-slate-400">Last computed: {{ $row->nci_computed_at?->diffForHumans() ?? 'never' }}</p>
        </div>
    @endif

    {{-- CONFIG (links to the existing settings — never duplicated) --}}
    @if ($tab === 'config')
        <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-5 text-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-slate-500 dark:text-slate-400">Per-provider configuration lives in its existing admin home — this tab links there rather than duplicating it:</p>
            <a href="{{ route('admin.integrations') }}" wire:navigate class="block text-primary hover:underline dark:text-teal-300">→ API keys & credentials (Integrations)</a>
            <a href="{{ route('admin.pricing') }}" wire:navigate class="block text-primary hover:underline dark:text-teal-300">→ Pricing & margin rules</a>
        </div>
    @endif
</div>
