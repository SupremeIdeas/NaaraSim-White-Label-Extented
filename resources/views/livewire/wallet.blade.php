{{-- Wallet on the skin system (Prompt 20 §23, S3 Batch 2). Every Livewire binding, Alpine tab and server rule is unchanged; only the
     markup moved onto the x-nx components so the signed-in member's skin reaches it. --}}
<x-nx.page class="ns-narrow" x-data="{ tab: 'topup' }">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
        <h1 class="ns-h1" style="margin:6px 0 0">Wallet</h1>
        {{-- Display-currency switcher (owner request). USD stays the settlement currency; this only changes what prices are SHOWN in. --}}
        <div style="position:relative" x-data="{ open: false }">
            <button type="button" class="ns-btn" style="height:40px;margin:0;gap:6px" x-on:click="open = !open" x-on:click.outside="open = false" :aria-expanded="open">
                <x-nx.icon name="globe" /> {{ $displayCurrency }} <x-nx.icon name="chevron-right" style="transform:rotate(90deg)" />
            </button>
            <div x-show="open" x-cloak x-transition class="ns-list" style="position:absolute;right:0;top:46px;z-index:20;width:13rem;max-height:16rem;overflow-y:auto;box-shadow:var(--nx-e1)">
                @foreach ($currencyOptions as $code => $meta)
                    <button type="button" wire:key="cur-{{ $code }}" x-on:click="open = false" wire:click="setCurrency('{{ $code }}')"
                            class="ns-list__row {{ $displayCurrency === $code ? 'is-on' : '' }}" style="font-size:14px;min-height:44px;justify-content:space-between">
                        <span>{{ $meta[1] }}</span>
                        <span class="ns-small">{{ $meta[0] }} {{ $code }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Top-up "processing" banner (owner request): the credit lands via a queued webhook job that can take up to ~2 minutes on shared
         hosting's cron-drain cadence. wire:poll only while $pendingTopUp is set; the moment the credit lands the banner disappears on
         its own next render (same pattern get-number uses for an OTP "waiting" state). --}}
    @if ($pendingTopUp)
        <div wire:poll.5s="checkPendingTopUp">
            <x-nx.note icon="info">
                <b style="display:block;color:rgb(var(--nx-text));font-weight:600"><x-ui.spinner class="h-3.5 w-3.5" /> Processing your top-up…</b>
                {{ ucfirst($pendingTopUp['gateway']) }} confirmed your payment — crediting {{ $pendingTopUp['currency'] }} {{ number_format((float) $pendingTopUp['amount'], 2) }} to your wallet now. This usually takes under a minute.
            </x-nx.note>
        </div>
    @endif

    {{-- Balance widget: ONE parent card (owner request) housing the balance, the live NGN equivalent and the quick actions, so every
         skin gives the whole widget a premium treatment. The settlement (USD) balance leads; NGN is a secondary row — no invented
         "total" across two real currencies. Quick actions switch the tabs below (no fake buttons). --}}
    <x-nx.balance-hero class="ns-wallet-hero" style="margin-top:14px" label="USD balance" icon="credit-card" :amount="'$'.number_format((float) $wallet->usd_balance, 2)"
                       :sub="$usdLocal ? '≈ '.$usdLocal.' · live rate' : null">
        <x-slot:footer>
            {{-- Unified USD Wallet (Part B): usd_balance is the ONE spendable balance. The NGN figure is the LIVE-rate equivalent for
                 convenience — never a separate, independently-growing balance. --}}
            <div class="ns-balance__sub"><span><x-nx.icon name="wallet" /> ≈ NGN</span><b>{{ $ngnLive }}</b></div>
            {{-- Legacy NGN, only if there's actually one to show (pre-Part-B top-ups). Historical only — never spendable, never grows again. --}}
            @if ((float) $wallet->ngn_balance > 0)
                <div class="ns-balance__sub ns-balance__sub--quiet"><span>Legacy NGN balance (historical, from before top-ups settled in USD)</span><b>NGN {{ number_format((float) $wallet->ngn_balance, 2) }}</b></div>
            @endif
            <div class="ns-balance__actions">
                <button type="button" @click="tab = 'topup'"><x-nx.icon name="zap" /> Top up</button>
                <button type="button" @click="tab = 'payout'"><x-nx.icon name="credit-card" /> Withdraw</button>
                <button type="button" @click="tab = 'spending'"><x-nx.icon name="signal" /> Spending</button>
            </div>
        </x-slot:footer>
    </x-nx.balance-hero>

    {{-- Tabbed navigation (owner request): Top Up / Payout / Spending / Shared each get their own tab instead of one long scroll. --}}
    <div class="ns-seg" style="margin-top:22px" role="tablist">
        @foreach (['topup' => 'Top Up', 'payout' => 'Payout', 'spending' => 'Spending', 'shared' => 'Shared'] as $key => $text)
            <button type="button" role="tab" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'is-on' : ''" :aria-selected="tab === '{{ $key }}'">{{ $text }}</button>
        @endforeach
    </div>

    {{-- ============================== TOP UP ============================== --}}
    <div x-show="tab === 'topup'">
        {{-- Top up (payment-method radios, quick-cash blocks) — all wired to the real gateway initialisation. --}}
        @if ($this->isIosBuild())
            {{-- App Store payments-compliance doc (BUILD-5 §6): no free-floating "add funds" entry point on iOS — pay at the point of
                 buying an eSIM or a number instead. --}}
            <x-nx.note icon="info">
                <b style="display:block;color:rgb(var(--nx-text));font-weight:600">Wallet top-ups aren't available here</b>
                Pay for your eSIM or number directly when you check out — your card or Apple Pay covers it in one step. Any refunds or change still land in your wallet.
            </x-nx.note>
        @else
            @if ($error)
                <x-nx.note icon="info" variant="warn" role="alert">{{ $error }}</x-nx.note>
            @endif

            <form wire:submit="topUp">
                {{-- Pay-in currency (owner request: a real dropdown) — every currency any configured gateway accepts. USD credits the
                     wallet directly; everything else is converted to a USD credit locked at the live rate. Changing this filters
                     "Pay with" below to only the gateways that actually accept it (updatedCurrency()). --}}
                <label class="ns-lbl" for="wallet-currency" style="display:block;margin-top:18px">Top up in</label>
                <select id="wallet-currency" wire:model.live="currency" class="ns-select">
                    @foreach ($payCurrencyOptions as $cur => $curLabel)
                        <option value="{{ $cur }}" @selected($currency === $cur)>{{ $curLabel }} ({{ $cur }})</option>
                    @endforeach
                </select>
                @error('currency') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror
                @if ($currency !== 'USD' && is_numeric($amount) && $amount > 0)
                    <p class="ns-sub" style="font-size:13px">
                        ≈ ${{ number_format(app(\App\Services\Pricing\CurrencyService::class)->toUsd((float) $amount, $currency), 2) }} credited to your wallet (live rate, locked at checkout).
                    </p>
                @endif

                {{-- Quick cash blocks --}}
                <div class="ns-lbl" style="margin-top:18px">Quick amounts</div>
                <div class="ns-len" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-top:8px">
                    @foreach ($currency === 'NGN' ? [1000, 2000, 5000, 10000] : [5, 10, 20, 50] as $quick)
                        <button type="button" wire:key="quick-{{ $currency }}-{{ $quick }}" wire:click="$set('amount', {{ $quick }})"
                                class="{{ (string) $amount === (string) $quick ? 'is-on' : '' }}" aria-pressed="{{ (string) $amount === (string) $quick ? 'true' : 'false' }}">
                            {{ $currency === 'NGN' ? '₦'.number_format($quick) : '$'.$quick }}
                        </button>
                    @endforeach
                </div>

                <label class="ns-lbl" for="wallet-amount" style="display:block;margin-top:18px">Amount</label>
                <label class="ns-search">
                    <x-nx.icon name="wallet" />
                    <input id="wallet-amount" type="number" step="0.01" min="1" wire:model="amount" placeholder="{{ $currency === 'NGN' ? '5000' : '20' }}" inputmode="decimal">
                </label>
                @error('amount') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror

                {{-- Payment mode radio rows (only Active gateways show) --}}
                <div class="ns-lbl" style="margin-top:18px">Pay with</div>
                @if (empty($gateways))
                    <x-nx.empty text="Online top-up is being set up. Please check back shortly." />
                @else
                    @foreach ($gateways as $gw => [$gwLabel, $gwHint])
                        <label wire:key="gw-{{ $gw }}" class="ns-row ns-ring {{ $gateway === $gw ? 'is-selected' : '' }}" style="height:auto;min-height:56px;padding:10px 14px;cursor:pointer">
                            <input type="radio" wire:model.live="gateway" value="{{ $gw }}" class="ns-check-input" style="margin:0">
                            <x-payment-icon :slug="$gw" class="h-9" />
                            <span class="ns-text" style="min-width:0">
                                <b style="display:flex;align-items:center;gap:6px;font-size:16px;font-weight:600">
                                    {{ $gwLabel }}
                                    {{-- Honest test-mode tag (BUILD-2 §8): never hidden from the user. --}}
                                    @if (\App\Support\PaymentSandbox::isTest($gw))<x-nx.pill variant="out">Test mode</x-nx.pill>@endif
                                </b>
                                <small class="ns-small" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $gwHint }}</small>
                            </span>
                        </label>
                    @endforeach
                @endif
                @error('gateway') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror

                <x-nx.cta type="submit" variant="gold" icon="cards" loading="topUp" style="margin-top:20px" :disabled="empty($gateways)">
                    <span wire:loading.remove wire:target="topUp">Continue to payment</span>
                    <span wire:loading wire:target="topUp" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Starting…</span>
                </x-nx.cta>

                {{-- Accepted methods (real brand logos) — trust strip. --}}
                <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;margin-top:14px">
                    <span class="ns-small">We accept</span>
                    @foreach (['visa', 'mastercard', 'googlepay', 'applepay'] as $mark)
                        <x-payment-icon :slug="$mark" class="h-6" />
                    @endforeach
                </div>
                <p class="ns-sub" style="font-size:13px;text-align:center">You’ll be redirected to a secure payment page.</p>
            </form>
        @endif
    </div>

    {{-- ============================== PAYOUT ============================== --}}
    <div x-show="tab === 'payout'" x-cloak>
        {{-- Payout & withdrawals — surfaced right on the wallet page (owner request). Reuses the SAME Withdraw component/services as
             /rewards/withdraw so there is exactly one place bank-account and withdrawal logic lives. Every account is verified
             against its actual payout provider BEFORE it is ever saved (PayoutAccountService::addAccount()). --}}
        <x-nx.step icon="credit-card" style="margin-top:18px" title="Payout & withdrawals"
                   :hint="$payoutAccount ? $payoutAccount->bank_name.' · '.$payoutAccount->masked_number.' · $'.number_format($withdrawableUsd, 2).' available' : 'Set up your bank account to withdraw earnings'" />

        {{-- Bank-account setup is always free — only withdrawing PAST the free-payout threshold needs identity verification (§3). --}}
        @if ($requiresKyc && ! $canWithdraw)
            <x-nx.note icon="shield-check" variant="warn">
                You've used your free withdrawals — verify your identity to keep withdrawing.
                <a href="{{ route('account.verify') }}" wire:navigate style="font-weight:600;text-decoration:underline">Verify now</a>
            </x-nx.note>
        @endif

        <livewire:withdraw :embedded="true" />
    </div>

    {{-- ============================== SPENDING ============================= --}}
    <div x-show="tab === 'spending'" x-cloak>
        {{-- My Spending: real this-month figures + a 14-day spend sparkline. --}}
        <x-nx.balance-hero style="margin-top:18px" label="My spending — {{ now()->format('F') }}" icon="signal" :amount="'$'.number_format(max($spentUsd, 0), 2)" sub="spent on eSIMs & numbers this month" />

        <div class="ns-stats">
            <x-nx.stat label="Topped up (USD)" :value="'$'.number_format($topupUsd, 2)" />
            <x-nx.stat label="Topped up (NGN)" :value="'NGN '.number_format($topupNgn, 2)" />
        </div>

        <div class="ns-card ns-ring" style="padding:14px;margin-top:12px">
            <small class="ns-small" style="display:flex;align-items:center;gap:6px"><x-nx.icon name="bars" style="color:rgb(var(--nx-teal-ink))" /> Last 14 days</small>
            @if ($hasSpendData)
                <svg viewBox="0 0 200 48" class="ns-spark" role="img" aria-label="Daily spending, last 14 days" preserveAspectRatio="none" style="margin-top:8px">
                    <polygon points="0,44 {{ $sparkline }} 200,44" class="ns-spark__fill" />
                    <polyline points="{{ $sparkline }}" class="ns-spark__line ns-spark__line--teal" />
                </svg>
            @else
                <div class="ns-small" style="height:48px;display:flex;align-items:center;gap:8px;margin-top:8px"><x-nx.icon name="bars" /> Your spending chart appears after your first purchase.</div>
            @endif
        </div>

        <div class="ns-lbl" style="margin-top:22px">Recent transactions</div>
        @forelse ($transactions as $txn)
            @php($out = in_array($txn->type, ['debit', 'withdrawal']))
            <div wire:key="txn-{{ $txn->id }}" class="ns-row" style="height:auto;min-height:60px;padding:10px 14px">
                <span class="ns-tile" style="width:40px;height:40px;font-size:19px;--nx-tone:var(--nx-{{ $out ? 'bad' : 'ok' }});color:rgb(var(--nx-{{ $out ? 'bad' : 'ok' }}))"><x-nx.icon :name="$out ? 'upload' : 'download'" /></span>
                <span class="ns-text" style="min-width:0;flex:1">
                    <b style="display:block;font-size:16px;font-weight:600;text-transform:capitalize;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $txn->type }}</b>
                    <small class="ns-small" style="display:block">{{ $txn->created_at->diffForHumans() }} · balance after {{ $txn->currency }} {{ number_format((float) $txn->balance_after, 2) }}</small>
                </span>
                <b style="flex:none;font-size:15px;font-variant-numeric:tabular-nums;color:rgb(var(--nx-{{ $out ? 'bad' : 'ok' }}))">{{ $out ? '−' : '+' }}{{ $txn->currency }} {{ number_format((float) $txn->amount, 2) }}</b>
            </div>
        @empty
            <x-nx.empty text="No transactions yet." />
        @endforelse
    </div>

    {{-- ============================== SHARED PLAN ============================ --}}
    {{-- Prompt 11 §3: a shared wallet plan. Every charge here still debits the OWNER's real UserWallet through WalletService's
         unmodified public methods — a member never has a separate balance, only an optional spend cap tracked against the owner's
         own transaction history. --}}
    <div x-show="tab === 'shared'" x-cloak>
        @if ($pendingInvites->isNotEmpty())
            <div class="ns-lbl" style="margin-top:18px;display:flex;align-items:center;gap:8px"><x-nx.icon name="mail" /> Invites waiting for you</div>
            @foreach ($pendingInvites as $invite)
                <div wire:key="invite-{{ $invite->id }}" class="ns-row" style="height:auto;padding:12px 14px;flex-wrap:wrap">
                    <span class="ns-text" style="min-width:0;flex:1 1 10rem">
                        <b style="display:block;font-size:16px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $invite->walletGroup->owner->name ?? 'A NaaraSim user' }}</b>
                        <small class="ns-small" style="display:block">
                            @if ($invite->spend_cap_usd || $invite->spend_cap_ngn)
                                Cap:
                                @if ($invite->spend_cap_usd) ${{ number_format((float) $invite->spend_cap_usd, 2) }} @endif
                                @if ($invite->spend_cap_ngn) NGN {{ number_format((float) $invite->spend_cap_ngn, 2) }} @endif
                            @else
                                No spend cap
                            @endif
                        </small>
                    </span>
                    <span style="display:flex;gap:8px;flex:none">
                        <button type="button" class="ns-btn ns-btn--solid" style="margin:0;height:40px" wire:click="acceptSharedPlanInvite({{ $invite->id }})" wire:loading.attr="disabled" wire:target="acceptSharedPlanInvite({{ $invite->id }})"><x-nx.icon name="check" /> Accept</button>
                        <button type="button" class="ns-btn" style="margin:0;height:40px" wire:click="leaveSharedPlan({{ $invite->id }})" wire:loading.attr="disabled" wire:target="leaveSharedPlan({{ $invite->id }})"><x-nx.icon name="x" /> Decline</button>
                    </span>
                </div>
            @endforeach
        @endif

        @if ($joinedPlans->isNotEmpty())
            <div class="ns-lbl" style="margin-top:22px;display:flex;align-items:center;gap:8px"><x-nx.icon name="arrow-right-left" /> Plans you've joined</div>
            @foreach ($joinedPlans as $plan)
                <div wire:key="joined-{{ $plan->id }}" class="ns-row" style="height:auto;padding:12px 14px">
                    <span class="ns-text" style="min-width:0;flex:1">
                        <b style="display:block;font-size:16px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $plan->walletGroup->owner->name ?? 'A NaaraSim user' }}'s plan</b>
                        <small class="ns-small" style="display:block">
                            @if ($plan->spend_cap_usd || $plan->spend_cap_ngn)
                                Cap:
                                @if ($plan->spend_cap_usd) ${{ number_format((float) $plan->spend_cap_usd, 2) }} @endif
                                @if ($plan->spend_cap_ngn) NGN {{ number_format((float) $plan->spend_cap_ngn, 2) }} @endif
                            @else
                                No spend cap
                            @endif
                        </small>
                    </span>
                    <button type="button" class="ns-btn" style="margin:0;height:40px;flex:none" wire:click="leaveSharedPlan({{ $plan->id }})" wire:loading.attr="disabled" wire:target="leaveSharedPlan({{ $plan->id }})"><x-nx.icon name="x" /> Leave</button>
                </div>
            @endforeach
        @endif

        <x-nx.step icon="users" style="margin-top:22px" title="Your shared plan" hint="Invite someone to spend from your wallet, up to a cap you set" />

        @if ($inviteError)
            <x-nx.note icon="info" variant="warn" role="alert">{{ $inviteError }}</x-nx.note>
        @endif

        <form wire:submit="inviteToSharedPlan">
            <label class="ns-lbl" for="invite-email" style="display:block">Their NaaraSim email</label>
            <label class="ns-search">
                <x-nx.icon name="user" />
                <input id="invite-email" type="email" wire:model="inviteEmail" placeholder="name@example.com" autocomplete="off">
            </label>
            @error('inviteEmail') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror

            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px">
                <div>
                    <label class="ns-lbl" for="invite-cap-usd" style="display:block">USD cap (optional)</label>
                    <label class="ns-search"><input id="invite-cap-usd" type="number" step="0.01" min="0.01" wire:model="inviteCapUsd" placeholder="No limit" inputmode="decimal"></label>
                    @error('inviteCapUsd') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror
                </div>
                <div>
                    <label class="ns-lbl" for="invite-cap-ngn" style="display:block">NGN cap (optional)</label>
                    <label class="ns-search"><input id="invite-cap-ngn" type="number" step="0.01" min="0.01" wire:model="inviteCapNgn" placeholder="No limit" inputmode="decimal"></label>
                    @error('inviteCapNgn') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror
                </div>
            </div>

            <x-nx.cta type="submit" icon="users" loading="inviteToSharedPlan" style="margin-top:18px">
                <span wire:loading.remove wire:target="inviteToSharedPlan">Send invite</span>
                <span wire:loading wire:target="inviteToSharedPlan" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Sending…</span>
            </x-nx.cta>
        </form>

        @if ($ownGroup && $ownGroup->members->isNotEmpty())
            <div class="ns-lbl" style="margin-top:22px">People on your plan</div>
            @foreach ($ownGroup->members as $member)
                <div wire:key="member-{{ $member->id }}" class="ns-row" style="height:auto;padding:12px 14px">
                    <span class="ns-text" style="min-width:0;flex:1">
                        <b style="display:flex;align-items:center;gap:6px;font-size:16px;font-weight:600">
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $member->user->name ?? $member->user->email ?? 'Member' }}</span>
                            @if (! $member->isActive())<x-nx.pill variant="out"><x-nx.icon name="clock" /> Pending</x-nx.pill>@endif
                        </b>
                        <small class="ns-small" style="display:block">
                            @if ($member->spend_cap_usd)
                                ${{ number_format((float) $memberSpendUsd[$member->id], 2) }} / ${{ number_format((float) $member->spend_cap_usd, 2) }} spent
                            @endif
                            @if ($member->spend_cap_ngn)
                                {{ $member->spend_cap_usd ? ' · ' : '' }}NGN {{ number_format((float) $memberSpendNgn[$member->id], 2) }} / {{ number_format((float) $member->spend_cap_ngn, 2) }} spent
                            @endif
                            @if (! $member->spend_cap_usd && ! $member->spend_cap_ngn)
                                No spend cap
                            @endif
                        </small>
                    </span>
                    <button type="button" class="ns-btn" style="margin:0;height:40px;flex:none;color:rgb(var(--nx-bad))" wire:click="removeSharedPlanMember({{ $member->id }})" wire:loading.attr="disabled" wire:target="removeSharedPlanMember({{ $member->id }})"><x-nx.icon name="trash" /> Remove</button>
                </div>
            @endforeach
        @endif
    </div>
</x-nx.page>
