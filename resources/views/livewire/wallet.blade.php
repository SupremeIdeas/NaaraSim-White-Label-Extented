<div class="mx-auto max-w-2xl" x-data="{ tab: 'topup' }">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Wallet</h1>
        {{-- Display-currency switcher (owner request). USD stays the
             settlement currency; this only changes what prices are SHOWN in. --}}
        <div class="relative" x-data="{ open: false }">
            <button type="button" x-on:click="open = !open" x-on:click.outside="open = false"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-200">
                <x-icon name="globe" class="h-3.5 w-3.5" /> {{ $displayCurrency }}
                <x-icon name="chevron-right" class="h-3 w-3 rotate-90" />
            </button>
            <div x-show="open" x-cloak x-transition
                 class="absolute right-0 z-20 mt-1 max-h-64 w-48 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                @foreach ($currencyOptions as $code => $meta)
                    <button type="button" wire:key="cur-{{ $code }}" x-on:click="open = false" wire:click="setCurrency('{{ $code }}')"
                            @class([
                                'flex w-full items-center justify-between px-3 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-[#243352]',
                                'font-bold text-primary dark:text-teal-300' => $displayCurrency === $code,
                                'text-slate-600 dark:text-slate-300' => $displayCurrency !== $code,
                            ])>
                        <span>{{ $meta[1] }}</span>
                        <span class="text-slate-400">{{ $meta[0] }} {{ $code }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Top-up "processing" banner (owner request): the actual credit lands
         via a queued webhook job that can take up to ~2 minutes on shared
         hosting's cron-drain cadence — this replaces the old "redirect back
         to a stale balance, refresh and hope" experience. wire:poll only
         while $pendingTopUp is set; the moment the credit lands the
         controller clears it and the banner disappears on its own next
         render, right as the success hero toast fires (same pattern
         get-number.blade.php uses for an OTP "waiting" state). --}}
    @if ($pendingTopUp)
        <div wire:poll.5s="checkPendingTopUp"
             class="mb-4 flex items-center gap-3 rounded-2xl border border-primary/20 bg-primary/5 p-4 dark:border-teal-500/20 dark:bg-teal-500/10">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary dark:bg-teal-500/20 dark:text-teal-300">
                <svg class="h-4.5 w-4.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Processing your top-up…</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ ucfirst($pendingTopUp['gateway']) }} confirmed your payment — crediting {{ $pendingTopUp['currency'] }} {{ number_format((float) $pendingTopUp['amount'], 2) }} to your wallet now. This usually takes under a minute.
                </p>
            </div>
        </div>
    @endif

    {{-- Balance hero: the settlement (USD) balance leads, NGN shown as a
         secondary card — no invented "total" across two real currencies.
         Quick actions switch tabs below (no fake buttons). --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-navy via-primary-dark to-primary p-6 shadow-lg shadow-primary/20">
        <div class="pointer-events-none absolute -right-10 -top-16 h-48 w-48 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-14 -left-10 h-40 w-40 rounded-full bg-accent/20 blur-2xl"></div>
        <div class="relative">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-teal-100/80">
                <x-icon name="credit-card" class="h-4 w-4" /> USD balance
            </p>
            <p class="mt-2 font-display text-4xl font-bold tracking-tight text-white">${{ number_format((float) $wallet->usd_balance, 2) }}</p>
            @if ($usdLocal)
                <p class="mt-1 text-xs text-teal-100/70">≈ {{ $usdLocal }} · live rate</p>
            @endif

            {{-- Unified USD Wallet (Part B): usd_balance is the ONE spendable
                 balance. This NGN row is the LIVE-rate equivalent for
                 convenience — never a separate, independently-growing balance. --}}
            <div class="mt-5 flex items-center justify-between rounded-2xl bg-white/10 px-4 py-3 backdrop-blur">
                <span class="flex items-center gap-2 text-sm text-teal-100/90"><x-icon name="wallet" class="h-4 w-4" /> ≈ NGN</span>
                <span class="text-sm font-bold text-white">{{ $ngnLive }}</span>
            </div>
            {{-- Legacy NGN, only if there's actually one to show (pre-Part-B
                 top-ups). Historical only — never spendable, never grows again. --}}
            @if ((float) $wallet->ngn_balance > 0)
                <div class="mt-2 flex items-center justify-between rounded-2xl bg-white/5 px-4 py-2.5 text-xs text-teal-100/70">
                    <span>Legacy NGN balance (historical, from before top-ups settled in USD)</span>
                    <span class="font-semibold text-white">NGN {{ number_format((float) $wallet->ngn_balance, 2) }}</span>
                </div>
            @endif

            {{-- Icons here are white, not the theme's accent colour: they sit
                 directly on the brand gradient (via a near-transparent
                 bg-white/10 pill), and an accent-on-primary combination isn't
                 guaranteed to contrast the same way in every theme. White
                 always reads against this gradient — the balance figure and
                 labels right above already rely on the same assumption. --}}
            <div class="mt-5 grid grid-cols-3 gap-2">
                <button type="button" @click="tab = 'topup'" class="flex flex-col items-center gap-1.5 rounded-2xl bg-white/10 py-3 text-xs font-semibold text-white transition hover:bg-white/20">
                    <x-icon name="zap" class="h-5 w-5 text-white" /> Top up
                </button>
                <button type="button" @click="tab = 'payout'" class="flex flex-col items-center gap-1.5 rounded-2xl bg-white/10 py-3 text-xs font-semibold text-white transition hover:bg-white/20">
                    <x-icon name="credit-card" class="h-5 w-5 text-white" /> Withdraw
                </button>
                <button type="button" @click="tab = 'spending'" class="flex flex-col items-center gap-1.5 rounded-2xl bg-white/10 py-3 text-xs font-semibold text-white transition hover:bg-white/20">
                    <x-icon name="signal" class="h-5 w-5 text-white" /> Spending
                </button>
            </div>
        </div>
    </div>

    {{-- Tabbed navigation (owner request): the page was too long as one long
         scroll — Top Up / Payout / Spending each get their own tab instead. --}}
    <div class="mt-6 inline-flex w-full rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
        <button type="button" @click="tab = 'topup'"
                :class="tab === 'topup' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400'"
                class="flex-1 rounded-full px-3 py-2 text-sm font-semibold transition">Top Up</button>
        <button type="button" @click="tab = 'payout'"
                :class="tab === 'payout' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400'"
                class="flex-1 rounded-full px-3 py-2 text-sm font-semibold transition">Payout</button>
        <button type="button" @click="tab = 'spending'"
                :class="tab === 'spending' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400'"
                class="flex-1 rounded-full px-3 py-2 text-sm font-semibold transition">Spending</button>
    </div>

    {{-- ============================== TOP UP ============================== --}}
    <div x-show="tab === 'topup'" class="mt-5">
        {{-- Top up (Module 32 pick — Na3ar-17 payment card, made functional):
             payment-method radios, quick-cash blocks — all wired to the real
             gateway initialisation. --}}
        <div class="rounded-2xl border border-slate-200 nx-glass-tile p-5 dark:border-[var(--brand-card-border-dark)]">
            @if ($error)
                <div class="mb-3 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                    <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
                </div>
            @endif

            <form wire:submit="topUp" class="space-y-4">
                {{-- Pay-in currency (owner request: a real dropdown, not a
                     hardcoded NGN/USD pair) — every currency any configured
                     gateway accepts. USD credits the wallet directly;
                     everything else is converted to a USD credit locked at
                     the live rate. Changing this filters "Pay with" below to
                     only the gateways that actually accept it
                     (updatedCurrency()). --}}
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Top up in</label>
                    <select wire:model.live="currency"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        @foreach ($payCurrencyOptions as $cur => $curLabel)
                            <option value="{{ $cur }}" @selected($currency === $cur)>{{ $curLabel }} ({{ $cur }})</option>
                        @endforeach
                    </select>
                    @error('currency') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                @if ($currency !== 'USD' && is_numeric($amount) && $amount > 0)
                    <p class="-mt-2 text-xs text-slate-400 dark:text-slate-500">
                        ≈ ${{ number_format(app(\App\Services\Pricing\CurrencyService::class)->toUsd((float) $amount, $currency), 2) }} credited to your wallet (live rate, locked at checkout).
                    </p>
                @endif

                {{-- Quick cash blocks --}}
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Quick amounts</label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ($currency === 'NGN' ? [1000, 2000, 5000, 10000] : [5, 10, 20, 50] as $quick)
                            <button type="button" wire:key="quick-{{ $currency }}-{{ $quick }}" wire:click="$set('amount', {{ $quick }})"
                                    @class([
                                        'rounded-xl border px-1 py-2 text-xs font-bold transition',
                                        'border-accent bg-accent/15 text-accent-dark dark:text-accent' => (string) $amount === (string) $quick,
                                        'border-slate-200 text-slate-600 hover:border-accent/60 hover:bg-accent/10 dark:border-[var(--brand-card-border-dark)] dark:text-slate-300' => (string) $amount !== (string) $quick,
                                    ])>
                                {{ $currency === 'NGN' ? '₦'.number_format($quick) : '$'.$quick }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Amount</label>
                    <input type="number" step="0.01" min="1" wire:model="amount" placeholder="{{ $currency === 'NGN' ? '5000' : '20' }}"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                    @error('amount') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                {{-- Payment mode radio rows (only Active gateways show) --}}
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Pay with</label>
                    @if (empty($gateways))
                        <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500 dark:border-[var(--brand-card-border-dark)] dark:text-slate-400">
                            Online top-up is being set up. Please check back shortly.
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach ($gateways as $gw => [$gwLabel, $gwHint])
                                <label wire:key="gw-{{ $gw }}"
                                       @class([
                                           'flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition',
                                           'border-primary bg-primary/5 shadow-sm dark:bg-primary/15' => $gateway === $gw,
                                           'border-slate-200 hover:border-primary/40 dark:border-[var(--brand-card-border-dark)]' => $gateway !== $gw,
                                       ])>
                                    <input type="radio" wire:model.live="gateway" value="{{ $gw }}" class="text-primary focus:ring-primary/40">
                                    <x-payment-icon :slug="$gw" class="h-9" />
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                            {{ $gwLabel }}
                                            {{-- Honest test-mode tag (BUILD-2 §8): never hidden from the user. --}}
                                            @if (\App\Support\PaymentSandbox::isTest($gw))
                                                <span class="rounded-full bg-orange-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-orange-700 dark:bg-orange-500/20 dark:text-orange-300">Test mode</span>
                                            @endif
                                        </span>
                                        <span class="block truncate text-[11px] text-slate-400 dark:text-slate-500">{{ $gwHint }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('gateway') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <button type="submit" @disabled(empty($gateways)) wire:loading.attr="disabled" wire:target="topUp"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="topUp" class="inline-flex items-center gap-2"><x-icon name="credit-card" class="h-4 w-4" /> Continue to payment</span>
                    <span wire:loading wire:target="topUp" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Starting…</span>
                </button>

                {{-- Accepted methods (real brand logos) — trust strip. --}}
                <div class="flex flex-wrap items-center justify-center gap-1.5 pt-1">
                    <span class="mr-1 text-[11px] text-slate-400 dark:text-slate-500">We accept</span>
                    @foreach (['visa', 'mastercard', 'googlepay', 'applepay'] as $mark)
                        <x-payment-icon :slug="$mark" class="h-6" />
                    @endforeach
                </div>
                <p class="text-center text-[11px] text-slate-400 dark:text-slate-500">You’ll be redirected to a secure payment page.</p>
            </form>
        </div>
    </div>

    {{-- ============================== PAYOUT ============================== --}}
    <div x-show="tab === 'payout'" x-cloak class="mt-5">
        {{-- Payout & withdrawals — surfaced right on the wallet page (owner
             request), not buried behind a separate menu. Reuses the SAME
             Withdraw component/services as /rewards/withdraw so there is
             exactly one place bank-account and withdrawal logic lives. Every
             account is verified against its actual payout provider BEFORE it
             is ever saved (PayoutAccountService::addAccount()) — a
             mistyped/unconfirmable account is refused outright, never stored. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
            <div class="mb-4 flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                    <x-icon name="credit-card" class="h-5 w-5" />
                </span>
                <span>
                    <span class="block text-base font-semibold text-slate-900 dark:text-slate-100">Payout &amp; withdrawals</span>
                    @if ($payoutAccount)
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $payoutAccount->bank_name }} · {{ $payoutAccount->masked_number }} · ${{ number_format($withdrawableUsd, 2) }} available</span>
                    @else
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Set up your bank account to withdraw earnings</span>
                    @endif
                </span>
            </div>

            {{-- Bank-account setup is always free — only withdrawing PAST the
                 free-payout threshold needs identity verification (§3). --}}
            @if ($requiresKyc && ! $canWithdraw)
                <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
                    <x-icon name="shield-check" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>
                        You've used your free withdrawals — verify your identity to keep withdrawing.
                        <a href="{{ route('account.verify') }}" wire:navigate class="font-semibold underline">Verify now</a>
                    </span>
                </div>
            @endif

            <livewire:withdraw />
        </div>
    </div>

    {{-- ============================== SPENDING ============================= --}}
    <div x-show="tab === 'spending'" x-cloak class="mt-5">
        {{-- My Spending (Module 32 pick — Gidarx aurora balance card, made
             functional): real this-month figures + a 14-day spend sparkline. --}}
        <div class="nx-aurora">
            <span class="nx-aurora__glow nx-aurora__glow--1" aria-hidden="true"></span>
            <span class="nx-aurora__glow nx-aurora__glow--2" aria-hidden="true"></span>
            <div class="relative grid gap-6 sm:grid-cols-2">
                <div>
                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-teal-100/90">
                        <x-icon name="signal" class="h-4 w-4" /> My spending — {{ now()->format('F') }}
                    </p>
                    <p class="mt-3 font-display text-4xl font-bold tracking-tight text-white">${{ number_format(max($spentUsd, 0), 2) }}</p>
                    <p class="mt-1 text-xs text-teal-100/80">spent on eSIMs &amp; numbers this month</p>

                    <dl class="mt-5 space-y-1.5 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="flex items-center gap-1.5 text-teal-100/80"><x-icon name="zap" class="h-3.5 w-3.5 text-accent" /> Topped up (USD)</dt>
                            <dd class="font-semibold text-white">${{ number_format($topupUsd, 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="flex items-center gap-1.5 text-teal-100/80"><x-icon name="zap" class="h-3.5 w-3.5 text-accent" /> Topped up (NGN)</dt>
                            <dd class="font-semibold text-white">NGN {{ number_format($topupNgn, 2) }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="flex flex-col justify-end">
                    <p class="mb-2 text-right text-[11px] font-medium uppercase tracking-wider text-teal-100/70">Last 14 days</p>
                    <div class="rounded-2xl bg-white/10 p-3 backdrop-blur">
                        @if ($hasSpendData)
                            <svg viewBox="0 0 200 48" class="h-16 w-full" role="img" aria-label="Daily spending, last 14 days" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="nx-spark-fill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#D4A017" stop-opacity="0.55" />
                                        <stop offset="100%" stop-color="#D4A017" stop-opacity="0" />
                                    </linearGradient>
                                </defs>
                                <polygon points="0,44 {{ $sparkline }} 200,44" fill="url(#nx-spark-fill)" />
                                <polyline points="{{ $sparkline }}" fill="none" stroke="#D4A017" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        @else
                            <div class="flex h-16 items-center justify-center gap-2 text-xs text-teal-100/70">
                                <x-icon name="signal" class="h-4 w-4" /> Your spending chart appears after your first purchase.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <h2 class="mb-3 mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Recent transactions</h2>
        <div class="space-y-2">
            @forelse ($transactions as $txn)
                <div wire:key="txn-{{ $txn->id }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl',
                        'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400' => in_array($txn->type, ['debit', 'withdrawal']),
                        'bg-green-50 text-green-600 dark:bg-green-950/40 dark:text-green-400' => ! in_array($txn->type, ['debit', 'withdrawal']),
                    ])>
                        @if (in_array($txn->type, ['debit', 'withdrawal']))
                            <x-icon name="upload" class="h-4 w-4" />
                        @else
                            <x-icon name="download" class="h-4 w-4" />
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold capitalize text-slate-900 dark:text-slate-100">{{ $txn->type }}</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ $txn->created_at->diffForHumans() }} · balance after {{ $txn->currency }} {{ number_format((float) $txn->balance_after, 2) }}</p>
                    </div>
                    <span @class([
                        'shrink-0 text-sm font-bold',
                        'text-red-600 dark:text-red-400' => in_array($txn->type, ['debit', 'withdrawal']),
                        'text-green-600 dark:text-green-400' => ! in_array($txn->type, ['debit', 'withdrawal']),
                    ])>{{ in_array($txn->type, ['debit', 'withdrawal']) ? '−' : '+' }}{{ $txn->currency }} {{ number_format((float) $txn->amount, 2) }}</span>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400 dark:border-[var(--brand-card-border-dark)] dark:text-slate-500">No transactions yet.</div>
            @endforelse
        </div>
    </div>
</div>
