<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Become a merchant</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Run your own co-branded storefront on NaaraSim — resell eSIMs and numbers to
        your customers and earn on every sale, settled straight to your bank. You
        never pay for refills: NaaraSim fulfils every order, you're the storefront.
    </p>

    {{-- V1 vs V2 plan comparison (BUILD-4 §3.1), live from MerchantSettings so a
         prospective merchant sees what each tier unlocks and its price up front. --}}
    <div class="mt-6 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
            <div class="flex items-center justify-between">
                <p class="font-bold text-slate-900 dark:text-slate-100">Merchant V1</p>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500 dark:bg-white/10 dark:text-slate-300">Standard</span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your own co-branded storefront. Earn a <strong>{{ rtrim(rtrim(number_format($pricing['margin'], 2), '0'), '.') }}%</strong> reseller margin on every sale, settled to your bank.</p>
            <ul class="mt-3 space-y-1.5 text-sm text-slate-600 dark:text-slate-300">
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> Co-branded storefront + invite link</li>
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> Earnings on every referred sale</li>
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> Unlock free (spend/referrals) or fast-route fee</li>
            </ul>
        </div>
        <div class="rounded-2xl border border-primary/30 bg-primary/[0.04] p-5 shadow-sm dark:border-primary/40 dark:bg-primary/10">
            <div class="flex items-center justify-between">
                <p class="font-bold text-slate-900 dark:text-slate-100">Merchant V2</p>
                <span class="rounded-full bg-primary/15 px-2 py-0.5 text-xs font-semibold text-primary dark:text-teal-300">${{ rtrim(rtrim(number_format($pricing['upgradePrice'], 2), '0'), '.') }} one-time</span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Everything in V1, plus manage eSIMs & numbers for clients who never sign in — and first-class API access.</p>
            <ul class="mt-3 space-y-1.5 text-sm text-slate-600 dark:text-slate-300">
                <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> Everything in V1</li>
                <li class="flex items-center gap-2"><x-icon name="users" class="h-4 w-4 text-primary" /> Client management (no-login customers)</li>
                <li class="flex items-center gap-2"><x-icon name="key" class="h-4 w-4 text-primary" /> Developer portal &amp; API keys</li>
            </ul>
            <p class="mt-3 text-xs text-slate-400">Upgrade any time from your Merchant dashboard once you're V1.</p>
        </div>
    </div>

    @unless ($programmeOpen)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
            The merchant programme isn't open yet. Please check back soon.
        </div>
    @else
        @php($unlocked = $eligibility && $eligibility['eligible'])
        {{-- Stepper — two steps now; identity verification is handled later, at
             payout time (BUILD-4 §1), so it isn't a front step here. --}}
        <ol class="mt-6 flex items-center gap-2 text-xs font-semibold">
            <li class="flex items-center gap-2 {{ $unlocked ? 'text-primary' : 'text-slate-900 dark:text-slate-100' }}">
                <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $unlocked ? 'bg-primary text-white' : 'bg-slate-200 text-slate-600 dark:bg-[#243352] dark:text-slate-300' }}">1</span> Unlock
            </li>
            <span class="h-px w-6 bg-slate-200 dark:bg-[#2D4060]"></span>
            <li class="flex items-center gap-2 {{ $merchant ? 'text-primary' : ($unlocked ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400') }}">
                <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $merchant ? 'bg-primary text-white' : 'bg-slate-200 text-slate-500 dark:bg-[#243352]' }}">2</span> Apply
            </li>
        </ol>

        {{-- Deferred-verification note (§1): no KYB up front. --}}
        <div class="mt-4 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-inner-dark)] dark:text-slate-400">
            <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
            <span>No verification needed to start. You'll confirm your identity later — only when you first cash out your earnings.</span>
        </div>

        @if ($merchant && $merchant->status === 'active')
            <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-5 dark:border-green-900/50 dark:bg-green-950/40">
                <p class="font-semibold text-green-800 dark:text-green-300">You're a merchant</p>
                <p class="mt-1 text-sm text-green-700/80 dark:text-green-400/80">Your storefront <strong>{{ $merchant->business_name }}</strong> is live. Manage it from your Merchant area.</p>
            </div>
        @elseif ($merchant && $merchant->status === 'pending')
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/50 dark:bg-amber-950/40">
                <p class="font-semibold text-amber-800 dark:text-amber-300">Application under review</p>
                <p class="mt-1 text-sm text-amber-700/80 dark:text-amber-400/80">We're reviewing <strong>{{ $merchant->business_name }}</strong>. You'll be notified once it's approved.</p>
            </div>
        @elseif (! $unlocked)
            {{-- Stage 1: unlock membership — meet ANY one path (§1: no KYB gate). --}}
            <div class="mt-6 space-y-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">Unlock membership by meeting <strong>any one</strong> of these:</p>
                @if ($error)
                    <div class="rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $error }}</div>
                @endif

                {{-- Path 1: spend --}}
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $eligibility['spend']['met'] ? 'bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-300' : 'bg-slate-100 text-slate-400 dark:bg-white/5' }}">
                        <x-icon name="{{ $eligibility['spend']['met'] ? 'badge-check' : 'credit-card' }}" class="h-4 w-4" />
                    </span>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Spend ${{ number_format($eligibility['spend']['required'], 0) }} as a user</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">You've transacted ${{ number_format($eligibility['spend']['current'], 2) }} so far.</p>
                    </div>
                </div>

                {{-- Path 2: fast-route enrollment --}}
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $eligibility['enrollment']['met'] ? 'bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-300' : 'bg-accent/15 text-accent-dark dark:text-accent' }}">
                        <x-icon name="{{ $eligibility['enrollment']['met'] ? 'badge-check' : 'zap' }}" class="h-4 w-4" />
                    </span>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Fast route — ${{ number_format($eligibility['enrollment']['fee'], 0) }} one-time enrollment</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Paid once from your wallet. Skip the thresholds and unlock instantly.</p>
                    </div>
                    @unless ($eligibility['enrollment']['met'])
                        <button type="button" wire:click="payEnrollment" wire:loading.attr="disabled" wire:target="payEnrollment"
                                wire:confirm="Pay the ${{ number_format($eligibility['enrollment']['fee'], 2) }} enrollment fee from your wallet?"
                                class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark disabled:opacity-60">Pay &amp; unlock</button>
                    @endunless
                </div>

                {{-- Path 3: referrals --}}
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $eligibility['referrals']['met'] ? 'bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-300' : 'bg-slate-100 text-slate-400 dark:bg-white/5' }}">
                        <x-icon name="{{ $eligibility['referrals']['met'] ? 'badge-check' : 'link' }}" class="h-4 w-4" />
                    </span>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Refer {{ number_format($eligibility['referrals']['required']) }} users</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">You've referred {{ number_format($eligibility['referrals']['current']) }} so far. <a href="{{ route('referrals') }}" class="text-primary hover:underline">Get your link</a></p>
                    </div>
                </div>
            </div>
        @else
            {{-- Stage 3: application --}}
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Set up your storefront</p>
                <p class="mt-0.5 text-xs text-green-600 dark:text-green-400">Membership unlocked — one last step.</p>
                @if ($error)
                    <div class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $error }}</div>
                @endif
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Business name</label>
                        <input type="text" wire:model="businessName" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        @error('businessName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Brand colour</label>
                        <input type="color" wire:model="brandColor" class="h-10 w-full rounded-lg border border-slate-300 bg-white dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352]">
                    </div>
                </div>
                <button type="button" wire:click="apply" wire:loading.attr="disabled" wire:target="apply"
                        class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                    <x-icon name="check" class="h-4 w-4" /> Submit application
                </button>
            </div>
        @endif

        {{-- OPTIONAL global business verification (BUILD-4 §2.1). Not a gate —
             a merchant can verify now or later at payout. Data-driven country +
             registration-type selects (worldwide), replacing the old hardcoded
             4-country / CAC-TIN form. --}}
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Business verification <span class="font-normal text-slate-400">(optional now)</span></p>
                @if ($kybVerified)
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300"><x-icon name="badge-check" class="h-3.5 w-3.5" /> Verified</span>
                @endif
            </div>

            @if ($kybVerified)
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Your business is verified — larger payouts will clear without extra checks.</p>
            @elseif ($kybAttempt && $kybAttempt->status === 'pending')
                <div class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Your business details are being verified — this usually takes a little while.</div>
            @else
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">You can start selling right away. Verify your business now, or we'll ask when you first cash out (required for larger payouts).</p>
                @if ($kybAttempt && $kybAttempt->status === 'rejected')
                    <div class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">We couldn't verify your business{{ $kybAttempt->reason ? ': '.$kybAttempt->reason : '.' }} Please try again.</div>
                @endif
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Country</label>
                        <select wire:model.live="country" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                            @foreach ($countries as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Reg. type</label>
                        <select wire:model="regType" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                            @foreach ($regTypes as $type)
                                <option value="{{ $type['code'] }}">{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                        @error('regType') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Reg. number</label>
                        <input type="text" wire:model="regNumber" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100">
                        @error('regNumber') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
                <button type="button" wire:click="submitKyb" wire:loading.attr="disabled" wire:target="submitKyb"
                        class="mt-4 flex items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 px-4 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary/10 disabled:opacity-60 dark:border-primary/40 dark:text-teal-300">
                    <x-icon name="shield" class="h-4 w-4" /> Submit for verification
                </button>
            @endif
        </div>
    @endunless
</div>
