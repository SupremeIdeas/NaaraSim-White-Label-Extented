@php
    $catalog = \App\Support\FeatureLocks::catalog();
    $tone = [
        'none' => ['label' => 'Not activated', 'dot' => 'bg-amber-500', 'ring' => 'ring-amber-500/30'],
        'active' => ['label' => 'Active', 'dot' => 'bg-emerald-500', 'ring' => 'ring-emerald-500/30'],
        'suspended' => ['label' => 'Suspended by the platform', 'dot' => 'bg-red-500', 'ring' => 'ring-red-500/30'],
        'revoked' => ['label' => 'Platform no longer accepts this install', 'dot' => 'bg-red-500', 'ring' => 'ring-red-500/30'],
        'unreachable' => ['label' => 'Platform not reachable', 'dot' => 'bg-amber-500', 'ring' => 'ring-amber-500/30'],
        'unverified' => ['label' => 'Answer could not be verified', 'dot' => 'bg-red-500', 'ring' => 'ring-red-500/30'],
    ][$s['status']];
    $steps = [
        ['Licence key', 'Paste the key you were sent.', $s['status'] !== 'none'],
        ['Register', 'This site tells the platform its brand, domain and version.', $s['status'] !== 'none'],
        ['Features unlock', 'Your plan\'s features switch on straight away.', $s['status'] === 'active'],
        ['Stays in touch', 'A check-in every six hours keeps the record live.', $s['status'] === 'active' && $s['lastCheckin']],
    ];
