<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Payment gateways</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Sandbox/live mode, the URLs to register on each provider's dashboard, and a live connection test.
            Card gateways (Paystack, Flutterwave, Stripe) store separate sandbox + live keys below; other gateways' keys live on
            <a href="{{ route('admin.api-keys') }}" class="text-primary underline" wire:navigate>Provider Keys</a>.
        </p>
    </div>

    <div class="space-y-4">
        @foreach ($gateways as $g)
            <div wire:key="gw-{{ $g['key'] }}" class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]"
                 x-data="{ copied: '' }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <x-payment-icon :slug="$g['key']" class="h-8" />
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $g['label'] }}</p>
                            <p class="text-xs">
                                @if ($g['configured'])
                                    <span class="text-emerald-600 dark:text-emerald-400">Configured</span>
                                @else
                                    <span class="text-slate-400">Not configured</span>
                                @endif
                                @if ($g['is_test'])
                                    <span class="ml-1 rounded-full bg-orange-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-orange-700 dark:bg-orange-500/20 dark:text-orange-300">Test keys</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Mode toggle --}}
                    <div class="flex items-center gap-1.5 rounded-full bg-slate-100 p-1 dark:bg-white/5">
                        <button type="button" wire:click="setMode('{{ $g['key'] }}', 'sandbox')"
                                @class(['rounded-full px-3 py-1 text-xs font-semibold transition', 'bg-white text-amber-700 shadow-sm dark:bg-white/15 dark:text-amber-300' => $g['mode'] === 'sandbox', 'text-slate-500' => $g['mode'] !== 'sandbox'])>Sandbox</button>
                        <button type="button" wire:click="setMode('{{ $g['key'] }}', 'live')"
                                @class(['rounded-full px-3 py-1 text-xs font-semibold transition', 'bg-white text-emerald-700 shadow-sm dark:bg-white/15 dark:text-emerald-300' => $g['mode'] === 'live', 'text-slate-500' => $g['mode'] !== 'live'])>Live</button>
                    </div>
                </div>

                @unless ($g['has_hosts'])
                    <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">This gateway keys sandbox vs live by the API key itself (e.g. a test key) — the toggle above is a reminder; paste the matching key on Provider Keys.</p>
                @else
                    <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">This gateway has separate sandbox and live hosts — the toggle switches the API base URL.</p>
                @endunless

                {{-- URLs --}}
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ([['Webhook URL', $g['webhook_url'], 'webhook'], ['Callback / return URL', $g['callback_url'], 'callback']] as [$label, $urlValue, $tag])
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</label>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ $urlValue }}"
                                       class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-mono text-[11px] text-slate-600 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300">
                                <button type="button"
                                        @click="navigator.clipboard.writeText(@js($urlValue)); copied = '{{ $tag }}'; setTimeout(() => copied = '', 1500)"
                                        class="shrink-0 rounded-lg border border-slate-300 p-1.5 text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5" aria-label="Copy {{ $label }}">
                                    <span x-show="copied !== '{{ $tag }}'"><x-icon name="copy" class="h-4 w-4" /></span>
                                    <span x-show="copied === '{{ $tag }}'" x-cloak class="text-[11px] font-semibold text-emerald-600">Copied</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Test connection --}}
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="test('{{ $g['key'] }}')" wire:loading.attr="disabled" wire:target="test('{{ $g['key'] }}')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary/30 bg-primary/5 px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/10 disabled:opacity-50 dark:text-teal-300">
                        <span wire:loading.remove wire:target="test('{{ $g['key'] }}')">Test connection</span>
                        <span wire:loading wire:target="test('{{ $g['key'] }}')" class="inline-flex items-center gap-1.5"><x-ui.spinner class="h-3.5 w-3.5" /> Testing…</span>
                    </button>
                    @if ($g['probe'])
                        <span @class(['text-xs font-medium', 'text-emerald-600 dark:text-emerald-400' => $g['probe']['ok'], 'text-red-600 dark:text-red-400' => ! $g['probe']['ok']])>
                            {{ $g['probe']['message'] }}
                        </span>
                    @elseif (! $g['testable'])
                        <span class="text-[11px] text-slate-400">Auto-test not available — verify with a small live payment.</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- HOTFIX §4: dual sandbox/live keys for the card gateways. The active set
         follows each gateway's Sandbox/Live toggle above. Secrets are never
         echoed back — a blank field leaves the stored key unchanged. --}}
    <div class="mt-8">
        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Sandbox &amp; live keys</h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Store both key sets once; the Sandbox/Live toggle above picks which one is live. A blank field leaves the saved key unchanged.</p>

        <div class="space-y-4">
            @foreach ($credGateways as $cg)
                <div wire:key="cred-{{ $cg['key'] }}" class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $cg['label'] }}</span>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $cg['mode'] === 'sandbox',
                            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $cg['mode'] === 'live',
                        ])>Active: {{ ucfirst($cg['mode']) }}</span>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['sandbox' => 'Sandbox', 'live' => 'Live'] as $mode => $modeLabel)
                            <div class="rounded-xl border border-slate-100 p-3 dark:border-[#243352]">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide {{ $cg['mode'] === $mode ? 'text-primary dark:text-teal-300' : 'text-slate-400' }}">{{ $modeLabel }}{{ $cg['mode'] === $mode ? ' · active' : '' }}</p>
                                @foreach ($cg['types'] as $type)
                                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ $type === 'secret_key' ? 'Secret key' : 'Public key' }}</label>
                                    <input type="password" autocomplete="off" wire:model="creds.{{ $cg['key'] }}.{{ $mode }}.{{ $type }}"
                                           placeholder="{{ $cg['previews'][$mode][$type] ?? 'Not set' }}"
                                           class="mb-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex justify-end">
            <x-ui.btn variant="primary" type="button" wire:click="saveCredentials" target="saveCredentials" icon="badge-check">Save keys</x-ui.btn>
        </div>
    </div>
</div>
