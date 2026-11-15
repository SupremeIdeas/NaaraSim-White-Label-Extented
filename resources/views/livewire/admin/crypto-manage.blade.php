{{-- nx:converted (skin tokens) --}}
<div class="mx-auto max-w-5xl space-y-6" data-tour="crypto-admin">
    <div>
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Crypto top-up</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">Members send USDC or USDT from a wallet they have linked, to the receiving addresses below. Funds go straight to the receiving addresses you set below; we only watch the chain and credit the wallet after the transfer is final. No private key is ever stored here.</p>
    </div>

    @if ($notice)
        <div class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-ok))]"><x-icon name="badge-check" class="h-4 w-4" /> {{ $notice }}</div>
    @endif
    @if ($error)
        <div class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-bad)/0.12)] px-4 py-3 text-sm text-[rgb(var(--nx-bad))]"><x-icon name="alert-triangle" class="h-4 w-4" /> {{ $error }}</div>
    @endif

    @php($done = collect($checklist)->where('ok', true)->count())
    <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" data-tour="crypto-checklist">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Setup checklist</p>
            <x-nx.pill :variant="$done === count($checklist) ? 'rec' : 'gold'">{{ $done === count($checklist) ? 'Ready for real payments' : $done.' of '.count($checklist).' done' }}</x-nx.pill>
        </div>
        <ul class="divide-y divide-[rgb(var(--nx-line))]">
            @foreach ($checklist as $item)
                <li class="flex items-start gap-3 py-2">
                    <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full {{ $item['ok'] ? 'bg-[rgb(var(--nx-ok))] text-white' : 'border border-[rgb(var(--nx-line-strong))] text-transparent' }}">
                        <svg class="h-3 w-3" aria-hidden="true"><use href="#i-check"></use></svg></span>
                    <span class="min-w-0 flex-1 text-sm"><b class="text-[rgb(var(--nx-text))]">{{ $item['title'] }}</b><small class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $item['detail'] }}</small></span>
                    @unless ($item['ok'])<a href="#{{ $item['anchor'] }}" class="shrink-0 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] underline">Fix</a>@endunless
                </li>
            @endforeach
        </ul>
    </section>

    <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" data-tour="crypto-health">
        @php($hw = $health ? \App\Services\Crypto\Health\CryptoSelfTest::worst($health['checks']) : null)
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Health and authenticity (runs every hour)</p>
            <span class="flex items-center gap-2">
                @if ($health)<x-nx.pill :variant="$hw === 'ok' ? 'rec' : ($hw === 'warn' ? 'gold' : null)">{{ $hw === 'ok' ? 'All good' : ($hw === 'warn' ? 'Needs attention' : 'Problem found') }}</x-nx.pill>@endif
                <button type="button" wire:click="runSelfTest" wire:loading.attr="disabled" wire:target="runSelfTest" class="ns-btn"><span wire:loading.remove wire:target="runSelfTest">Run checks now</span><span wire:loading wire:target="runSelfTest">Checking…</span></button>
            </span>
        </div>
        @if ($health)
            <p class="mb-2 text-xs text-[rgb(var(--nx-text-2))]">Last run {{ \Illuminate\Support\Carbon::parse($health['at'])->diffForHumans() }}. Proves the maths, the wallet signature check, the issuers' official contracts, every endpoint, the scanner, stuck deposits and that the wallet ledger matches the deposits to the cent. You are alerted automatically if anything fails.</p>
            <ul class="divide-y divide-[rgb(var(--nx-line))]">
                @foreach ($health['checks'] as $c)
                    <li class="flex items-start gap-3 py-2 text-sm">
                        <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $c['status'] === 'ok' ? 'bg-[rgb(var(--nx-ok))]' : ($c['status'] === 'warn' ? 'bg-[rgb(var(--nx-warn))]' : 'bg-[rgb(var(--nx-bad))]') }}"></span>
                        <span class="min-w-0"><b class="text-[rgb(var(--nx-text))]">{{ $c['title'] }}</b><small class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $c['detail'] }}</small></span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-[rgb(var(--nx-text-2))]">Not run yet. Press "Run checks now"; after that it runs by itself every hour.</p>
        @endif
    </section>

    <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" data-tour="crypto-guide">
        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Setup guide</p>
        <p class="mb-3 text-sm text-[rgb(var(--nx-text-2))]">Everything from nothing to a first real payment, in order. Open a step to read it.</p>
        <div class="divide-y divide-[rgb(var(--nx-line))] text-sm text-[rgb(var(--nx-text))]">
            <details class="py-2" open><summary class="cursor-pointer font-semibold">1. How it works, in 30 seconds</summary>
                <div class="mt-2 space-y-2 text-[rgb(var(--nx-text-2))]"><p>A member opens Wallet, then "Top up with crypto". They connect a crypto wallet (MetaMask, Trust Wallet, Coinbase Wallet or anything through WalletConnect) and sign one free message that proves the wallet is theirs. They choose an amount and the wallet asks them to approve one USDC or USDT transfer to your receiving address.</p>
                <p>You never hold their keys and cannot take money from their wallet; each payment needs their approval in the wallet. We watch the blockchain, wait until the payment is final, double-check it on two independent providers, match it to the member through the wallet they verified, and credit their wallet in US dollars, one for one. Nothing is credited twice, and anything unusual (a very large payment, an unknown sender, a pause) waits for you.</p></div></details>
            <details class="py-2"><summary class="cursor-pointer font-semibold">2. Install wallet verification support (once)</summary>
                <div class="mt-2 space-y-2 text-[rgb(var(--nx-text-2))]"><p>Use the "Wallet verification support" card below. On a VPS where PHP may install packages, press "Install now". On shared hosting, follow the cPanel or Plesk steps it shows you, or send your host the ready-made request. The badge turns green when it is done.</p></div></details>
            <details class="py-2"><summary class="cursor-pointer font-semibold">3. Turn on the full wallet picker (WalletConnect)</summary>
                <div class="mt-2 space-y-2 text-[rgb(var(--nx-text-2))]"><ol class="list-decimal space-y-1 pl-5"><li>Go to <b>cloud.reown.com</b> and sign up (free).</li><li>Create a project, choose "AppKit", platform "JavaScript".</li><li>In the project settings, add your website address (for example https://yourdomain.com) to the allowed domains. Wallets refuse to connect from a domain that is not listed.</li><li>Copy the Project ID (32 letters and numbers) and paste it into the "WalletConnect (Reown) project" box below, then Save.</li></ol>
                <p>Until you do this, members can still connect MetaMask, Trust Wallet and Coinbase Wallet from their own browser or app. The QR picker for 300+ other wallets needs the Project ID.</p></div></details>
            <details class="py-2"><summary class="cursor-pointer font-semibold">4. Networks, RPC endpoints and receiving addresses</summary>
                <div class="mt-2 space-y-2 text-[rgb(var(--nx-text-2))]"><p>Each network (Ethereum, Polygon, BNB) has its own card. "Watching" means we read that chain; "Crediting" means we are allowed to credit wallets from it. Everything starts switched off. Add a receiving address on Polygon or Ethereum, then press Start watching and Start crediting. BNB stays off because its stablecoins are bridged copies, not the issuers' own; switch it on only after you accept that risk.</p>
                <p><b>RPC endpoints</b> are how we read the chain. Two free public ones are set by default. For real volume, create a free key with Alchemy or Infura, paste its https address in the Primary box and press "Test and save": it is checked against the network before it is accepted. Keep a second provider as Secondary so every payment is verified twice.</p>
                <p><b>Receiving addresses</b> are where the money lands. Add the address of a wallet YOU control (a treasury wallet), never one you do not own: paste it under the network and press "Add address". To stop using an old one, press "Retire": we keep watching it for 45 days so a late payment is never lost. A network always keeps at least one active address. Only ever use addresses you control; we never hold or ask for a private key.</p></div></details>
            <details class="py-2"><summary class="cursor-pointer font-semibold">5. Accepted tokens and limits</summary>
                <div class="mt-2 space-y-2 text-[rgb(var(--nx-text-2))]"><p>Only USDC and USDT from the issuers' official contract addresses are accepted; anything else sent to your address is ignored, never credited. For each token press "Edit limits":</p>
                <ul class="list-disc space-y-1 pl-5"><li><b>Minimum</b>: smaller payments are held (they cost more in fees than they are worth).</li><li><b>Maximum single payment</b>: bigger payments are held.</li><li><b>Review from</b>: payments at or above this wait for your approval in "Latest deposits".</li><li><b>Daily cap per member</b>: a member's credits in 24 hours cannot pass this without your approval.</li></ul></div></details>
            <details class="py-2"><summary class="cursor-pointer font-semibold">6. Make sure the scheduler is running</summary>
                <div class="mt-2 space-y-2 text-[rgb(var(--nx-text-2))]"><p>The deposit scanner runs every minute from the platform's scheduler (cron). If the checklist says the scheduler is not running, open Admin, System health and follow the cron step there. Nothing is lost while it is stopped: the scanner catches up from where it stopped.</p></div></details>
            <details class="py-2"><summary class="cursor-pointer font-semibold">7. Your first real test (do this before announcing)</summary>
                <div class="mt-2 space-y-2 text-[rgb(var(--nx-text-2))]"><ol class="list-decimal space-y-1 pl-5"><li>Log in as a normal member (not admin) on your phone, open Wallet, then "Top up with crypto".</li><li>Connect your own wallet and confirm the free signature. It shows "Verified".</li><li>Top up $5. Confirm the payment in your wallet (you pay a small network fee).</li><li>Watch "Latest deposits" below: Detected, then Confirming, then Credited, usually within a few minutes. The member's wallet shows +$5.</li></ol>
                <p>The last checklist item turns green when a payment has been credited.</p></div></details>
            <details class="py-2"><summary class="cursor-pointer font-semibold">8. Day-to-day: holds, approvals, emergencies</summary>
                <div class="mt-2 space-y-2 text-[rgb(var(--nx-text-2))]"><ul class="list-disc space-y-1 pl-5"><li><b>Waiting for review</b> (gold badge): open the row, check the amount and sender, then Approve or Reject. Rejecting credits nothing.</li><li><b>Link your wallet</b> (unclaimed): the sender has not verified a wallet yet. It credits automatically the moment they verify the sending wallet.</li><li><b>Pause all crediting</b>: the emergency stop. Payments are still recorded, none is credited until you unpause.</li><li><b>Stop crediting</b> on one network if its provider has problems; the others keep working.</li><li>Every setting change here is written to the audit log.</li></ul></div></details>
            <details class="py-2"><summary class="cursor-pointer font-semibold">9. Troubleshooting</summary>
                <div class="mt-2 space-y-2 text-[rgb(var(--nx-text-2))]"><ul class="list-disc space-y-1 pl-5"><li><b>"Connect wallet" does nothing or says unavailable:</b> the member's browser has no wallet. They can open the page inside Trust Wallet or MetaMask (the picker does this on phones), or you can enable WalletConnect (step 3).</li><li><b>Wallet picker shows no wallet list or errors:</b> the Reown Project ID is missing, mistyped, or your domain is not on its allowed list.</li><li><b>A payment was sent but never credited:</b> check "Latest deposits". "Link your wallet" means the member must verify the sending wallet; "On hold" means crediting is paused or off; nothing at all usually means the scheduler is stopped or the coin or network was not one we accept.</li><li><b>RPC test fails:</b> the address must be a full https:// endpoint for the same network; a key from the wrong network is refused on purpose.</li></ul></div></details>
        </div>
    </section>

    <section id="crypto-switches" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Switches</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex items-center gap-3 rounded-lg border border-[rgb(var(--nx-line))] p-3 text-sm text-[rgb(var(--nx-text))]">
                <input type="checkbox" wire:model="enabled" class="h-4 w-4">
                <span><b>Crypto top-up is on</b><small class="block text-xs text-[rgb(var(--nx-text-2))]">Off: nothing is scanned and members never see the option.</small></span>
            </label>
            <label class="flex items-center gap-3 rounded-lg border border-[rgb(var(--nx-line))] p-3 text-sm text-[rgb(var(--nx-text))]">
                <input type="checkbox" wire:model="paused" class="h-4 w-4">
                <span><b>Pause all crediting</b><small class="block text-xs text-[rgb(var(--nx-text-2))]">Emergency stop: deposits are still recorded, none is credited.</small></span>
            </label>
        </div>
        <div class="mt-3 flex items-center justify-end">
            <button type="button" wire:click="saveSwitches" wire:loading.attr="disabled" wire:target="saveSwitches" class="ns-btn ns-btn--solid px-5">
                <span wire:loading.remove wire:target="saveSwitches">Save switches</span><span wire:loading wire:target="saveSwitches">Saving…</span>
            </button>
        </div>
    </section>

    <section class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" id="crypto-math" data-tour="crypto-math" @if ($mathRunning) wire:poll.3s="mathTick" @endif>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Wallet verification support (PHP gmp / bcmath)</p>
            <x-nx.pill :variant="$math['ok'] ? 'rec' : 'gold'">{{ $math['ok'] ? ($math['gmp'] ? 'gmp installed' : 'bcmath installed') : 'Not installed' }}</x-nx.pill>
        </div>
        @if ($math['ok'])
            <p class="text-sm text-[rgb(var(--nx-text-2))]">Members can verify their wallet. {{ $math['gmp'] ? 'gmp is the fast option, nothing more to do.' : 'bcmath works but is slower; gmp is better if your host offers it.' }}</p>
        @else
            <p class="mb-3 text-sm text-[rgb(var(--nx-text-2))]">Wallet verification needs a small PHP extension this server does not have yet. Without it members cannot link a wallet, so top-ups cannot be matched to them.</p>
            @if ($math['can_install'])
                <button type="button" wire:click="mathInstall" wire:loading.attr="disabled" wire:target="mathInstall" @disabled($mathRunning) class="ns-btn ns-btn--solid px-5">
                    <span wire:loading.remove wire:target="mathInstall">{{ $mathRunning ? 'Installing…' : 'Install now (one click)' }}</span><span wire:loading wire:target="mathInstall">Starting…</span>
                </button>
                <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Installs the PHP {{ $math['php'] }} gmp and bcmath packages and reloads PHP. The website may blink for a second.</p>
            @else
                <p class="mb-2 text-sm text-[rgb(var(--nx-text))]">{{ $math['blocker'] }}</p>
            @endif
            <details class="mt-3 text-sm text-[rgb(var(--nx-text))]" @if (! $math['can_install']) open @endif>
                <summary class="cursor-pointer font-semibold">Install it yourself (any host)</summary>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-[rgb(var(--nx-text-2))]">@foreach ($math['steps'] as $step)<li>{{ $step }}</li>@endforeach</ul>
                <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">When it is done, reload this page: the badge above turns green.</p>
            </details>
        @endif
        @if ($mathLog && $mathLog['log'] !== '')
            <pre class="mt-3 max-h-40 overflow-auto rounded-lg border border-[rgb(var(--nx-line))] p-2 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $mathLog['log'] }}</pre>
        @endif
    </section>

    <section id="crypto-reown" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">WalletConnect (Reown) project</p>
        <p class="mb-3 text-sm text-[rgb(var(--nx-text-2))]">Powers the "Connect wallet" picker (Trust Wallet, MetaMask, Coinbase, 300+ more). Create a free project at cloud.reown.com, add this site's domain to its allowed list, and paste the project id here. Until then members can only connect a wallet built into their browser.</p>
        <div class="flex flex-wrap items-center gap-2">
            <input type="text" wire:model="reownId" placeholder="32-character project id" class="min-w-0 flex-1 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]" autocomplete="off" spellcheck="false">
            <button type="button" wire:click="saveReown" wire:loading.attr="disabled" wire:target="saveReown" class="ns-btn ns-btn--solid px-4">Save</button>
            <x-nx.pill :variant="$reownId !== '' ? 'rec' : null">{{ $reownId !== '' ? 'WalletConnect on' : 'Injected wallets only' }}</x-nx.pill>
        </div>
    </section>

    @foreach ($chains as $c)
        <section @if ($loop->first) id="crypto-chains" @endif class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" wire:key="chain-{{ $c->chain_id }}">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-[rgb(var(--nx-text))]">{{ $c->name }} <span class="text-xs font-normal text-[rgb(var(--nx-text-2))]">chain {{ $c->chain_id }}, {{ $c->finality_mode === 'finalized' ? 'waits for finality' : $c->confirmations.' confirmations' }}</span></h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-nx.pill :variant="$c->enabled ? 'rec' : null">{{ $c->enabled ? 'Watching' : 'Off' }}</x-nx.pill>
                    <x-nx.pill :variant="$c->credit_enabled ? 'rec' : null">{{ $c->credit_enabled ? 'Crediting' : 'Not crediting' }}</x-nx.pill>
                    <button type="button" wire:click="toggleChain({{ $c->chain_id }}, 'enabled')" class="ns-btn">{{ $c->enabled ? 'Stop watching' : 'Start watching' }}</button>
                    <button type="button" wire:click="toggleChain({{ $c->chain_id }}, 'credit_enabled')" class="ns-btn">{{ $c->credit_enabled ? 'Stop crediting' : 'Start crediting' }}</button>
                </div>
            </div>

            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Receiving addresses</p>
            <ul class="mb-3 divide-y divide-[rgb(var(--nx-line))] rounded-lg border border-[rgb(var(--nx-line))]">
                @forelse (($addresses[$c->chain_id] ?? collect()) as $a)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm" wire:key="addr-{{ $a->id }}">
                        <span class="min-w-0"><code class="break-all text-xs text-[rgb(var(--nx-text))]">{{ $a->address }}</code><small class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $a->label }}</small></span>
                        <span class="flex items-center gap-2">
                            <x-nx.pill :variant="$a->status === 'active' ? 'rec' : null">{{ ucfirst($a->status) }}</x-nx.pill>
                            @if ($a->status === 'active')
                                <button type="button" wire:click="retireAddress({{ $a->id }})" wire:confirm="Retire this address? It is still scanned for a grace period." class="text-xs font-semibold text-[rgb(var(--nx-teal-ink))] underline">Retire</button>
                            @endif
                        </span>
                    </li>
                @empty
                    <li class="px-3 py-3 text-sm text-[rgb(var(--nx-text-2))]">No address yet for this network.</li>
                @endforelse
            </ul>
            <div class="mb-5 grid gap-2 sm:grid-cols-[1fr_180px_auto]">
                <input type="text" wire:model="newAddress.{{ $c->chain_id }}" placeholder="0x… new receiving address" class="rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]" autocomplete="off" spellcheck="false">
                <input type="text" wire:model="newLabel.{{ $c->chain_id }}" placeholder="Label (optional)" class="rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                <button type="button" wire:click="addAddress({{ $c->chain_id }})" wire:loading.attr="disabled" wire:target="addAddress({{ $c->chain_id }})" class="ns-btn ns-btn--solid px-4">Add address</button>
            </div>

            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">RPC endpoints (stored encrypted, checked against the chain id)</p>
            <div class="mb-5 grid gap-3 sm:grid-cols-2">
                @foreach (['primary', 'secondary'] as $role)
                    @php($ep = ($endpoints[$c->chain_id] ?? collect())->firstWhere('role', $role))
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">{{ ucfirst($role) }} @if ($ep) <span class="text-[rgb(var(--nx-ok))]">saved: {{ $ep->maskedUrl() }}</span>@endif</label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="{{ $role }}Url.{{ $c->chain_id }}" placeholder="https://…" class="min-w-0 flex-1 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]" autocomplete="off" spellcheck="false">
                            <button type="button" wire:click="saveRpc({{ $c->chain_id }}, '{{ $role }}')" wire:loading.attr="disabled" wire:target="saveRpc" class="ns-btn">Test &amp; save</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Accepted tokens</p>
            <ul class="divide-y divide-[rgb(var(--nx-line))] rounded-lg border border-[rgb(var(--nx-line))]">
                @foreach (($tokens[$c->chain_id] ?? collect()) as $t)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm" wire:key="tok-{{ $t->id }}">
                        <span class="min-w-0"><b class="text-[rgb(var(--nx-text))]">{{ $t->symbol }}</b> <code class="break-all text-xs text-[rgb(var(--nx-text-2))]">{{ $t->contract }}</code>
                            <small class="block text-xs text-[rgb(var(--nx-text-2))]">min ${{ rtrim(rtrim(number_format((float) $t->min_usd, 2, '.', ''), '0'), '.') }} · max ${{ rtrim(rtrim(number_format((float) $t->max_single_usd, 2, '.', ''), '0'), '.') }} · review from ${{ rtrim(rtrim(number_format((float) $t->review_above_usd, 2, '.', ''), '0'), '.') }} · daily ${{ rtrim(rtrim(number_format((float) $t->user_daily_cap_usd, 2, '.', ''), '0'), '.') }}
                                <button type="button" wire:click="editLimits({{ $t->id }})" class="ml-1 font-semibold text-[rgb(var(--nx-teal-ink))] underline">Edit limits</button></small>
                            @if (isset($limits[$t->id]))
                                <span class="mt-2 grid max-w-md grid-cols-2 gap-2 sm:grid-cols-4">
                                    @foreach (['min' => 'Minimum $', 'max' => 'Max single $', 'review' => 'Review from $', 'daily' => 'Daily cap $'] as $f => $lab)
                                        <label class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $lab }}<input type="text" inputmode="decimal" wire:model="limits.{{ $t->id }}.{{ $f }}" class="mt-0.5 w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-2 py-1 text-sm text-[rgb(var(--nx-text))]"></label>
                                    @endforeach
                                    <button type="button" wire:click="saveLimits({{ $t->id }})" class="ns-btn ns-btn--solid col-span-2 sm:col-span-4">Save limits</button>
                                </span>
                            @endif
                            <small class="block text-xs text-[rgb(var(--nx-text-2))]">{{ $t->verified_source_note }}</small></span>
                        <span class="flex items-center gap-2">
                            <x-nx.pill :variant="$t->enabled ? 'rec' : null">{{ $t->enabled ? 'Accepted' : 'Disabled' }}</x-nx.pill>
                            <button type="button" wire:click="toggleToken({{ $t->id }})" class="text-xs font-semibold text-[rgb(var(--nx-teal-ink))] underline">{{ $t->enabled ? 'Disable' : 'Enable' }}</button>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <section id="crypto-deposits" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <div class="mb-3 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Latest deposits</p>
            @if ($held > 0)<x-nx.pill variant="gold">{{ $held }} waiting for review</x-nx.pill>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-[rgb(var(--nx-text-2))]"><tr><th class="py-2 pr-3">When</th><th class="pr-3">Chain</th><th class="pr-3">From</th><th class="pr-3">USD</th><th class="pr-3">Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-[rgb(var(--nx-line))] text-[rgb(var(--nx-text))]">
                    @forelse ($deposits as $d)
                        <tr wire:key="dep-{{ $d->id }}">
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $d->created_at?->diffForHumans() }}</td>
                            <td class="pr-3">{{ $d->chain_id }}</td>
                            <td class="pr-3"><code class="text-xs">{{ substr($d->from_address, 0, 8) }}…{{ substr($d->from_address, -4) }}</code></td>
                            <td class="pr-3">{{ number_format((float) $d->usd_amount, 2) }} {{ $d->token?->symbol }}</td>
                            <td class="pr-3"><x-nx.pill :variant="$d->status === 'credited' ? 'rec' : ($d->status === 'held_review' ? 'gold' : null)">{{ str_replace('_', ' ', $d->status) }}</x-nx.pill>@if ($d->hold_reason)<small class="block text-xs text-[rgb(var(--nx-text-2))]">{{ str_replace('_', ' ', $d->hold_reason) }}</small>@endif</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($d->status === 'held_review')
                                    <button type="button" wire:click="approve({{ $d->id }})" wire:confirm="Credit this deposit to the member's wallet?" class="text-xs font-semibold text-[rgb(var(--nx-teal-ink))] underline">Approve</button>
                                    <button type="button" wire:click="reject({{ $d->id }})" wire:confirm="Reject this deposit? Nothing is credited." class="ml-2 text-xs font-semibold text-[rgb(var(--nx-bad))] underline">Reject</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-[rgb(var(--nx-text-2))]">No deposits yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="text-xs text-[rgb(var(--nx-text-2))]">Tron and Solana: the official TRC20 and Solana addresses are on the licence payment page, but automatic crediting is EVM only for now, so those are taken as manual payments.</p>
</div>
