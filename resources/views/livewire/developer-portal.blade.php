<div class="mx-auto max-w-3xl">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Developer API</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Create keys, top up your prepaid API balance, and resell from your own app.
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('guide', ['audience' => 'developer']) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200 dark:hover:bg-[#243352]">
                <x-icon name="help-circle" class="h-4 w-4" /> Guide
            </a>
            <a href="{{ route('developers') }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200 dark:hover:bg-[#243352]">
                <x-icon name="file-text" class="h-4 w-4" /> API docs
            </a>
        </div>
    </div>

    @if ($error)
        <div class="mb-4 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
            <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
        </div>
    @endif

    {{-- One-time key reveal --}}
    @if ($plainToken)
        <div class="mb-6 rounded-2xl border-2 border-primary/40 bg-primary/5 p-5 dark:bg-primary/10"
             x-data="{ copied: false, key: @js($plainToken) }">
            <div class="flex items-center gap-2 text-sm font-semibold text-primary-dark dark:text-primary">
                <x-icon name="key" class="h-4 w-4" /> Your new API key — copy it now
            </div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">For security this is shown only once. Store it safely.</p>
            <div class="mt-3 flex items-center gap-2">
                <code class="flex-1 overflow-x-auto rounded-lg bg-navy px-3 py-2.5 font-mono text-xs text-slate-100">{{ $plainToken }}</code>
                <button type="button" x-on:click="navigator.clipboard.writeText(key).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
                    <x-icon name="copy" class="h-4 w-4" /> <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
            <button type="button" wire:click="hideToken" class="mt-3 text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                I’ve saved it — hide
            </button>
        </div>
    @endif

    {{-- Wallet + create --}}
    <div class="mb-6 rounded-2xl border border-slate-200 nx-glass-tile p-5 shadow-sm dark:border-[var(--brand-card-border-dark)]">
        <div class="mb-4 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-[#243352]">
            <span class="text-slate-500 dark:text-slate-400">Your wallet balance (funds top-ups)</span>
            <span class="font-bold text-slate-900 dark:text-slate-100">${{ number_format($walletUsd, 2) }}</span>
        </div>

        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Create a new API key</h2>
        <div class="mt-3 space-y-3">
            <input type="text" wire:model="name" placeholder="Key name (e.g. My production app)"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
            @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror

            <div class="flex flex-wrap gap-2">
                @foreach ($allScopes as $scope)
                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition
                        {{ in_array($scope, $scopes, true) ? 'border-primary bg-primary/10 text-primary-dark dark:text-primary' : 'border-slate-200 text-slate-600 dark:border-[var(--brand-card-border-dark)] dark:text-slate-300' }}">
                        <input type="checkbox" wire:model="scopes" value="{{ $scope }}" class="sr-only">
                        {{ ucfirst($scope) }}
                    </label>
                @endforeach
            </div>

            <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                    class="flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="key" wire:loading.remove wire:target="create" class="h-4 w-4" />
                <x-ui.spinner wire:loading wire:target="create" class="h-4 w-4" />
                Create key
            </button>
        </div>
    </div>

    {{-- Existing keys --}}
    <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100">Your API keys</h2>
    <div class="space-y-3">
        @forelse ($clients as $client)
            <div class="rounded-2xl border border-slate-200 nx-glass-tile p-4 shadow-sm dark:border-[var(--brand-card-border-dark)]" wire:key="client-{{ $client->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $client->name }}</span>
                        @if ($client->is_active)
                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-700 dark:bg-green-950/50 dark:text-green-300">Active</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Revoked</span>
                        @endif
                    </div>
                    <span class="font-mono text-xs text-slate-400">…{{ $client->token_last_four ?? '----' }}</span>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    @foreach ($client->scopes ?? [] as $scope)
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500 dark:bg-[#243352] dark:text-slate-400">{{ $scope }}</span>
                    @endforeach
                </div>

                <div class="mt-3 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-[#243352]">
                    <span class="text-slate-500 dark:text-slate-400">API balance</span>
                    <span class="font-bold text-primary-dark dark:text-primary">${{ number_format((float) $client->prepaid_balance_usd, 2) }}</span>
                </div>

                @if ($client->is_active)
                    <div class="mt-3 flex flex-wrap items-end gap-2">
                        <div class="flex-1">
                            <label class="mb-1 block text-[11px] font-medium text-slate-500 dark:text-slate-400">Top up from wallet (USD)</label>
                            <input type="number" min="1" step="0.01" wire:model="topUp.{{ $client->id }}" placeholder="10.00"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)] dark:text-slate-100">
                        </div>
                        <button type="button" wire:click="fund({{ $client->id }})" wire:loading.attr="disabled" wire:target="fund({{ $client->id }})"
                                class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                            <x-icon name="wallet" class="h-4 w-4" /> Top up
                        </button>
                    </div>
                    <div class="mt-3 flex items-center gap-3 border-t border-slate-100 pt-3 text-xs dark:border-[var(--brand-card-border-dark)]">
                        <button type="button" wire:click="rotate({{ $client->id }})"
                                class="inline-flex items-center gap-1 font-medium text-slate-500 hover:text-primary dark:text-slate-400">
                            <x-icon name="refresh" class="h-3.5 w-3.5" /> Rotate key
                        </button>
                        <button type="button" wire:click="revoke({{ $client->id }})"
                                wire:confirm="Revoke this key? Apps using it will stop working immediately."
                                class="inline-flex items-center gap-1 font-medium text-red-500 hover:text-red-600">
                            <x-icon name="trash" class="h-3.5 w-3.5" /> Revoke
                        </button>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-[var(--brand-card-border-dark)] dark:text-slate-400">
                No API keys yet. Create one above to start building.
            </div>
        @endforelse
    </div>
</div>
