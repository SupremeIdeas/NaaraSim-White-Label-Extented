{{-- Withdraw on the skin system (S3 Batch 2). Renders as a page at /rewards/withdraw and embedded in the Wallet's Payout tab (`bare`
     = no second canvas inside the Wallet's). Livewire bindings and server rules are unchanged. --}}
<x-nx.page :bare="$embedded">
    @if ($embedded)
        <h2 class="ns-h1" style="font-size:22px;margin:18px 0 0">Withdraw earnings</h2>
    @else
        <h1 class="ns-h1" style="margin-top:6px">Withdraw earnings</h1>
    @endif
    <p class="ns-sub">Cash out the NaaraCredits you earned from referrals to your bank account.</p>

    {{-- Balance --}}
    <x-nx.balance-hero style="margin-top:14px" label="Available to withdraw" icon="wallet" :amount="'$'.number_format($availableUsd, 2)"
                       :sub="number_format($withdrawableCredits, 0).' withdrawable credits · minimum $'.number_format($minWithdrawal, 2)" />

    @unless ($enabled)
        <x-nx.note icon="info" variant="warn">Withdrawals are currently paused. Please check back soon.</x-nx.note>
    @endunless

    {{-- Free-payout / KYC threshold state (§3) — positive-framed, same pattern as the shared PayoutDashboard. Setting up an account
         below is always free; this only affects submitting a withdrawal. --}}
    @if ($requiresKyc && ! $canWithdraw)
        <x-nx.note icon="shield" variant="warn">
            <b style="display:block;color:rgb(var(--nx-text));font-weight:600">You've used your {{ $freeCount }} free withdrawals</b>
            Verify your identity to keep withdrawing — it only takes a minute.
            <a href="{{ route('account.verify') }}" wire:navigate class="ns-btn ns-btn--solid" style="margin-top:10px;display:inline-flex">Verify identity</a>
        </x-nx.note>
    @elseif (! $requiresKyc)
        <p class="ns-sub" style="font-size:13px;display:flex;gap:6px;align-items:center"><x-nx.icon name="check" style="color:rgb(var(--nx-ok))" />{{ $remainingFree }} of {{ $freeCount }} free withdrawals left before identity verification is needed.</p>
    @endif

    <div style="margin-top:18px"><livewire:payout-step-up /></div>

    {{-- Step 1 — Choose how to get paid (Rail Guide). Choosing a rail opens the matching setup below. --}}
    <div style="margin-top:26px">
        <livewire:payout-guide :embedded="true" />
    </div>

    {{-- Payout accounts --}}
    <div class="ns-lbl" style="margin-top:26px;color:rgb(var(--nx-text));font-weight:600">Your payout accounts</div>
    @forelse ($accounts as $acct)
        <div class="ns-row" style="height:auto;padding:12px 14px;align-items:flex-start;flex-wrap:wrap" wire:key="acct-{{ $acct->id }}">
            <span class="ns-text" style="min-width:0;flex:1 1 11rem">
                <b style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;font-size:16px;font-weight:600">
                    {{ $acct->account_name }}
                    @if ($acct->is_default)<x-nx.pill variant="best">Default</x-nx.pill>@endif
                    {{-- §8: highlight the highest-inbound-volume rail (never hides others). --}}
                    @if ($recommendedGateway && $acct->provider === $recommendedGateway)
                        <x-nx.pill variant="rec"><x-nx.icon name="zap" /> Recommended — fast payout</x-nx.pill>
                    @endif
                </b>
                <small class="ns-small" style="display:block">{{ $acct->bank_name }} · {{ $acct->masked_number }} · {{ $acct->currency }}</small>
                {{-- A Stripe Connect account isn't usable until Stripe's own onboarding is complete — never let it look silently stuck. --}}
                @if ($acct->type === 'stripe' && ! $acct->payouts_enabled)
                    <small style="display:flex;align-items:center;gap:6px;margin-top:4px;color:rgb(var(--nx-warn))">
                        <x-nx.icon name="info" /> Onboarding incomplete
                        <button type="button" wire:click="connectStripe" style="font-weight:600;text-decoration:underline">Continue setup</button>
                    </small>
                @endif
            </span>
            <span style="display:flex;gap:8px;flex:none">
                @unless ($acct->is_default)
                    <button type="button" class="ns-btn" style="margin:0;height:38px;font-size:13px" wire:click="setDefault({{ $acct->id }})">Make default</button>
                @endunless
                <button type="button" class="ns-btn" style="margin:0;height:38px;font-size:13px;color:rgb(var(--nx-bad))" wire:click="removeAccount({{ $acct->id }})" wire:confirm="Remove this account?">Remove</button>
            </span>
        </div>
    @empty
        <x-nx.empty text="No payout accounts yet — add one below." />
    @endforelse

    {{-- Add account --}}
    <x-nx.step icon="plus" style="margin-top:26px" title="Add a payout account" />

    @if ($paypalAvailable || $stripeAvailable)
        <div class="ns-seg" style="margin-top:6px" role="group" aria-label="Account type">
            <button type="button" wire:click="$set('accountType', 'bank')" class="{{ $accountType === 'bank' ? 'is-on' : '' }}" aria-pressed="{{ $accountType === 'bank' ? 'true' : 'false' }}">Bank account</button>
            @if ($paypalAvailable)
                <button type="button" wire:click="$set('accountType', 'paypal')" class="{{ $accountType === 'paypal' ? 'is-on' : '' }}" aria-pressed="{{ $accountType === 'paypal' ? 'true' : 'false' }}">PayPal</button>
            @endif
            @if ($stripeAvailable)
                <button type="button" wire:click="$set('accountType', 'stripe')" class="{{ $accountType === 'stripe' ? 'is-on' : '' }}" aria-pressed="{{ $accountType === 'stripe' ? 'true' : 'false' }}">Stripe</button>
            @endif
        </div>
    @endif

    @if ($accountError)
        <x-nx.note icon="info" variant="warn" role="alert">{{ $accountError }}</x-nx.note>
    @endif

    @if ($accountType === 'bank')
        <p class="ns-sub" style="font-size:13px">We confirm the account name with your bank before saving.</p>
        <label class="ns-lbl" for="wd-country" style="display:block;margin-top:14px">Country</label>
        <select id="wd-country" wire:model.live="country" class="ns-select">
            <option value="NG">Nigeria</option>
            <option value="GH">Ghana</option>
            <option value="KE">Kenya</option>
            <option value="ZA">South Africa</option>
        </select>
        <label class="ns-lbl" for="wd-bank" style="display:block;margin-top:14px">Bank</label>
        <select id="wd-bank" wire:model="bankCode" class="ns-select">
            <option value="">Select a bank</option>
            @foreach ($banks as $bank)
                <option value="{{ $bank['code'] }}">{{ $bank['name'] }}</option>
            @endforeach
        </select>
        @error('bankCode') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror
        @if (empty($banks))
            <p class="ns-sub" style="font-size:13px">Bank list loads once a payout provider is configured for this country.</p>
        @endif
        <label class="ns-lbl" for="wd-account" style="display:block;margin-top:14px">Account number</label>
        <label class="ns-search"><input id="wd-account" type="text" wire:model="accountNumber" inputmode="numeric" autocomplete="off"></label>
        @error('accountNumber') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror
        <x-nx.cta icon="check" loading="addAccount" wire:click="addAccount" style="margin-top:18px">
            <span wire:loading.remove wire:target="addAccount">Verify &amp; add account</span>
            <span wire:loading wire:target="addAccount" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Verifying…</span>
        </x-nx.cta>
    @elseif ($accountType === 'paypal')
        {{-- PayPal has no bank-style resolve API to confirm a payout email before sending money — re-typing it is the guard against a
             mistyped destination. --}}
        <p class="ns-sub" style="font-size:13px">Type your PayPal email twice to confirm it — we can't verify a PayPal address in advance the way we do a bank account.</p>
        <label class="ns-lbl" for="wd-pp" style="display:block;margin-top:14px">PayPal email</label>
        <label class="ns-search"><input id="wd-pp" type="email" wire:model="paypalEmail" autocomplete="off"></label>
        @error('paypalEmail') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror
        <label class="ns-lbl" for="wd-pp2" style="display:block;margin-top:14px">Confirm PayPal email</label>
        <label class="ns-search"><input id="wd-pp2" type="email" wire:model="paypalEmailConfirm" autocomplete="off"></label>
        @error('paypalEmailConfirm') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror
        <x-nx.cta icon="check" loading="addPaypalAccount" wire:click="addPaypalAccount" style="margin-top:18px">
            <span wire:loading.remove wire:target="addPaypalAccount">Add PayPal account</span>
            <span wire:loading wire:target="addPaypalAccount" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Adding…</span>
        </x-nx.cta>
    @else
        {{-- Stripe requires their own hosted onboarding (identity, banking details, capability review) before an account can receive
             a transfer — there's nothing to type here, just a handoff. --}}
        @if ($stripeAccount && $stripeAccount->payouts_enabled)
            <x-nx.note icon="check">Your Stripe account is connected and ready for payouts.</x-nx.note>
        @else
            <p class="ns-sub" style="font-size:13px">
                @if ($stripeAccount)
                    Your Stripe account isn't finished onboarding yet — continue where you left off.
                @else
                    You'll be redirected to Stripe to securely set up your payout account.
                @endif
            </p>
            <x-nx.cta icon="check" loading="connectStripe" wire:click="connectStripe" style="margin-top:18px">
                <span wire:loading.remove wire:target="connectStripe">{{ $stripeAccount ? 'Continue Stripe setup' : 'Connect with Stripe' }}</span>
                <span wire:loading wire:target="connectStripe" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Opening Stripe…</span>
            </x-nx.cta>
        @endif
    @endif

    {{-- Withdraw --}}
    <x-nx.step icon="cards" style="margin-top:26px" title="Request a withdrawal" />

    @if ($withdrawError)
        <x-nx.note icon="info" variant="warn" role="alert">{{ $withdrawError }}</x-nx.note>
    @endif

    <label class="ns-lbl" for="wd-to" style="display:block">To account</label>
    <select id="wd-to" wire:model="accountId" class="ns-select">
        <option value="">Select an account</option>
        @foreach ($accounts as $acct)
            <option value="{{ $acct->id }}">{{ $acct->account_name }} · {{ $acct->masked_number }}</option>
        @endforeach
    </select>
    @error('accountId') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror

    <label class="ns-lbl" for="wd-amount" style="display:block;margin-top:14px">Amount (USD)</label>
    <label class="ns-search"><input id="wd-amount" type="number" step="0.01" min="0" wire:model="amountUsd" inputmode="decimal"></label>
    @error('amountUsd') <x-nx.note icon="info" variant="warn" role="alert">{{ $message }}</x-nx.note> @enderror

    {{-- Money leaves the wallet here: gold, disabled while in flight and whenever withdrawals are off or gated. --}}
    <x-nx.cta variant="gold" icon="cards" loading="withdraw" wire:click="withdraw" style="margin-top:18px" :disabled="! $enabled || ! $canWithdraw">
        <span wire:loading.remove wire:target="withdraw">Withdraw to bank</span>
        <span wire:loading wire:target="withdraw" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> Withdrawing…</span>
    </x-nx.cta>
</x-nx.page>
