{{-- Merchant dashboard on the skin system (S3 Batch 6): header with tier badge, stats, V2 links / upgrade, invite links, storefront, withdraw, ledger, customers. --}}
<div>
<x-nx.page class="ns-pg ns-pg--read">
    {{-- Merchant hero: a distinct banner that sets a merchant account apart from a normal user dashboard (self-hosted Lottie), with the V1/V2 tier badge. --}}
    <div class="ns-pg__card ns-ring" style="margin-top:6px">
        <div style="display:flex;align-items:center;gap:16px">
            @if ($merchant->logo_url)
                <img src="{{ $merchant->logo_url }}" alt="{{ $merchant->business_name }}" style="width:56px;height:56px;border-radius:16px;object-fit:contain;flex:none;background:rgb(var(--nx-surface-3))">
            @else
                <span class="ns-ct__avatar" style="display:grid;place-items:center;width:56px;height:56px;border-radius:16px;flex:none;font-size:20px;font-weight:700;text-transform:uppercase;background-color: {{ $merchant->brand_color ?: 'rgb(var(--nx-cta-b))' }}"><span>{{ \Illuminate\Support\Str::of($merchant->business_name)->trim()->substr(0, 2) }}</span></span>
            @endif
            <div style="min-width:0;flex:1">
                <h1 class="ns-h1" style="font-size:24px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $merchant->business_name }}</h1>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:6px">
                    {{-- ONE tier badge, fully contained in a fixed, clipped box so the medal artwork can't overflow into the copy. Same chip for V1 and V2. --}}
                    <span class="ns-st ns-st--info" style="padding:3px 12px 3px 4px;gap:6px">
                        <span style="display:inline-flex;width:28px;height:28px;overflow:hidden;border-radius:50%"><x-lottie name="{{ $merchant->isV2() ? 'merchant-v2-badge' : 'merchant-v1-badge' }}" label="{{ $merchant->isV2() ? 'Merchant V2' : 'Merchant V1' }} badge" class="h-7 w-7" /></span>
                        {{ $merchant->isV2() ? 'Merchant V2' : 'Merchant V1' }}
                    </span>
                    <span class="ns-small">Your NaaraSim reseller storefront</span>
                </div>
            </div>
            <x-lottie name="merchant-hero" label="Merchant" class="hidden h-24 w-24 shrink-0 sm:block sm:h-28 sm:w-28" />
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="ns-pg__cols3" style="margin-top:14px;grid-template-columns:repeat(3,minmax(0,1fr))">
        <div class="ns-pg__stat ns-ring"><small>Customers</small><b>{{ $customerCount }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Available</small><b class="is-accent" style="color:color-mix(in srgb, rgb(var(--nx-teal-ink)) 62%, rgb(var(--nx-text)))">${{ number_format($balance, 2) }}</b></div>
        <a href="{{ route('merchant.earnings') }}" wire:navigate class="ns-pg__stat ns-ring" style="display:block">
            <small>Lifetime earned</small><b>${{ number_format($lifetime, 2) }}</b>
            <span class="ns-pg__act ns-pg__act--link" style="margin-top:4px;font-size:12.5px">View analytics <x-nx.icon name="chev" style="font-size:12px" /></span>
        </a>
    </div>

    {{-- Merchant V2 --}}
    @if ($merchant->isV2())
        <div class="ns-pg__cols3 ns-pg__cols3--2" style="margin-top:20px">
            <a href="{{ route('merchant.clients') }}" wire:navigate class="ns-pg__path ns-ring"><span class="ns-tile" style="width:44px;height:44px;font-size:22px"><x-nx.icon name="users" /></span><div><b>Clients</b><small>Manage eSIMs for people without an account</small></div></a>
            <a href="{{ route('developer') }}" wire:navigate class="ns-pg__path ns-ring"><span class="ns-tile" style="width:44px;height:44px;font-size:22px"><x-nx.icon name="code" /></span><div><b>Developer portal</b><small>API keys &amp; docs, first-class access</small></div></a>
        </div>
    @else
        <div class="ns-pg__card ns-pg__tier is-hi" style="margin-top:20px">
            <div class="ns-pg__row" style="align-items:center;flex-wrap:wrap">
                <div class="is-grow" style="min-width:14rem">
                    <b class="ns-pg__h2" style="display:flex;align-items:center;gap:8px"><span class="nx-badge">V2</span> Upgrade to Merchant V2</b>
                    <p class="ns-pg__hint" style="font-size:14px">Manage eSIMs &amp; numbers for clients who never log in, plus first-class developer-portal access. One-time <strong style="color:rgb(var(--nx-text))">${{ number_format(\App\Support\MerchantSettings::upgradePriceUsd(), 2) }}</strong> from your wallet.</p>
                </div>
                <button type="button" wire:click="upgradeToV2" wire:loading.attr="disabled" wire:target="upgradeToV2" class="ns-cta ns-cta--pill">
                    <span wire:loading.remove wire:target="upgradeToV2">Upgrade now</span>
                    <span wire:loading wire:target="upgradeToV2">Upgrading…</span>
                </button>
            </div>
        </div>
    @endif

    {{-- Invite link --}}
    <div class="ns-pg__card ns-ring" x-data="{ copied: false, copy() { navigator.clipboard.writeText('{{ $inviteUrl }}').then(() => { this.copied = true; setTimeout(() => this.copied = false, 1500); }); } }">
        <h2 class="ns-bh__h" style="margin-bottom:4px"><x-nx.icon name="globe" /> Your invite link</h2>
        <p class="ns-pg__hint" style="margin:0 0 12px">Share this. Anyone who signs up through it becomes your customer, and you earn on every purchase they make.</p>
        <div class="ns-pg__row" style="flex-wrap:nowrap">
            <input type="text" readonly value="{{ $inviteUrl }}" aria-label="Your invite link" class="ns-input">
            <button type="button" x-on:click="copy()" class="ns-cta ns-cta--pill" style="height:50px;flex:none"><x-nx.icon name="file" /> <span x-text="copied ? 'Copied' : 'Copy'"></span></button>
        </div>
    </div>

    {{-- Invite another merchant (BUILD-7 §4): a one-time bonus, NOT earn-from-their-sales. --}}
    @php($merchantInvite = $inviteUrl.(str_contains($inviteUrl, '?') ? '&' : '?').'as=merchant')
    <div class="ns-pg__card ns-pg__card--warn" x-data="{ copied: false, copy() { navigator.clipboard.writeText('{{ $merchantInvite }}').then(() => { this.copied = true; setTimeout(() => this.copied = false, 1500); }); } }">
        <h2 class="ns-bh__h" style="margin-bottom:4px"><x-nx.icon name="users" /> Invite another merchant</h2>
        <p class="ns-pg__hint" style="margin:0 0 12px">Know a business that should resell on {{ \App\Support\BrandSettings::name() }}? Share this. When they join and become a merchant, you get a <strong style="color:rgb(var(--nx-text))">one-time bonus</strong>. (This is a one-off reward, not a cut of their sales.)</p>
        <div class="ns-pg__row" style="flex-wrap:nowrap">
            <input type="text" readonly value="{{ $merchantInvite }}" aria-label="Merchant invite link" class="ns-input">
            <button type="button" x-on:click="copy()" class="ns-cta ns-cta--pill ns-cta--gold" style="height:50px;flex:none"><x-nx.icon name="file" /> <span x-text="copied ? 'Copied' : 'Copy'"></span></button>
        </div>
    </div>

    {{-- Storefront branding --}}
    <div class="ns-pg__card ns-ring">
        <h2 class="ns-bh__h"><x-nx.icon name="cog" /> Storefront</h2>
        <div class="ns-pg__form">
            <div class="ns-pg__two">
                <div>
                    <label class="ns-pg__lbl" for="md-name">Business name</label>
                    <input id="md-name" type="text" wire:model="businessName" class="ns-input">
                    @error('businessName') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="ns-pg__lbl" for="md-col">Brand colour</label>
                    <div class="ns-pg__row" style="flex-wrap:nowrap;align-items:center">
                        <input type="color" wire:model="brandColor" aria-label="Pick brand colour" style="width:48px;height:50px;flex:none;border-radius:10px;border:1px solid rgb(var(--nx-line-strong));background:transparent">
                        <input id="md-col" type="text" wire:model="brandColor" class="ns-input">
                    </div>
                    @error('brandColor') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                </div>
            </div>
            <label class="ns-pg__lbl" for="md-logo" style="margin-top:8px">Logo <span style="font-weight:400">(optional, PNG/JPG)</span></label>
            <input id="md-logo" type="file" wire:model="logo" accept="image/*" class="ns-ct__file">
            @error('logo') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
        </div>
        <button type="button" wire:click="saveStorefront" wire:loading.attr="disabled" wire:target="saveStorefront,logo" class="ns-cta ns-cta--pill" style="margin-top:16px"><x-nx.icon name="check" /> Save storefront</button>
        <p class="ns-pg__hint" style="margin-top:12px">Pricing is set by NaaraSim. You brand the storefront, we run the engine.</p>
    </div>

    {{-- Withdraw earnings --}}
    <div class="ns-pg__card ns-ring">
        <h2 class="ns-bh__h"><x-nx.icon name="wallet" /> Withdraw earnings</h2>
        {{-- §5.1: explain the identity gate up front, before they hit it. --}}
        <x-kyc-gate-notice :level="2" action="withdraw your earnings" style="margin-bottom:12px" />
        @if (! $payoutsEnabled)
            <p class="ns-sub" style="margin:0">Withdrawals aren’t open yet. Your earnings keep accruing safely.</p>
        @elseif ($accounts->isEmpty())
            <p class="ns-sub" style="margin:0">Add a verified payout account on the <a href="{{ route('rewards.withdraw') }}" wire:navigate class="ns-linkink" style="font-weight:600;text-decoration:underline">withdrawals page</a> first.</p>
        @else
            @if ($withdrawError)<div class="ns-pg__err" role="alert" style="margin-top:0;margin-bottom:12px">{{ $withdrawError }}</div>@endif
            <div class="ns-pg__two">
                <div>
                    <label class="ns-pg__lbl" for="md-acct">To account</label>
                    <select id="md-acct" wire:model="accountId" class="ns-input">@foreach ($accounts as $acct)<option value="{{ $acct->id }}">{{ $acct->bank_name ?? $acct->bank_code }} · {{ $acct->account_name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="ns-pg__lbl" for="md-amt">Amount (USD)</label>
                    <input id="md-amt" type="number" step="0.01" min="0" wire:model="amountUsd" placeholder="0.00" class="ns-input">
                </div>
            </div>
            <button type="button" wire:click="withdraw" wire:loading.attr="disabled" wire:target="withdraw" class="ns-cta ns-cta--pill" style="margin-top:16px"><x-nx.icon name="send" /> Request withdrawal</button>
        @endif
    </div>

    {{-- Earnings ledger --}}
    @if ($ledger->isNotEmpty())
        <span class="ns-lbl" style="margin-top:22px">Recent earnings</span>
        <div class="ns-pg__ledger ns-ring">
            @foreach ($ledger as $row)
                <div class="ns-pg__lrow" wire:key="earn-{{ $row->id }}">
                    <div style="min-width:0"><b>{{ ucfirst($row->type) }}<span style="font-weight:400;color:rgb(var(--nx-text-2))"> · {{ $row->description }}</span></b><small>{{ $row->created_at->diffForHumans() }}</small></div>
                    <span class="{{ (float) $row->amount > 0 ? 'is-pos' : 'is-neg' }}" style="flex:none">{{ (float) $row->amount > 0 ? '+' : '' }}${{ number_format((float) $row->amount, 2) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Customers --}}
    @if ($customers->isNotEmpty())
        <span class="ns-lbl" style="margin-top:22px">Your customers</span>
        <div class="ns-pg__ledger ns-ring">
            @foreach ($customers as $customer)
                <div class="ns-pg__lrow" wire:key="cust-{{ $customer->id }}" style="justify-content:flex-start;gap:12px">
                    <span class="ns-avatar" style="width:34px;height:34px;font-size:13px;text-transform:uppercase;background:rgb(var(--nx-surface-3));color:rgb(var(--nx-text))">{{ \Illuminate\Support\Str::of($customer->name)->trim()->substr(0, 1) }}</span>
                    <b>{{ $customer->name }}</b>
                    <small style="margin-left:auto">Joined {{ $customer->created_at->format('M Y') }}</small>
                </div>
            @endforeach
        </div>
    @endif
</x-nx.page>
</div>