@endphp
<div class="mx-auto max-w-5xl" data-tour="license-activation" @if ($s['status'] !== 'none') wire:poll.30s @endif>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Setup</p>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Licence activation</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">Activating registers this site with the platform that licensed it. That is what unlocks your plan, delivers updates, and lets the platform see which domain you run on.</p>
        </div>
        <a href="{{ route('admin.activation-branding') }}" wire:navigate class="nx-btn nx-btn--ghost !px-4 !py-2 !text-sm">Name and branding</a>
    </div>

    @if ($notice)
        <div class="mb-5 flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200" role="status"><x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $notice }}</div>
    @endif
    @if ($error)
        <div class="mb-5 flex items-start gap-2 rounded-xl bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/30 dark:text-red-200" role="alert" data-license-error><x-icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $error }}</div>
    @endif

    {{-- Status hero --}}
    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
        <div class="flex flex-wrap items-center gap-4 p-5 sm:p-6">
            <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 ring-4 {{ $tone['ring'] }} dark:bg-white/10">
                <x-icon name="{{ $s['status'] === 'active' ? 'shield-check' : ($s['status'] === 'none' ? 'key' : 'shield-alert') }}" class="h-6 w-6 text-slate-800 dark:text-slate-100" />
                <span class="absolute -right-1 -top-1 h-3.5 w-3.5 rounded-full border-2 border-white {{ $tone['dot'] }} dark:border-[#14161a]"></span>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-lg font-bold text-slate-900 dark:text-slate-100" data-license-status="{{ $s['status'] }}">{{ $tone['label'] }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @if ($s['status'] === 'none') Nothing is confirmed by a licence yet.
                    @elseif ($s['status'] === 'active') {{ $s['tier'] ? ucfirst(str_replace('_', ' ', $s['tier'])).' plan' : 'Licensed' }}@if ($s['reference']) · ref {{ $s['reference'] }}@endif
                    @else {{ $s['lastError'] ?: 'Contact the platform operator.' }} @endif
                </p>
            </div>
            @if ($s['status'] !== 'none')
                <button type="button" wire:click="checkNow" wire:loading.attr="disabled" wire:target="checkNow" class="nx-btn nx-btn--ghost !px-4 !py-2 !text-sm">
                    <span wire:loading.remove wire:target="checkNow">Check in now</span><span wire:loading wire:target="checkNow">Checking...</span>
                </button>
            @endif
        </div>
        {{-- Stepper --}}
        <ol class="grid border-t border-slate-200 text-sm dark:border-white/10 sm:grid-cols-4">
            @foreach ($steps as $i => [$t, $d, $done])
                <li class="flex gap-3 border-slate-200 p-4 dark:border-white/10 {{ $i > 0 ? 'sm:border-l' : '' }} {{ $i > 0 ? 'border-t sm:border-t-0' : '' }}">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $done ? 'bg-emerald-600 text-white' : 'border border-slate-300 text-slate-500 dark:border-white/25 dark:text-slate-400' }}">@if ($done)<x-icon name="check" class="h-3.5 w-3.5" />@else{{ $i + 1 }}@endif</span>
                    <span><span class="block font-semibold text-slate-800 dark:text-slate-100">{{ $t }}</span><span class="block text-xs text-slate-500 dark:text-slate-400">{{ $d }}</span></span>
                </li>
            @endforeach
        </ol>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            @if ($s['status'] === 'none' || in_array($s['status'], ['revoked'], true))
                <form wire:submit="activate" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]" data-license-form>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $s['status'] === 'revoked' ? 'Activate again' : 'Activate this install' }}</h2>
                    <div class="mt-4 space-y-4">
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Licence key</span>
                            <input type="text" wire:model="licenseKey" autocomplete="off" spellcheck="false" maxlength="64" placeholder="Paste your key" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-slate-100">
                            <span class="mt-1 block text-xs text-slate-500">Sent to you when your licence was approved. It is used once and never stored here.</span>
                            @error('licenseKey') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Platform address</span>
                            <input type="url" wire:model="platformUrl" placeholder="https://" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-slate-100">
                            <span class="mt-1 block text-xs text-slate-500">Where your licence was issued. It came with your key.</span>
                            @error('platformUrl') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Contact email</span>
                            <input type="email" wire:model="contactEmail" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-slate-100">
                            @error('contactEmail') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </label>
                        <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-white/5 dark:text-slate-300">The platform will be told: your brand name <b>{{ \App\Support\BrandSettings::name() }}</b>, the domain <b>{{ $s['liveDomain'] }}</b>, and this build's version. Nothing about your customers or money is sent.</p>
                        <button type="submit" wire:loading.attr="disabled" wire:target="activate" class="nx-btn nx-btn--primary !px-6 !py-2.5">
                            <span wire:loading.remove wire:target="activate">Activate</span><span wire:loading wire:target="activate">Activating...</span>
                        </button>
                    </div>
                </form>
            @else
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">What your licence includes</h2>
                    @if (empty($locks))
                        <p class="mt-3 flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400"><x-icon name="check" class="h-4 w-4" /> Every feature is switched on for this install.</p>
                    @else
                        <p class="mt-3 text-xs font-medium uppercase tracking-wide text-slate-400">Not in your plan</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($locks as $k)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"><x-icon name="lock" class="h-3.5 w-3.5" /> {{ $catalog[$k] ?? $k }}</span>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">To add more, ask the platform operator to upgrade your licence, then press Check in now.</p>
                    @endif
                    <a href="{{ route('admin.updater') }}" wire:navigate class="mt-4 inline-block text-sm font-semibold text-slate-900 underline dark:text-white">Updates and themes</a>
                </section>
            @endif
        </div>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Registered as</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Domain</dt><dd class="truncate font-mono text-xs text-slate-800 dark:text-slate-100" data-license-domain>{{ $s['domain'] ?: '—' }}</dd></div>
                    @if ($s['status'] !== 'none' && $s['domain'] && $s['domain'] !== $s['liveDomain'])
                        <div class="rounded-lg bg-amber-50 p-2 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">This site now answers on <b>{{ $s['liveDomain'] }}</b>. The platform flags a domain that differs from the one first activated; if you moved on purpose, tell the operator.</div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Key</dt><dd class="font-mono text-xs text-slate-800 dark:text-slate-100">{{ $s['keyLast4'] ? '••••'.$s['keyLast4'] : '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Activated</dt><dd class="text-slate-800 dark:text-slate-100">{{ $s['activatedAt']?->diffForHumans() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Last check-in</dt><dd class="text-slate-800 dark:text-slate-100" data-license-last>{{ $s['lastCheckin']?->diffForHumans() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Proof</dt><dd class="text-right text-xs {{ $s['verified'] ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}" data-license-proof="{{ $s['verified'] ? 'verified' : ($s['keyInstalled'] ? 'unverified' : 'nokey') }}">@if ($s['verified']) Signed by the platform @if ($s['validUntil'])<span class="block text-slate-500">valid to {{ $s['validUntil']->format('j M Y') }}</span>@endif @elseif ($s['keyInstalled']) Not verified yet @else No platform key installed @endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Platform</dt><dd class="truncate text-xs text-slate-800 dark:text-slate-100">{{ $s['platform'] ? parse_url($s['platform'], PHP_URL_HOST) : '—' }}</dd></div>
                </dl>
                @if (! $s['keyInstalled'])
                    <p class="mt-3 rounded-lg bg-amber-50 p-2 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-200" data-license-nokey>This install has no platform verification key (<code>NAARA_LICENCE_PUBLIC_KEY</code>), so it cannot tell a real licence answer from a forged one. Ask the platform operator for it.</p>
                @endif
                @if ($s['viaEnv'])
                    <p class="mt-3 text-xs text-slate-500">Connected through the server's .env file. Activate here to manage it from the admin.</p>
                @endif
            </section>

            @if ($s['status'] !== 'none' && $isSuper)
                <section class="rounded-2xl border border-slate-200 p-5 text-sm dark:border-white/15">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Move or reset</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Disconnecting forgets this install's token. The site keeps running; activate again to reconnect.</p>
                    @if ($confirmDisconnect)
                        <div class="mt-3 flex gap-2">
                            <button type="button" wire:click="disconnect" class="nx-btn nx-btn--ghost !px-3 !py-1.5 !text-xs">Yes, disconnect</button>
                            <button type="button" wire:click="$set('confirmDisconnect', false)" class="nx-btn nx-btn--ghost !px-3 !py-1.5 !text-xs">Cancel</button>
                        </div>
                    @else
                        <button type="button" wire:click="$set('confirmDisconnect', true)" class="mt-3 text-xs font-semibold text-slate-900 underline dark:text-white">Disconnect this install</button>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</div>
