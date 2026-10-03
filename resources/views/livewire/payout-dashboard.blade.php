{{-- Shared payout dashboard (NAARA-BUILD-22 §4) — one component for partner /
     merchant / referral earners. Status-focused: payouts run automatically, so
     this shows the balance, the free-payout/KYC state, and the ledger history.
     Cost is never shown; only the earner's own payable amounts. --}}
@php
    $label = ['partner' => 'Partner', 'merchant' => 'Merchant', 'referral' => 'Referral'][$earnerType] ?? 'Earnings';
@endphp
<div>
{{-- Embedded in earner pages (partner / merchant / referral / wallet): `bare` adds no second skin canvas. --}}
<x-nx.page :bare="true">
    {{-- Balance + auto-payout status --}}
    <div class="ns-pg__card ns-ring" style="margin-top:0">
        <div class="ns-pg__head" style="align-items:flex-start">
            <div>
                <span class="ns-pg__lbl" style="text-transform:uppercase;letter-spacing:.06em">{{ $label }} earnings balance</span>
                <p class="ns-pg__bignum">${{ number_format($balance, 2) }}</p>
                @if (($debt ?? 0) > 0)
                    <p class="ns-pg__callout ns-pg__callout--warn" style="margin-top:10px;padding:10px 12px;font-size:13.5px;color:rgb(var(--nx-text))" role="status"><x-nx.icon name="info" style="font-size:18px" /><span>An adjustment of ${{ number_format($debt, 2) }} (a refunded or disputed sale) is being repaid from your future earnings. Withdrawals resume once it is cleared.</span></p>
                @endif
                @if ($enabled)
                    <p class="ns-pg__hint" style="display:flex;align-items:center;gap:6px"><x-nx.icon name="refresh" style="font-size:15px" />Payouts run automatically to your verified account.</p>
                @else
                    <p class="ns-pg__note">Payouts are currently paused by the admin.</p>
                @endif
            </div>

            @if (! $hasVerifiedAccount)
                <a href="{{ route('rewards.withdraw') }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><x-nx.icon name="wallet" /> Add a payout account</a>
            @endif
        </div>

        {{-- Free-payout / KYC threshold state (§3), positive-framed. --}}
        <div class="ns-pg__sep" style="display:block">
            @if ($exempt)
                <p class="ns-pg__hint" style="margin:0;display:flex;align-items:center;gap:6px"><x-nx.icon name="check" style="font-size:15px;color:rgb(var(--nx-ok))" />Staff earnings have no payout limits.</p>
            @elseif ($requiresKyc && ! $canWithdraw)
                <div class="ns-pg__callout ns-pg__callout--warn">
                    <x-nx.icon name="shield" />
                    <div>
                        <b>You've used your {{ $freeCount }} free payouts</b>
                        <p>Verify your identity to keep earning and withdrawing. It only takes a minute.</p>
                        <a href="{{ route('account.verify') }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--sm ns-cta--gold" style="margin-top:10px;display:inline-flex">Verify identity</a>
                    </div>
                </div>
            @elseif ($requiresKyc && $canWithdraw)
                <p class="ns-pg__hint" style="margin:0;display:flex;align-items:center;gap:6px;color:color-mix(in srgb, rgb(var(--nx-ok)) 72%, rgb(var(--nx-text)))"><x-nx.icon name="check" style="font-size:15px" />Identity verified. No payout limits.</p>
            @else
                <p class="ns-pg__hint" style="margin:0;display:flex;align-items:center;gap:6px"><x-nx.icon name="check" style="font-size:15px;color:rgb(var(--nx-ok))" />{{ $remainingFree }} of {{ $freeCount }} free payouts left before identity verification is needed.</p>
            @endif
        </div>
    </div>

    {{-- Earnings history --}}
    <div class="ns-pg__card ns-ring">
        <h3 class="ns-pg__h2" style="margin-bottom:8px">Earnings history</h3>
        <div class="ns-pg__ledger" style="background:transparent;margin-top:0">
            @forelse ($history as $row)
                <div class="ns-pg__lrow" style="padding-inline:0">
                    <div style="min-width:0">
                        <b>{{ match ($row->type) { 'clawback' => 'Adjustment', 'transfer_out' => 'Sent to a member', 'transfer_in' => 'Received from a member', default => ucfirst($row->type) } }}</b>
                        <small>{{ $row->description ?? '' }}</small>
                    </div>
                    <div style="text-align:end;flex:none">
                        <span class="{{ (float) $row->amount >= 0 ? 'is-pos' : 'is-neg' }}">{{ (float) $row->amount >= 0 ? '+' : '−' }}${{ number_format(abs((float) $row->amount), 2) }}</span>
                        <small>{{ $row->created_at->format('M j, Y') }}</small>
                    </div>
                </div>
            @empty
                <p class="ns-sub" style="margin:0">No earnings yet. As you earn, entries appear here and are paid out automatically.</p>
            @endforelse
        </div>
    </div>

    {{-- Recent payouts --}}
    @if ($payouts->isNotEmpty())
        <div class="ns-pg__card ns-ring">
            <h3 class="ns-pg__h2" style="margin-bottom:8px">Recent payouts</h3>
            <div class="ns-pg__ledger" style="background:transparent;margin-top:0">
                @foreach ($payouts as $p)
                    @php $tone = match(\App\Support\PayoutStatusText::tone($p)){ 'success' => 'ns-st--ok', 'danger' => 'ns-st--bad', 'warn' => 'ns-st--warn', default => '' }; @endphp
                    <div class="ns-pg__lrow" style="padding-inline:0;flex-wrap:wrap">
                        <b style="font-variant-numeric:tabular-nums">${{ number_format((float) $p->amount, 2) }} {{ $p->currency }}</b>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                            <small>{{ $p->created_at->format('M j, Y') }}</small>
                            <span class="ns-st {{ $tone }}">{{ \App\Support\PayoutStatusText::text($p) }}</span>
                            @if (in_array($p->status, [\App\Models\PayoutRequest::PENDING, \App\Models\PayoutRequest::AWAITING_FUNDS, \App\Models\PayoutRequest::APPROVED], true))
                                <button type="button" wire:click="cancelPayout({{ $p->id }})" wire:confirm="{{ __('payouts.cancel.confirm') }}" wire:loading.attr="disabled" wire:target="cancelPayout({{ $p->id }})" class="ns-pg__act ns-pg__act--bad">{{ __('payouts.cancel.button') }}</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-nx.page>
</div>
