<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">API keys</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Paste each provider, payment gateway and integration key here. Keys are encrypted at rest and take
        effect immediately — no file editing and no redeploy. A product stays <span class="font-medium">Coming Soon</span>
        until its key is saved, then flips <span class="font-medium text-green-700 dark:text-green-400">Active</span>.
        Leave a field blank to keep the key you already saved.
    </p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Live status of every provider, driven by the real config. --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Provider status</h2>
        <div class="flex flex-wrap gap-2">
            @foreach ($statuses as $provider => $label)
                @php $active = $label === 'Active'; @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium
                    {{ $active
                        ? 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-400'
                        : 'bg-slate-100 text-slate-500 dark:bg-[#243352] dark:text-slate-400' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $active ? 'bg-green-500' : 'bg-slate-400' }}"></span>
                    {{ ucfirst($provider) }} — {{ $label }}
                </span>
            @endforeach
        </div>
    </div>

    {{-- eSIM catalogue sync — last result per provider + force a fetch now. --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">eSIM catalogue sync</h2>
        <div class="space-y-2">
            @foreach (['esimgo' => 'eSIM Go', 'airalo' => 'Airalo', 'quibity' => 'Quibity', 'zendit' => 'Zendit (Naara Connect + data)', 'oneglobal' => '1GLOBAL (Naara Connect)', 'montymobile' => 'Monty Mobile (Naara Connect)', 'gigs' => 'Gigs (Naara Connect)'] as $prov => $label)
                @php $st = $syncStatus[$prov] ?? null; @endphp
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-100 px-3 py-2 dark:border-[#243352]">
                    <span class="w-24 shrink-0 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}</span>
                    @if ($st === null)
                        <span class="text-xs text-slate-400">Never synced</span>
                    @elseif ($st['skipped'] ?? false)
                        <span class="inline-flex items-center gap-1 text-xs text-slate-400"><x-icon name="info" class="h-3.5 w-3.5" /> Not configured — no API key set</span>
                    @elseif ($st['ok'])
                        <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400"><x-icon name="badge-check" class="h-3.5 w-3.5" /> {{ $st['count'] }} plans</span>
                        <span class="text-[11px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($st['at'])->diffForHumans() }}</span>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs text-red-600 dark:text-red-400"><x-icon name="x" class="h-3.5 w-3.5" /> failed</span>
                        <span class="max-w-md truncate text-[11px] text-slate-400" title="{{ $st['error'] }}">{{ $st['error'] }}</span>
                        <span class="text-[11px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($st['at'])->diffForHumans() }}</span>
                    @endif
                    <button type="button" wire:click="syncNow('{{ $prov }}')" wire:loading.attr="disabled" wire:target="syncNow"
                            class="ml-auto shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                        <span wire:loading.remove wire:target="syncNow">Sync now</span>
                        <span wire:loading wire:target="syncNow" class="inline-flex items-center gap-1"><x-ui.spinner class="h-3.5 w-3.5" /> Syncing…</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Media storage — which object store serves uploads (BUILD-11 §4). --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Primary media store</h2>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                Serving from: {{ $activeMediaDisk }}
            </span>
        </div>
        <p class="mb-3 text-[11px] leading-relaxed text-slate-400 dark:text-slate-500">
            <span class="font-medium">Auto</span> prefers Cloudflare R2, then Wasabi, then the server’s own disk — using
            whichever is configured. Pick a specific store to pin it. An option that isn’t configured safely falls back to
            Auto, so uploads never break.
            @unless ($r2Ready)
                <span class="text-amber-600 dark:text-amber-400">R2 isn’t configured yet — add its keys below to enable it.</span>
            @endunless
        </p>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="primary-disk" class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Serve media from</label>
                <select id="primary-disk" wire:model="primaryDisk"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="auto">Auto (R2 → Wasabi → server)</option>
                    <option value="r2">Cloudflare R2</option>
                    <option value="wasabi">Wasabi</option>
                    <option value="public">Server disk</option>
                </select>
            </div>
            <button type="button" wire:click="savePrimaryDisk" wire:loading.attr="disabled" wire:target="savePrimaryDisk"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                <span wire:loading.remove wire:target="savePrimaryDisk">Save store</span>
                <span wire:loading wire:target="savePrimaryDisk" class="inline-flex items-center gap-1"><x-ui.spinner class="h-3.5 w-3.5" /> Saving…</span>
            </button>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        @foreach ($schema as $groupKey => $group)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $group['label'] }}</h2>
                <div class="space-y-4">
                    @foreach ($group['fields'] as $field => $meta)
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label for="key-{{ $field }}" class="block text-xs font-medium text-slate-600 dark:text-slate-300">
                                    {{ $meta['label'] }}
                                    @if ($meta['secret'])
                                        <span class="ml-1 inline-flex items-center gap-0.5 text-[10px] font-normal text-slate-400">
                                            <x-icon name="shield" class="h-3 w-3" /> secret
                                        </span>
                                    @endif
                                </label>
                                @if ($previews[$field])
                                    <span class="font-mono text-[11px] text-slate-400 dark:text-slate-500">saved: {{ $previews[$field] }}</span>
                                @else
                                    <span class="text-[11px] text-slate-400 dark:text-slate-500">not set</span>
                                @endif
                            </div>
                            <input id="key-{{ $field }}"
                                   type="{{ $meta['secret'] ? 'password' : 'text' }}"
                                   autocomplete="off" spellcheck="false"
                                   wire:model="inputs.{{ $field }}"
                                   placeholder="{{ $previews[$field] ? 'Leave blank to keep current' : 'Paste key…' }}"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm text-slate-900 placeholder:font-sans placeholder:text-slate-400 focus:border-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            <p class="mt-1 text-[11px] leading-relaxed text-slate-400 dark:text-slate-500">
                                <span class="font-mono">{{ $meta['env'] }}</span> — {{ $meta['hint'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex items-center justify-end gap-3">
            <span wire:loading wire:target="save" class="text-sm text-slate-400">Saving…</span>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save keys
            </button>
        </div>
    </form>
</div>
