{{-- nx:converted (skin tokens only) --}}
<div class="mx-auto max-w-3xl" data-server-ip @if ($until) wire:poll.5s="tick" @endif>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Server IP address</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">The address your suppliers must have on their whitelist. Copy it into the supplier dashboard, so airtime, data, bills and payouts are not refused.</p>
    </div>

    @php
        $btn = 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold';
        $primary = $btn.' bg-[rgb(var(--nx-cta-b))] text-white disabled:opacity-60';
        $ghost = $btn.' border border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]';
    @endphp

    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5" aria-labelledby="sip-h">
        <h2 id="sip-h" class="text-sm font-bold text-[rgb(var(--nx-text))]">Outbound IP of this server</h2>

        @if ($error)<p class="mt-3 rounded-xl bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]" role="alert" data-ip-error>{{ $error }}</p>@endif

        @if ($until)
            @if ($ip)
                <div class="mt-3 flex flex-wrap items-center gap-2" x-data="{ done: false, copy() { const t = $refs.v.textContent.trim(); const ok = () => { this.done = true; setTimeout(() => this.done = false, 1600); }; if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(ok); } else { const a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); ok(); } catch (e) {} document.body.removeChild(a); } } }">
                    <code x-ref="v" class="min-w-0 flex-1 break-all rounded-lg bg-[rgb(var(--nx-surface-2))] px-3 py-3 font-mono text-lg font-bold text-[rgb(var(--nx-text))]" data-ip-value>{{ $ip }}</code>
                    <button type="button" x-on:click="copy()" class="{{ $primary }}" data-ip-copy><x-icon name="copy" class="h-4 w-4" /><span x-text="done ? 'Copied' : 'Copy'">Copy</span></button>
                </div>
                <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">Showing for two minutes, then it hides itself again.</p>
            @else
                <p class="mt-3 rounded-xl bg-[rgb(var(--nx-warn)/0.12)] p-3 text-sm text-[rgb(var(--nx-text))]" data-ip-missing>We could not detect it from here (the servers that report it did not answer). On the server, run <code class="font-mono">php artisan topup:run ip</code> or <code class="font-mono">curl https://ifconfig.me</code>.</p>
            @endif
            @if ($site && $site !== $ip)
                <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]" data-ip-site>This website answers on <code class="font-mono">{{ $site }}</code>, which is a different address. Suppliers see the outbound one above, not this one.</p>
            @endif
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="recheck" wire:loading.attr="disabled" wire:target="recheck" class="{{ $ghost }}">Check again</button>
                <button type="button" wire:click="hide" class="{{ $ghost }}">Hide now</button>
            </div>
        @else
            <p class="mt-3 flex items-center gap-2 font-mono text-lg tracking-widest text-[rgb(var(--nx-text-2))]" aria-label="Hidden" data-ip-masked>&bull;&bull;&bull;.&bull;&bull;&bull;.&bull;&bull;&bull;.&bull;&bull;&bull;</p>
            @if ($asking)
                <form wire:submit="reveal" class="mt-4 max-w-sm space-y-3">
                    <label class="block text-xs font-semibold text-[rgb(var(--nx-text-2))]" for="sip-pw">Enter your account password to show it</label>
                    <input id="sip-pw" type="password" wire:model="password" autocomplete="current-password" required autofocus class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <div class="flex gap-2">
                        <button type="submit" wire:loading.attr="disabled" wire:target="reveal" class="{{ $primary }}"><span wire:loading.remove wire:target="reveal">Show the address</span><span wire:loading wire:target="reveal">Checking...</span></button>
                        <button type="button" wire:click="cancel" class="{{ $ghost }}">Cancel</button>
                    </div>
                </form>
            @else
                <button type="button" wire:click="ask" class="{{ $primary }} mt-4" data-ip-reveal><x-icon name="eye" class="h-4 w-4" />Reveal with my password</button>
            @endif
        @endif
    </section>

    <section class="mt-5 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="text-sm font-bold text-[rgb(var(--nx-text))]">Where to paste it</h2>
        <ul class="mt-2 space-y-1.5 text-sm text-[rgb(var(--nx-text-2))]">
            <li><b class="text-[rgb(var(--nx-text))]">Paga</b> (airtime, data, bills, wallet funding): Developer Tools, IP Whitelist, Live environment.</li>
            <li><b class="text-[rgb(var(--nx-text))]">NOWPayments payouts:</b> the IP whitelist in its dashboard, plus each wallet address.</li>
            <li><b class="text-[rgb(var(--nx-text))]">VTpass, Grey and any other supplier:</b> look for a field called IP whitelist or allowed IPs. If there is none, it does not need it.</li>
        </ul>
        <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Shared hosting: ask your host for a dedicated IP, because a shared one can change. If the queue worker runs on another machine, whitelist that machine's address too. If the address ever changes, come back here and update every supplier the same day.</p>
    </section>
</div>
