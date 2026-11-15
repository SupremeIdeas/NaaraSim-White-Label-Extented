<div>
<x-nx.page class="ns-tp oc-page">
    <section class="oc-hero" data-tour="crypto-hero">
        <div>
            <h1>Top up with crypto</h1>
            <p>Pay with USDC or USDT from your own wallet. It lands in your wallet in US dollars, one for one.</p>
            <div class="oc-trust">
                <x-nx.pill variant="rec">Non-custodial</x-nx.pill>
                <x-nx.pill>Double-checked on 2 nodes</x-nx.pill>
                <x-nx.pill>1 : 1 in USD</x-nx.pill>
            </div>
        </div>
        <div class="oc-hero__art">@include('partials.crypto-illustration')</div>
    </section>
    @if ($notice)<p class="ns-tp__detect" role="status"><x-nx.icon name="check" /> {{ $notice }}</p>@endif
    @if ($problem)<p class="ns-tp__err" role="alert"><x-nx.icon name="alert-triangle" /> {{ $problem }}</p>@endif

    @if (! $chain)
        <section class="oc-card"><p class="oc-sub">Crypto top-up is not taking payments right now. Please check back soon.</p></section>
    @else
    @assets
        @vite('resources/js/crypto/topup.js')
    @endassets
    <div x-data="{
            cfg: @js($config),
            address: null, chain: null, mode: null, ready: false, busy: false, step: 'idle', msg: '', picker: false, has: {}, here: location.href,
            get mobile() { return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent); },
            get wc() { return !!this.cfg.projectId; },
            deep(kind) { const u = encodeURIComponent(this.here); return { metamask: 'https://metamask.app.link/dapp/' + this.here.replace(/^https?:\/\//, ''), trust: 'https://link.trustwallet.com/open_url?coin_id=60&url=' + u, coinbase: 'https://go.cb-w.com/dapp?cb_url=' + u }[kind]; },
            async pick(kind) {
                this.msg = '';
                if (kind === 'walletconnect') { if (!this.wc) { this.msg = 'WalletConnect is not switched on yet. Pick another wallet, or ask the site admin to finish setup.'; return; } this.picker = false; return this.connect('walletconnect'); }
                if (this.has[kind]) { this.picker = false; return this.connect(kind); }
                if (this.mobile) { window.location.href = this.deep(kind); return; }
                this.msg = { metamask: 'MetaMask is not installed in this browser. Install it from metamask.io, or choose WalletConnect to use your phone.', trust: 'Open this page in the Trust Wallet app (Browser tab), or choose WalletConnect to scan from your phone.', coinbase: 'Coinbase Wallet is not installed in this browser. Choose WalletConnect to use your phone.' }[kind];
            },
            amount: '25', symbol: '', chainPick: {{ (int) $chain->chain_id }},
            get linked() { return this.address && ($wire.linkedList || []).includes(this.address.toLowerCase()); },
            get net() { return this.cfg.byChain[this.chainPick]; },
            get token() { return (this.net?.tokens || []).find(t => t.symbol === this.symbol) || (this.net?.tokens || [])[0]; },
            get stepIdx() { return this.step === 'sent' ? 3 : (!this.address ? 0 : (!this.linked ? 1 : 2)); },
            get amt() { const v = parseFloat(this.amount); return isNaN(v) ? 0 : v; },
            get amtErr() { const t = this.token; if (!t || this.amount === '') return ''; if (!/^\d{1,7}(\.\d{1,2})?$/.test(this.amount.trim())) return 'Enter a plain amount like 25 or 25.50'; if (this.amt < t.min) return 'Minimum is $' + t.min; if (this.amt > t.max) return 'Maximum is $' + t.max.toLocaleString() + ' per payment'; return ''; },
            get short() { return this.address ? this.address.slice(0, 6) + '…' + this.address.slice(-4) : ''; },
            async boot() {
                if (!window.CryptoTopupJs) { await new Promise(r => document.addEventListener('crypto-topup-ready', r, { once: true })); }
                const r = await window.CryptoTopupJs.init(this.cfg, {
                    account: (a) => { this.address = a; this.afterConnect(); },
                    chain: (c) => { if (c && this.cfg.byChain[c]) this.chainPick = c; this.chain = c; },
                });
                this.mode = r.mode; this.has = window.CryptoTopupJs.installed(); this.ready = true;
            },
            async connect(kind) { this.msg = ''; try { await window.CryptoTopupJs.connect(kind); } catch (e) { this.msg = e.message || 'Could not open your wallet.'; } },
            async afterConnect() { if (this.address && !this.linked && this.step === 'idle') { this.step = 'linking'; await $wire.startLink(this.address, this.chainPick); } },
            async onSign(d) {
                try { const sig = await window.CryptoTopupJs.sign(d.message); await $wire.finishLink(d.id, sig); }
                catch (e) { await $wire.walletRefused(e && (e.code === 4001 || e.code === 'ACTION_REJECTED') ? 'You declined to sign. Nothing was linked.' : ''); }
                this.step = 'idle';
            },
            async pay() {
                const t = this.token, n = this.net; this.msg = '';
                const v = parseFloat(this.amount);
                if (!t || !(v >= t.min) || v > t.max) { this.msg = 'Enter an amount between $' + (t ? t.min : 5) + ' and $' + (t ? t.max : 2000) + '.'; return; }
                this.busy = true; this.step = 'sending';
                try {
                    const hash = await window.CryptoTopupJs.pay({ chainId: Number(this.chainPick), contract: t.contract, decimals: t.decimals, to: n.to, amount: String(this.amount) });
                    this.step = 'sent'; await $wire.submitted(hash, Number(this.chainPick));
                } catch (e) {
                    this.step = 'idle';
                    this.msg = e.friendly ? e.message : (e && (e.code === 4001 || e.code === 'ACTION_REJECTED') ? 'You cancelled the payment in your wallet. Nothing was sent.' : (e && (e.code === 'INSUFFICIENT_FUNDS' || /insufficient funds/i.test(e.message || '')) ? 'Your wallet needs a little ' + (Number(this.chainPick) === 137 ? 'POL' : 'ETH') + ' to pay the network fee. Add a small amount and try again.' : 'The payment could not be sent. Check your wallet and balance, then try again.'));
                }
                this.busy = false;
            }
         }" x-init="boot()" x-on:crypto-sign.window="onSign($event.detail)">


        <div x-show="picker" x-cloak x-transition.opacity class="fixed inset-0 z-[70] flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4" x-on:keydown.escape.window="picker = false" x-on:click.self="picker = false" role="dialog" aria-modal="true" aria-label="Choose a wallet" data-crypto-picker>
            <div class="w-full max-w-md rounded-t-3xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-2xl sm:rounded-3xl">
                <div class="mb-1 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-[rgb(var(--nx-text))]">Connect a wallet</h2>
                    <button type="button" class="rounded-full p-1 text-[rgb(var(--nx-text-2))]" x-on:click="picker = false" aria-label="Close"><x-nx.icon name="x" /></button>
                </div>
                <p class="mb-4 text-sm text-[rgb(var(--nx-text-2))]">Choose the wallet you use. You will confirm in the wallet itself. We never see your keys.</p>
                <ul class="space-y-2">
                    @foreach ([
                        ['metamask', 'MetaMask', 'Browser extension or the MetaMask app'],
                        ['trust', 'Trust Wallet', 'Open in the Trust Wallet app'],
                        ['coinbase', 'Coinbase Wallet', 'Extension or the Coinbase Wallet app'],
                    ] as [$k, $name, $sub])
                        <li><button type="button" class="flex w-full items-center gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-left hover:border-[rgb(var(--nx-teal-ink))]" x-on:click="pick('{{ $k }}')" data-wallet="{{ $k }}">
                            <img src="{{ asset('images/wallets/'.($k === 'trust' ? 'trust' : $k).'.svg') }}" alt="" width="44" height="44" class="h-11 w-11 shrink-0 rounded-xl" loading="lazy">
                            <span class="min-w-0 flex-1"><b class="block text-[rgb(var(--nx-text))]">{{ $name }}</b><small class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $sub }}</small></span>
                            <x-nx.pill x-show="has.{{ $k }}" variant="rec">Detected</x-nx.pill>
                        </button></li>
                    @endforeach
                    <li><button type="button" class="flex w-full items-center gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-left hover:border-[rgb(var(--nx-teal-ink))]" x-on:click="pick('walletconnect')" data-wallet="walletconnect">
                        <img src="{{ asset('images/wallets/walletconnect.svg') }}" alt="" width="44" height="44" class="h-11 w-11 shrink-0 rounded-xl" loading="lazy">
                        <span class="min-w-0 flex-1"><b class="block text-[rgb(var(--nx-text))]">WalletConnect</b><small class="block text-xs text-[rgb(var(--nx-text-2))]">Scan with Rainbow, OKX and 300+ more</small>
                            <span class="mt-1 flex gap-1"><img src="{{ asset('images/wallets/rainbow.svg') }}" alt="" class="h-5 w-5 rounded-md" loading="lazy"><img src="{{ asset('images/wallets/okx.svg') }}" alt="" class="h-5 w-5 rounded-md" loading="lazy"></span></span>
                        <x-nx.pill x-show="!wc">Coming soon</x-nx.pill>
                    </button></li>
                    <li x-show="has.any && !has.metamask && !has.trust && !has.coinbase" x-cloak><button type="button" class="flex w-full items-center gap-3 rounded-2xl border border-[rgb(var(--nx-line))] p-3 text-left" x-on:click="picker = false; connect('any')">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[rgb(var(--nx-teal-ink)/0.12)]"><x-nx.icon name="wallet" /></span>
                        <span class="flex-1"><b class="block text-[rgb(var(--nx-text))]">Browser wallet</b><small class="block text-xs text-[rgb(var(--nx-text-2))]">The wallet built into this browser</small></span>
                    </button></li>
                </ul>
                <p class="mt-3 text-sm" x-show="msg" x-text="msg" style="color:rgb(var(--nx-bad))"></p>
                <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">New to crypto wallets? Install Trust Wallet or MetaMask, buy or receive USDC, then come back here.</p>
            </div>
        </div>


        <ol class="oc-steps" aria-label="Progress">
            @foreach (['Connect', 'Verify', 'Pay', 'Credited'] as $i => $lab)
                <li class="oc-step" x-bind:class="stepIdx > {{ $i }} ? 'is-done' : (stepIdx === {{ $i }} ? 'is-on' : '')">
                    <span class="oc-step__dot"><template x-if="stepIdx > {{ $i }}"><svg aria-hidden="true"><use href="#i-check"></use></svg></template><template x-if="stepIdx <= {{ $i }}"><span>{{ $i + 1 }}</span></template></span>{{ $lab }}
                </li>
            @endforeach
        </ol>

        <section class="oc-card" data-tour="crypto-link">
            <div class="oc-card__h"><i>1</i> Connect your wallet</div>
            <template x-if="!address">
                <div class="grid gap-3">
                    <p class="oc-sub">Trust Wallet, MetaMask, Coinbase Wallet, Rainbow and 300+ more. We never see your keys and can never move your funds: every payment needs your approval in your own wallet.</p>
                    <button type="button" class="oc-cta" x-on:click="picker = true" x-bind:disabled="!ready" data-crypto-connect>
                        <x-nx.icon name="wallet" /> <span x-text="ready ? 'Connect wallet' : 'Loading wallets…'"></span>
                    </button>
                </div>
            </template>
            <template x-if="address">
                <div class="oc-conn">
                    <span class="oc-conn__who"><span class="oc-conn__av"></span><span class="grid"><code x-text="short"></code><small class="oc-sub" x-text="linked ? 'Verified: payments from this wallet credit you' : (step === 'linking' ? 'Confirm the free signature in your wallet…' : 'Waiting for verification')"></small></span></span>
                    <span class="flex items-center gap-2"><x-nx.pill x-show="linked" variant="rec">Verified</x-nx.pill><button type="button" class="text-xs font-semibold text-[rgb(var(--nx-teal-ink))] underline" x-on:click="window.CryptoTopupJs.disconnect()">Disconnect</button></span>
                </div>
            </template>
            @if (! $canLink)<p class="oc-sub">Wallet verification is being set up on this server. Please check back soon.</p>@endif
            <p class="oc-err" x-show="msg" x-text="msg"></p>
            @foreach ($linked as $w)
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-[rgb(var(--nx-text-2))]" wire:key="lw-{{ $w->id }}">
                    <span>Linked: <code class="break-all">{{ $w->address }}</code></span>
                    <button type="button" wire:click="unlink({{ $w->id }})" wire:confirm="Unlink this wallet? Payments from it will no longer credit your account." class="font-semibold text-[rgb(var(--nx-teal-ink))] underline">Unlink</button>
                </div>
            @endforeach
        </section>

        <section class="oc-card" x-bind:class="stepIdx < 2 ? 'is-locked' : ''" data-tour="crypto-send">
            <div class="oc-card__h"><i>2</i> Choose an amount</div>
            <div class="oc-seg" role="group" aria-label="Network" x-show="cfg.chains.length > 1">
                <template x-for="c in cfg.chains" :key="c"><button type="button" class="oc-opt" x-bind:class="Number(chainPick) === c ? 'is-on' : ''" x-on:click="chainPick = c; symbol = ''" x-text="cfg.byChain[c].name"></button></template>
            </div>
            <div class="oc-seg" role="group" aria-label="Token">
                <template x-for="t in (net?.tokens || [])" :key="t.symbol"><button type="button" class="oc-opt" x-bind:class="token && token.symbol === t.symbol ? 'is-on' : ''" x-on:click="symbol = t.symbol"><i class="oc-opt__coin" x-bind:class="'oc-opt__coin--' + t.symbol.toLowerCase()"></i><span x-text="t.symbol"></span></button></template>
            </div>
            <label class="oc-amount"><span>$</span><input type="text" inputmode="decimal" x-model="amount" aria-label="Amount in US dollars" autocomplete="off"></label>
            <p class="oc-limit" x-show="!amtErr" x-text="token ? 'Between $' + token.min + ' and $' + token.max.toLocaleString() + ' per payment' : ''"></p>
            <p class="oc-err" style="text-align:center" x-show="amtErr" x-text="amtErr"></p>
            <div class="oc-seg" style="justify-content:center" role="group" aria-label="Quick amounts">
                <template x-for="q in [10, 25, 50, 100, 250]" :key="q"><button type="button" class="oc-opt" x-bind:class="amt === q ? 'is-on' : ''" x-on:click="amount = String(q)" x-text="'$' + q"></button></template>
            </div>
            <div class="oc-sum">
                <div><span>You pay</span><b x-text="(amt || 0).toLocaleString(undefined, {maximumFractionDigits: 2}) + ' ' + (token ? token.symbol : '') + ' on ' + (net ? net.name : '')"></b></div>
                <div class="is-get"><span>You get in your wallet</span><b x-text="'$' + (amt || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></b></div>
                <div><span>Network fee</span><b>Paid from your wallet, usually a few cents</b></div>
                <div><span>Arrives</span><b>After the network confirms, usually 1 to 5 minutes</b></div>
            </div>
            <button type="button" class="oc-cta" x-bind:class="step === 'sending' ? 'is-busy' : ''" x-on:click="address ? pay() : (picker = true)" x-bind:disabled="busy || (address && !linked) || !!amtErr || amt <= 0">
                <x-nx.icon name="zap" />
                <span x-text="!address ? 'Connect wallet to continue' : (!linked ? 'Verify your wallet first' : (step === 'sending' ? 'Confirm in your wallet…' : 'Top up $' + (amt || 0) + ' with ' + (token ? token.symbol : 'crypto')))"></span>
            </button>
            <p class="oc-sub" style="text-align:center">Your wallet asks you to approve the exact amount. Payments from $500 are checked by our team first.</p>

            <details class="oc-sub">
                <summary style="cursor:pointer;font-weight:600">Prefer to send from any wallet yourself?</summary>
                <div class="mt-2 rounded-lg border border-[rgb(var(--nx-line))] p-3" x-data="{ copied: false }">
                    <p class="oc-sub">Send only {{ $tokens->pluck('symbol')->implode(' or ') }} on <b>{{ $chain->name }}</b> to:</p>
                    <code class="my-2 block break-all text-sm font-semibold text-[rgb(var(--nx-text))]" data-crypto-address>{{ $depositAddress }}</code>
                    <button type="button" class="ns-btn" x-on:click="navigator.clipboard.writeText('{{ $depositAddress }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"><x-nx.icon name="copy" /> <span x-text="copied ? 'Copied' : 'Copy address'"></span></button>
                    <p class="oc-sub" style="margin-top:8px">Send from a wallet you have verified above. Other coins or networks cannot be credited automatically.</p>
                </div>
            </details>
        </section>
    </div>

    <section class="oc-card" data-tour="crypto-history" wire:poll.8s>
        <div class="oc-card__h"><i>3</i> Your payments</div>
        @php($seen = $deposits->pluck('tx_hash')->all())
        @if ($lastHash && ! in_array($lastHash, $seen, true))
            <div class="oc-tx">
                <div class="oc-tx__top"><b>Payment sent</b><x-nx.pill>Confirming</x-nx.pill></div>
                <ul class="oc-tl"><li class="is-done">Sent</li><li class="is-on">Confirming</li><li>Credited</li></ul>
                @if (! empty($explorers[$lastChain]))<a href="{{ str_replace('{hash}', $lastHash, $explorers[$lastChain]) }}" target="_blank" rel="noopener noreferrer">View on the block explorer</a>@endif
            </div>
        @endif
        @forelse ($deposits as $d)
            @php($idx = match ($d->status) { 'credited' => 3, 'detected', 'confirming', 'verified', 'credit_pending' => 1, default => 1 })
            <div class="oc-tx" wire:key="dp-{{ $d->id }}">
                <div class="oc-tx__top"><b>${{ number_format((float) $d->usd_amount, 2) }} {{ $d->token?->symbol }}</b>
                    <x-nx.pill :variant="$d->status === 'credited' ? 'rec' : ($d->status === 'held_review' ? 'gold' : null)">{{ match ($d->status) { 'credited' => 'Credited', 'detected', 'confirming', 'verified', 'credit_pending' => 'Confirming', 'held_review' => 'Being reviewed', 'unclaimed' => 'Link your wallet', 'paused_hold' => 'On hold', 'reorged' => 'Not final', default => ucfirst(str_replace('_', ' ', $d->status)) } }}</x-nx.pill></div>
                @if (in_array($d->status, ['credited', 'detected', 'confirming', 'verified', 'credit_pending'], true))
                    <ul class="oc-tl"><li class="is-done">Sent</li><li class="{{ $idx === 3 ? 'is-done' : 'is-on' }}">Confirming</li><li class="{{ $idx === 3 ? 'is-done' : '' }}">Credited</li></ul>
                @elseif ($d->status === 'held_review')<p class="oc-sub">Larger payments are checked by our team. It credits once approved.</p>
                @elseif ($d->status === 'unclaimed')<p class="oc-sub">This came from a wallet that is not verified on your account. Verify it above and it credits straight away.</p>
                @endif
                <span class="oc-sub">{{ $d->created_at?->diffForHumans() }}@if (! empty($explorers[$d->chain_id])) · <a href="{{ str_replace('{hash}', $d->tx_hash, $explorers[$d->chain_id]) }}" target="_blank" rel="noopener noreferrer">View on explorer</a>@endif</span>
            </div>
        @empty
            @if (! $lastHash)<p class="oc-sub">Nothing yet. Payments you send will show here with live progress.</p>@endif
        @endforelse
    </section>
    @endif
</x-nx.page>
</div>
