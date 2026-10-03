{{-- Developer API on the skin system (S3 Batch 4): same keys, scopes, one-time reveal, wallet top-ups, rotate / revoke. --}}
<div>
<x-nx.page class="ns-pg">
    <div class="ns-pg__head">
        <div>
            <h1 class="ns-h1" style="margin-top:6px">Developer API</h1>
            <p class="ns-sub">Create keys, top up your prepaid API balance, and resell from your own app.</p>
        </div>
        <div class="ns-pg__actions">
            <a href="{{ route('guide', ['audience' => 'developer']) }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><x-nx.icon name="info" /> Guide</a>
            <a href="{{ route('developers') }}" target="_blank" rel="noopener" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><x-nx.icon name="file" /> API docs</a>
        </div>
    </div>

    @if ($error)
        <div class="ns-pg__err" role="alert"><x-nx.icon name="x" /> <span>{{ $error }}</span></div>
    @endif

    {{-- One-time key reveal --}}
    @if ($plainToken)
        <div class="ns-pg__reveal" x-data="{ copied: false, key: @js($plainToken) }">
            <b class="ns-pg__h2" style="display:flex;align-items:center;gap:8px;color:rgb(var(--nx-teal-ink))"><x-nx.icon name="lock" /> Your new API key: copy it now</b>
            <p class="ns-pg__hint">For security this is shown only once. Store it safely.</p>
            <div class="ns-pg__row" style="margin-top:12px;flex-wrap:nowrap;align-items:stretch">
                <code class="ns-pg__code is-grow" style="flex:1;min-width:0">{{ $plainToken }}</code>
                <button type="button" x-on:click="navigator.clipboard.writeText(key).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" class="ns-cta ns-cta--pill ns-cta--sm">
                    <x-nx.icon name="file" /> <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
            <button type="button" wire:click="hideToken" class="ns-pg__act" style="margin-top:12px">I've saved it, hide</button>
        </div>
    @endif

    {{-- Wallet + create --}}
    <div class="ns-pg__card ns-ring">
        <div class="ns-pg__kv"><span>Your wallet balance (funds top-ups)</span><b>${{ number_format($walletUsd, 2) }}</b></div>

        <h2 class="ns-pg__h2" style="margin-top:18px">Create a new API key</h2>
        <div class="ns-pg__stack" style="margin-top:12px">
            <div>
                <input type="text" wire:model="name" placeholder="Key name (e.g. My production app)" aria-label="Key name" class="ns-input">
                @error('name') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
            </div>

            <div class="ns-pg__chips" role="group" aria-label="Scopes">
                @foreach ($allScopes as $scope)
                    <label class="ns-pg__chip {{ in_array($scope, $scopes, true) ? 'is-on' : '' }}">
                        <input type="checkbox" wire:model="scopes" value="{{ $scope }}" class="sr-only">
                        {{ ucfirst($scope) }}
                    </label>
                @endforeach
            </div>

            <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create" class="ns-cta">
                <span wire:loading.remove wire:target="create" class="ns-cta__label"><x-nx.icon name="lock" /> Create key</span>
                <span wire:loading wire:target="create" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Creating…</span>
            </button>
        </div>
    </div>

    {{-- Existing keys --}}
    <h2 class="ns-pg__h2" style="margin-top:24px">Your API keys</h2>
    <div class="ns-pg__stack" style="margin-top:12px">
        @forelse ($clients as $client)
            <div class="ns-pg__card ns-pg__card--tight ns-ring" style="margin-top:0" wire:key="client-{{ $client->id }}">
                <div class="ns-pg__head" style="align-items:center">
                    <div style="display:flex;align-items:center;gap:10px">
                        <b class="ns-pg__h2">{{ $client->name }}</b>
                        @if ($client->is_active)<span class="ns-st ns-st--ok">Active</span>@else<span class="ns-st">Revoked</span>@endif
                    </div>
                    <span class="ns-pg__mono">…{{ $client->token_last_four ?? '----' }}</span>
                </div>

                <div class="ns-pg__chips" style="margin-top:10px;gap:6px">
                    @foreach ($client->scopes ?? [] as $scope)<span class="ns-pg__tag">{{ $scope }}</span>@endforeach
                </div>

                <div class="ns-pg__kv" style="margin-top:12px"><span>API balance</span><b class="is-accent">${{ number_format((float) $client->prepaid_balance_usd, 2) }}</b></div>

                @if ($client->is_active)
                    <div class="ns-pg__row" style="margin-top:12px">
                        <div class="is-grow">
                            <label class="ns-pg__lbl" for="topup-{{ $client->id }}">Top up from wallet (USD)</label>
                            <input id="topup-{{ $client->id }}" type="number" min="1" step="0.01" wire:model="topUp.{{ $client->id }}" placeholder="10.00" class="ns-input">
                        </div>
                        <button type="button" wire:click="fund({{ $client->id }})" wire:loading.attr="disabled" wire:target="fund({{ $client->id }})" class="ns-cta ns-cta--pill" style="height:50px"><x-nx.icon name="wallet" /> Top up</button>
                    </div>
                    <div class="ns-pg__sep">
                        <button type="button" wire:click="rotate({{ $client->id }})" class="ns-pg__act"><x-nx.icon name="refresh" /> Rotate key</button>
                        <button type="button" wire:click="revoke({{ $client->id }})" wire:confirm="Revoke this key? Apps using it will stop working immediately." class="ns-pg__act ns-pg__act--bad"><x-nx.icon name="trash" /> Revoke</button>
                    </div>
                @endif
            </div>
        @empty
            <x-nx.empty text="No API keys yet. Create one above to start building." />
        @endforelse
    </div>
</x-nx.page>
</div>
