@props(['level' => 2, 'action' => 'add a payout account'])
{{-- BUILD-4 §5.1 — a clear, inline explanation shown at a withdraw / add-bank-
     account entry point when the user hasn't reached the required KYC level, with
     a direct link into verification. Renders nothing once they're verified, so it
     can sit safely above any payout control. --}}
@php($__verified = auth()->check() && app(\App\Services\Kyc\KycService::class)->hasLevel(auth()->user(), (int) $level))
@unless ($__verified)
    <div {{ $attributes->merge(['class' => 'ns-pg__callout ns-pg__callout--warn']) }}>
        <x-nx.icon name="shield" />
        <div class="min-w-0">
            <b style="font-size:15px">Verify your identity to {{ $action }}</b>
            <p>A quick identity check keeps payouts safe and going to the right person. It only takes a moment.</p>
            <a href="{{ route('account.verify') }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--sm" style="margin-top:10px;display:inline-flex"><x-nx.icon name="check" /> Verify identity</a>
        </div>
    </div>
@endunless
