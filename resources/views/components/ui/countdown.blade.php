@props([
    'until' => null,        // ISO-8601 target time (server-authoritative)
    'expireEvent' => null,  // optional Livewire/browser event dispatched on expiry
    'labels' => true,       // show H/M/S labels
])

@php
    // Anchor to SERVER time: we send both the target and the server's "now", so
    // the client can correct for a wrong device clock and never be gamed by it
    // (blueprint Section 31).
    $serverNow = now()->toIso8601String();
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 font-mono tabular-nums']) }}
     x-data="{
        target: new Date('{{ $until }}').getTime(),
        skew: Date.now() - new Date('{{ $serverNow }}').getTime(),
        remaining: 0,
        expired: false,
        _timer: null,
        tick() {
            const trueNow = Date.now() - this.skew;   // corrected to server time
            this.remaining = Math.max(0, this.target - trueNow);
            if (this.remaining === 0 && ! this.expired) {
                this.expired = true;
                clearInterval(this._timer);
                @if ($expireEvent) $dispatch('{{ $expireEvent }}'); @endif
            }
        },
        part(ms, div, mod) { return String(Math.floor(ms / div) % mod).padStart(2, '0'); },
        init() { this.tick(); this._timer = setInterval(() => this.tick(), 1000); },
     }"
     role="timer" aria-live="polite"
     x-effect="$el.setAttribute('aria-label', expired ? 'Expired' : 'Time remaining')">
    <template x-if="expired">
        <span class="font-semibold text-red-600 dark:text-red-400">Expired</span>
    </template>
    <template x-if="! expired">
        <span class="inline-flex items-center gap-1">
            <template x-if="remaining >= 3600000">
                <span><span x-text="part(remaining, 3600000, 24)"></span>@if ($labels)<span class="text-xs text-slate-400">h</span>@else:@endif</span>
            </template>
            <span><span x-text="part(remaining, 60000, 60)"></span>@if ($labels)<span class="text-xs text-slate-400">m</span>@else:@endif</span>
            <span><span x-text="part(remaining, 1000, 60)"></span>@if ($labels)<span class="text-xs text-slate-400">s</span>@endif</span>
        </span>
    </template>
</div>
