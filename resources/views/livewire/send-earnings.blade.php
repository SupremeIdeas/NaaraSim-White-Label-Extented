@php
    $badge = fn ($s) => match ($s) {
        'accepted' => 'ns-st--ok',
        'pending' => 'ns-st--warn',
        default => '',
    };
    $mask = fn ($u) => \App\Services\Payouts\Peer\EarningsTransferService::maskedName($u);
@endphp
{{-- Send earnings (peer transfer) on the skin system (S3 Batch 6). Copy and behaviour unchanged. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <h1 class="ns-h1" style="margin-top:6px">{{ __('payouts.peer.title') }}</h1>
    <p class="ns-sub">{{ __('payouts.peer.intro') }}</p>

    @if ($notice)<div class="ns-pg__callout ns-pg__callout--ok" style="margin-top:16px" role="status"><x-nx.icon name="check" /><div><p style="margin:0;color:rgb(var(--nx-text))">{{ $notice }}</p></div></div>@endif
    @if ($error)<div class="ns-pg__err" role="alert">{{ $error }}</div>@endif

    {{-- Incoming: needs the member's answer --}}
    @php $open = $incoming->where('status', 'pending'); @endphp
    @if ($open->isNotEmpty())
        <section class="ns-pg__card ns-pg__card--warn" aria-labelledby="in-title">
            <h2 id="in-title" class="ns-pg__h2">{{ __('payouts.peer.incoming') }}</h2>
            <ul class="ns-pg__stack" style="list-style:none;margin:12px 0 0;padding:0">
                @foreach ($open as $t)
                    <li class="ns-pg__kv" style="display:block" wire:key="in-{{ $t->id }}">
                        <p style="margin:0;color:rgb(var(--nx-text))"><strong>${{ number_format((float) $t->amount_usd, 2) }}</strong> {{ __('payouts.peer.from') }} {{ $mask($t->sender) }}</p>
                        @if ($t->note)<p class="ns-pg__hint">&ldquo;{{ $t->note }}&rdquo;</p>@endif
                        <p class="ns-pg__hint">{{ __('payouts.peer.accept_by', ['when' => $t->expires_at->diffForHumans()]) }}</p>
                        <p class="ns-pg__hint">{{ __('payouts.peer.accept_warning') }}</p>
                        <div class="ns-pg__btns" style="margin-top:12px">
                            <button type="button" wire:click="accept({{ $t->id }})" wire:loading.attr="disabled" wire:target="accept({{ $t->id }})" class="ns-cta ns-cta--pill ns-cta--sm">{{ __('payouts.peer.accept') }}</button>
                            <button type="button" wire:click="decline({{ $t->id }})" wire:loading.attr="disabled" wire:target="decline({{ $t->id }})" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">{{ __('payouts.peer.decline') }}</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Send --}}
    <section class="ns-pg__card ns-ring" aria-labelledby="send-title">
        <h2 id="send-title" class="ns-pg__h2">{{ __('payouts.peer.send_title') }}</h2>
        @if (! $enabled)
            <p class="ns-pg__hint" style="margin-top:8px">{{ __('payouts.peer.block.unavailable') }}</p>
        @elseif ($block)
            <p class="ns-pg__hint" style="margin-top:8px;display:flex;align-items:flex-start;gap:8px"><x-nx.icon name="shield" /> {{ __('payouts.peer.block.'.$block) }}</p>
        @else
            <p class="ns-pg__hint">{{ __('payouts.peer.how', ['hours' => $expiryHours]) }}</p>
            <form wire:submit="send" class="ns-pg__form" style="margin-top:12px">
                <label for="pe-email" class="ns-pg__lbl">{{ __('payouts.peer.email') }}</label>
                <input id="pe-email" type="email" wire:model="email" autocomplete="off" class="ns-input" placeholder="name@example.com">
                @error('email')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                <p class="ns-pg__hint">{{ __('payouts.peer.email_hint') }}</p>

                <div class="ns-pg__two">
                    <div>
                        <label for="pe-bucket" class="ns-pg__lbl">{{ __('payouts.peer.from_balance') }}</label>
                        <select id="pe-bucket" wire:model.live="bucket" class="ns-input">
                            <option value="referral">{{ __('payouts.peer.bucket_referral') }} — ${{ number_format($balances['referral'], 2) }}</option>
                            @if ($balances['merchant'] > 0)<option value="merchant">{{ __('payouts.peer.bucket_merchant') }} — ${{ number_format($balances['merchant'], 2) }}</option>@endif
                        </select>
                    </div>
                    <div>
                        <label for="pe-amount" class="ns-pg__lbl">{{ __('payouts.peer.amount') }} (USD)</label>
                        <input id="pe-amount" type="number" step="0.01" min="{{ $min }}" max="{{ $max }}" wire:model="amount" class="ns-input">
                        @error('amount')<span class="ns-pg__fielderr">{{ $message }}</span>@enderror
                        <p class="ns-pg__hint">{{ __('payouts.peer.limits', ['min' => number_format($min, 2), 'max' => number_format($max, 2)]) }}</p>
                    </div>
                </div>

                <label for="pe-note" class="ns-pg__lbl">{{ __('payouts.peer.note') }}</label>
                <input id="pe-note" type="text" maxlength="200" wire:model="note" class="ns-input">

                <button type="submit" wire:loading.attr="disabled" wire:target="send" class="ns-cta" style="margin-top:14px">
                    <span wire:loading.remove wire:target="send" class="ns-cta__label">{{ __('payouts.peer.send') }}</span>
                    <span wire:loading wire:target="send" class="ns-cta__label">{{ __('payouts.peer.sending') }}</span>
                </button>
            </form>
        @endif
    </section>

    {{-- Receiving: how to be a safe receiver --}}
    <section class="ns-pg__card ns-ring" aria-labelledby="recv-title">
        <h2 id="recv-title" class="ns-pg__h2">{{ __('payouts.peer.recv_title') }}</h2>
        <p class="ns-pg__hint" style="margin-top:6px;font-size:14.5px;{{ $canReceive ? 'color:color-mix(in srgb, rgb(var(--nx-ok)) 72%, rgb(var(--nx-text)))' : '' }}">{{ $canReceive ? __('payouts.peer.recv_yes') : __('payouts.peer.recv_no') }}</p>
    </section>

    {{-- History --}}
    @if ($outgoing->isNotEmpty() || $incoming->isNotEmpty())
        <section class="ns-pg__card ns-ring" aria-labelledby="hist-title">
            <h2 id="hist-title" class="ns-pg__h2">{{ __('payouts.peer.history') }}</h2>
            <ul class="ns-pg__ledger" style="list-style:none;margin:12px 0 0;padding:0;background:transparent">
                @foreach ($outgoing as $t)
                    <li class="ns-pg__lrow" style="padding-inline:0;flex-wrap:wrap" wire:key="out-{{ $t->id }}">
                        <span style="color:rgb(var(--nx-text))">{{ __('payouts.peer.sent_to', ['amount' => '$'.number_format((float) $t->amount_usd, 2), 'name' => $mask($t->recipient)]) }}</span>
                        <span style="display:flex;align-items:center;gap:10px">
                            <span class="ns-st {{ $badge($t->status) }}">{{ __('payouts.peer.status_'.$t->status) }}</span>
                            @if ($t->status === 'pending')
                                <button type="button" wire:click="cancel({{ $t->id }})" wire:loading.attr="disabled" wire:target="cancel({{ $t->id }})" class="ns-pg__act ns-pg__act--link">{{ __('payouts.peer.cancel') }}</button>
                            @endif
                        </span>
                    </li>
                @endforeach
                @foreach ($incoming->where('status', '!=', 'pending') as $t)
                    <li class="ns-pg__lrow" style="padding-inline:0;flex-wrap:wrap" wire:key="inh-{{ $t->id }}">
                        <span style="color:rgb(var(--nx-text))">{{ __('payouts.peer.received_from', ['amount' => '$'.number_format((float) $t->amount_usd, 2), 'name' => $mask($t->sender)]) }}</span>
                        <span class="ns-st {{ $badge($t->status) }}">{{ __('payouts.peer.status_'.$t->status) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-nx.page>
</div>
