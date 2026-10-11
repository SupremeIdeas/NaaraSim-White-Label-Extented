{{-- Become a merchant on the skin system (S3 Batch 6): V1 vs V2 plans, unlock paths, application, optional business verification. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <h1 class="ns-h1" style="margin-top:6px">Become a merchant</h1>
    <p class="ns-sub">Run your own co-branded storefront on NaaraSim: resell eSIMs and numbers to your customers and earn on every sale, settled straight to your bank. You never pay for refills. NaaraSim fulfils every order, you're the storefront.</p>

    {{-- V1 vs V2 plan comparison (BUILD-4 §3.1), live from MerchantSettings so a prospective merchant sees what each tier unlocks and its price up front. --}}
    <div class="ns-pg__cols3 ns-pg__cols3--2" style="margin-top:20px">
        <div class="ns-pg__tier ns-ring">
            <div class="ns-pg__head" style="align-items:center;flex-wrap:nowrap"><b class="ns-pg__h2">Merchant V1</b><span class="ns-st">Standard</span></div>
            <p class="ns-pg__hint" style="font-size:14px;margin-top:6px">Your own co-branded storefront. Earn a <strong style="color:rgb(var(--nx-text))">{{ rtrim(rtrim(number_format($pricing['margin'], 2), '0'), '.') }}%</strong> reseller margin on every sale, settled to your bank.</p>
            <ul class="ns-pg__ticks">
                <li><x-nx.icon name="check" /> Co-branded storefront + invite link</li>
                <li><x-nx.icon name="check" /> Earnings on every referred sale</li>
                <li><x-nx.icon name="check" /> Unlock free (spend/referrals) or fast-route fee</li>
            </ul>
        </div>
        <div class="ns-pg__tier is-hi">
            <div class="ns-pg__head" style="align-items:center;flex-wrap:nowrap"><b class="ns-pg__h2">Merchant V2</b><span class="ns-st ns-st--info">${{ rtrim(rtrim(number_format($pricing['upgradePrice'], 2), '0'), '.') }} one-time</span></div>
            <p class="ns-pg__hint" style="font-size:14px;margin-top:6px">Everything in V1, plus manage eSIMs &amp; numbers for clients who never sign in, and first-class API access.</p>
            <ul class="ns-pg__ticks">
                <li><x-nx.icon name="check" /> Everything in V1</li>
                <li><x-nx.icon name="users" /> Client management (no-login customers)</li>
                <li><x-nx.icon name="code" /> Developer portal &amp; API keys</li>
            </ul>
            <p class="ns-small" style="margin:12px 0 0">Upgrade any time from your Merchant dashboard once you're V1.</p>
        </div>
    </div>

    @unless ($programmeOpen)
        <div class="ns-pg__callout ns-pg__callout--warn" style="margin-top:20px"><x-nx.icon name="info" /><div><p style="margin:0;color:rgb(var(--nx-text))">The merchant programme isn't open yet. Please check back soon.</p></div></div>
    @else
        @php($unlocked = $eligibility && $eligibility['eligible'])
        {{-- Stepper: two steps now; identity verification is handled later, at payout time (BUILD-4 §1). --}}
        <ol class="ns-pg__steps" aria-label="Progress">
            <li class="{{ $unlocked ? 'is-done' : 'is-on' }}"><b>1</b> Unlock</li>
            <hr>
            <li class="{{ $merchant ? 'is-done' : ($unlocked ? 'is-on' : '') }}"><b>2</b> Apply</li>
        </ol>

        {{-- Deferred-verification note (§1): no KYB up front. --}}
        <x-nx.note style="margin-top:14px" icon="shield">No verification needed to start. You'll confirm your identity later, only when you first cash out your earnings.</x-nx.note>

        @if ($merchant && $merchant->status === 'active')
            <div class="ns-pg__callout ns-pg__callout--ok" style="margin-top:20px"><x-nx.icon name="check" /><div><b>You're a merchant</b><p>Your storefront <strong style="color:rgb(var(--nx-text))">{{ $merchant->business_name }}</strong> is live. Manage it from your Merchant area.</p></div></div>
        @elseif ($merchant && $merchant->status === 'pending')
            <div class="ns-pg__callout ns-pg__callout--warn" style="margin-top:20px"><x-nx.icon name="info" /><div><b>Application under review</b><p>We're reviewing <strong style="color:rgb(var(--nx-text))">{{ $merchant->business_name }}</strong>. You'll be notified once it's approved.</p></div></div>
        @elseif (! $unlocked)
            {{-- Stage 1: unlock membership: meet ANY one path (§1: no KYB gate). --}}
            <div class="ns-pg__stack ns-pg__stack--sm" style="margin-top:20px">
                <p class="ns-sub" style="margin:0">Unlock membership by meeting <strong style="color:rgb(var(--nx-text))">any one</strong> of these:</p>
                @if ($error)<div class="ns-pg__err" role="alert" style="margin-top:0">{{ $error }}</div>@endif

                {{-- Path 1: spend --}}
                <div class="ns-pg__path ns-ring">
                    <span class="ns-tile" style="{{ $eligibility['spend']['met'] ? '--nx-tone: var(--nx-ok);' : '' }}width:40px;height:40px;font-size:20px"><x-nx.icon name="{{ $eligibility['spend']['met'] ? 'check' : 'cards' }}" /></span>
                    <div><b>Spend ${{ number_format($eligibility['spend']['required'], 0) }} as a user</b><small>You've transacted ${{ number_format($eligibility['spend']['current'], 2) }} so far.</small></div>
                </div>

                {{-- Path 2: fast-route enrollment --}}
                <div class="ns-pg__path ns-ring">
                    <span class="ns-tile" style="{{ $eligibility['enrollment']['met'] ? '--nx-tone: var(--nx-ok);' : '' }}width:40px;height:40px;font-size:20px"><x-nx.icon name="{{ $eligibility['enrollment']['met'] ? 'check' : 'bolt' }}" /></span>
                    <div><b>Fast route: ${{ number_format($eligibility['enrollment']['fee'], 0) }} one-time enrollment</b><small>Paid once from your wallet. Skip the thresholds and unlock instantly.</small></div>
                    @unless ($eligibility['enrollment']['met'])
                        <button type="button" wire:click="payEnrollment" wire:loading.attr="disabled" wire:target="payEnrollment" wire:confirm="Pay the ${{ number_format($eligibility['enrollment']['fee'], 2) }} enrollment fee from your wallet?" class="ns-cta ns-cta--pill ns-cta--sm" style="flex:none">Pay &amp; unlock</button>
                    @endunless
                </div>

                {{-- Path 3: referrals --}}
                <div class="ns-pg__path ns-ring">
                    <span class="ns-tile" style="{{ $eligibility['referrals']['met'] ? '--nx-tone: var(--nx-ok);' : '' }}width:40px;height:40px;font-size:20px"><x-nx.icon name="{{ $eligibility['referrals']['met'] ? 'check' : 'users' }}" /></span>
                    <div><b>Refer {{ number_format($eligibility['referrals']['required']) }} users</b><small>You've referred {{ number_format($eligibility['referrals']['current']) }} so far. <a href="{{ route('referrals') }}" class="ns-linkink" style="text-decoration:underline">Get your link</a></small></div>
                </div>
            </div>
        @else
            {{-- Stage 3: application --}}
            <div class="ns-pg__card ns-ring">
                <h2 class="ns-pg__h2">Set up your storefront</h2>
                <p class="ns-pg__hint" style="color:color-mix(in srgb, rgb(var(--nx-ok)) 72%, rgb(var(--nx-text)))">Membership unlocked. One last step.</p>
                @if ($error)<div class="ns-pg__err" role="alert">{{ $error }}</div>@endif
                <div class="ns-pg__form" style="margin-top:8px">
                    <div class="ns-pg__two">
                        <div>
                            <label class="ns-pg__lbl" for="bm-name">Business name</label>
                            <input id="bm-name" type="text" wire:model="businessName" class="ns-input">
                            @error('businessName') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="ns-pg__lbl" for="bm-col">Brand colour</label>
                            <input id="bm-col" type="color" wire:model="brandColor" class="ns-input" style="padding:4px 6px">
                        </div>
                    </div>
                </div>
                <button type="button" wire:click="apply" wire:loading.attr="disabled" wire:target="apply" class="ns-cta" style="margin-top:16px">
                    <span class="ns-cta__label"><x-nx.icon name="check" /> Submit application</span>
                </button>
            </div>
        @endif

        {{-- OPTIONAL global business verification (BUILD-4 §2.1). Not a gate: a merchant can verify now or later at payout. Data-driven country + registration-type selects (worldwide). --}}
        <div class="ns-pg__card ns-ring">
            <div class="ns-pg__head" style="align-items:center;flex-wrap:nowrap">
                <h2 class="ns-pg__h2">Business verification <span style="font-weight:400;color:rgb(var(--nx-text-2))">(optional now)</span></h2>
                @if ($kybVerified)<span class="ns-st ns-st--ok" style="flex:none"><x-nx.icon name="check" style="font-size:13px;margin-inline-end:4px" /> Verified</span>@endif
            </div>

            @if ($kybVerified)
                <p class="ns-pg__hint">Your business is verified. Larger payouts will clear without extra checks.</p>
            @elseif ($kybAttempt && $kybAttempt->status === 'pending')
                <div class="ns-pg__callout ns-pg__callout--warn" style="margin-top:12px"><x-nx.icon name="info" /><div><p style="margin:0;color:rgb(var(--nx-text))">Your business details are being verified. This usually takes a little while.</p></div></div>
            @else
                <p class="ns-pg__hint">You can start selling right away. Verify your business now, or we'll ask when you first cash out (required for larger payouts).</p>
                @if ($kybAttempt && $kybAttempt->status === 'rejected')
                    <div class="ns-pg__err" role="alert">We couldn't verify your business{{ $kybAttempt->reason ? ': '.$kybAttempt->reason : '.' }} Please try again.</div>
                @endif
                <div class="ns-pg__form" style="margin-top:8px">
                    <div class="ns-pg__cols3">
                        <div>
                            <label class="ns-pg__lbl" for="kb-country">Country</label>
                            <select id="kb-country" wire:model.live="country" class="ns-input">@foreach ($countries as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select>
                        </div>
                        <div>
                            <label class="ns-pg__lbl" for="kb-type">Reg. type</label>
                            <select id="kb-type" wire:model="regType" class="ns-input">@foreach ($regTypes as $type)<option value="{{ $type['code'] }}">{{ $type['label'] }}</option>@endforeach</select>
                            @error('regType') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="ns-pg__lbl" for="kb-num">Reg. number</label>
                            <input id="kb-num" type="text" wire:model="regNumber" class="ns-input">
                            @error('regNumber') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <button type="button" wire:click="submitKyb" wire:loading.attr="disabled" wire:target="submitKyb" class="ns-cta ns-cta--pill ns-cta--ghost" style="margin-top:16px"><x-nx.icon name="shield" /> Submit for verification</button>
            @endif
        </div>
    @endunless
</x-nx.page>
</div>
