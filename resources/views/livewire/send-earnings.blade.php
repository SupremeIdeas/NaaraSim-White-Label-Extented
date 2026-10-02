@php
    $card = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60';
    $inp = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/15 dark:bg-slate-800 dark:text-slate-100';
    $badge = fn ($s) => match ($s) {
        'accepted' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
        'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
        default => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
    };
    $mask = fn ($u) => \App\Services\Payouts\Peer\EarningsTransferService::maskedName($u);
@endphp
<div class="mx-auto max-w-2xl space-y-5 px-4 py-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">{{ __('payouts.peer.title') }}</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('payouts.peer.intro') }}</p>
    </div>

    @if ($notice)<div class="flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $notice }}</div>@endif
    @if ($error)<div class="rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300" role="alert">{{ $error }}</div>@endif

    {{-- Incoming: needs the member's answer --}}
    @php $open = $incoming->where('status', 'pending'); @endphp
    @if ($open->isNotEmpty())
        <section class="{{ $card }} border-amber-300 dark:border-amber-500/40" aria-labelledby="in-title">
            <h2 id="in-title" class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('payouts.peer.incoming') }}</h2>
            <ul class="mt-3 space-y-3">
                @foreach ($open as $t)
                    <li class="rounded-xl border border-slate-200 p-3 dark:border-white/10" wire:key="in-{{ $t->id }}">
                        <p class="text-sm text-slate-800 dark:text-slate-200"><strong>${{ number_format((float) $t->amount_usd, 2) }}</strong> {{ __('payouts.peer.from') }} {{ $mask($t->sender) }}</p>
                        @if ($t->note)<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">&ldquo;{{ $t->note }}&rdquo;</p>@endif
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('payouts.peer.accept_by', ['when' => $t->expires_at->diffForHumans()]) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('payouts.peer.accept_warning') }}</p>
                        <div class="mt-2 flex gap-2">
                            <button type="button" wire:click="accept({{ $t->id }})" wire:loading.attr="disabled" wire:target="accept({{ $t->id }})" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">{{ __('payouts.peer.accept') }}</button>
                            <button type="button" wire:click="decline({{ $t->id }})" wire:loading.attr="disabled" wire:target="decline({{ $t->id }})" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50 dark:border-white/15 dark:text-slate-200">{{ __('payouts.peer.decline') }}</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Send --}}
    <section class="{{ $card }}" aria-labelledby="send-title">
        <h2 id="send-title" class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('payouts.peer.send_title') }}</h2>
        @if (! $enabled)
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('payouts.peer.block.unavailable') }}</p>
        @elseif ($block)
            <p class="mt-2 flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300"><x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0" /> {{ __('payouts.peer.block.'.$block) }}</p>
        @else
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('payouts.peer.how', ['hours' => $expiryHours]) }}</p>
            <form wire:submit="send" class="mt-4 space-y-3">
                <div>
                    <label for="pe-email" class="block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('payouts.peer.email') }}</label>
                    <input id="pe-email" type="email" wire:model="email" autocomplete="off" class="{{ $inp }} mt-1" placeholder="name@example.com">
                    @error('email')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <p class="mt-1 text-[11px] text-slate-400">{{ __('payouts.peer.email_hint') }}</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="pe-bucket" class="block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('payouts.peer.from_balance') }}</label>
                        <select id="pe-bucket" wire:model.live="bucket" class="{{ $inp }} mt-1">
                            <option value="referral">{{ __('payouts.peer.bucket_referral') }} — ${{ number_format($balances['referral'], 2) }}</option>
                            @if ($balances['merchant'] > 0)<option value="merchant">{{ __('payouts.peer.bucket_merchant') }} — ${{ number_format($balances['merchant'], 2) }}</option>@endif
                        </select>
                    </div>
                    <div>
                        <label for="pe-amount" class="block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('payouts.peer.amount') }} (USD)</label>
                        <input id="pe-amount" type="number" step="0.01" min="{{ $min }}" max="{{ $max }}" wire:model="amount" class="{{ $inp }} mt-1">
                        @error('amount')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-slate-400">{{ __('payouts.peer.limits', ['min' => number_format($min, 2), 'max' => number_format($max, 2)]) }}</p>
                    </div>
                </div>
                <div>
                    <label for="pe-note" class="block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('payouts.peer.note') }}</label>
                    <input id="pe-note" type="text" maxlength="200" wire:model="note" class="{{ $inp }} mt-1">
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="send" class="nx-btn w-full !py-2.5 text-sm disabled:opacity-50">
                    <span wire:loading.remove wire:target="send">{{ __('payouts.peer.send') }}</span>
                    <span wire:loading wire:target="send">{{ __('payouts.peer.sending') }}</span>
                </button>
            </form>
        @endif
    </section>

    {{-- Receiving: how to be a safe receiver --}}
    <section class="{{ $card }}" aria-labelledby="recv-title">
        <h2 id="recv-title" class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('payouts.peer.recv_title') }}</h2>
        <p class="mt-1 text-sm {{ $canReceive ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-400' }}">
            {{ $canReceive ? __('payouts.peer.recv_yes') : __('payouts.peer.recv_no') }}
        </p>
    </section>

    {{-- History --}}
    @if ($outgoing->isNotEmpty() || $incoming->isNotEmpty())
        <section class="{{ $card }}" aria-labelledby="hist-title">
            <h2 id="hist-title" class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('payouts.peer.history') }}</h2>
            <ul class="mt-3 divide-y divide-slate-100 text-sm dark:divide-white/10">
                @foreach ($outgoing as $t)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="out-{{ $t->id }}">
                        <span class="text-slate-800 dark:text-slate-200">{{ __('payouts.peer.sent_to', ['amount' => '$'.number_format((float) $t->amount_usd, 2), 'name' => $mask($t->recipient)]) }}</span>
                        <span class="flex items-center gap-2">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge($t->status) }}">{{ __('payouts.peer.status_'.$t->status) }}</span>
                            @if ($t->status === 'pending')
                                <button type="button" wire:click="cancel({{ $t->id }})" wire:loading.attr="disabled" wire:target="cancel({{ $t->id }})" class="text-xs font-semibold text-primary hover:underline disabled:opacity-50">{{ __('payouts.peer.cancel') }}</button>
                            @endif
                        </span>
                    </li>
                @endforeach
                @foreach ($incoming->where('status', '!=', 'pending') as $t)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="inh-{{ $t->id }}">
                        <span class="text-slate-800 dark:text-slate-200">{{ __('payouts.peer.received_from', ['amount' => '$'.number_format((float) $t->amount_usd, 2), 'name' => $mask($t->sender)]) }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge($t->status) }}">{{ __('payouts.peer.status_'.$t->status) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
