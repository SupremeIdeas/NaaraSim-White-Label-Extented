<div class="mx-auto max-w-6xl px-4 py-16">
    {{-- Header --}}
    <div class="mx-auto max-w-2xl text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">Transparent Pricing</p>
        <h1 class="mt-3 font-display text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Plans That Make Sense</h1>
        <p class="mx-auto mt-4 leading-relaxed text-slate-600 dark:text-slate-300">
            No monthly fees. No contracts. Pay for exactly what you need — a short pass or a full month.
            Every plan includes instant activation and 24/7 support.
        </p>
        @if ($mode === 'estimate')
            <p class="mx-auto mt-5 inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 text-xs font-medium text-primary dark:bg-primary/20 dark:text-teal-300">
                <x-icon name="signal" class="h-3.5 w-3.5" /> Indicative “from” pricing — live plans and exact prices appear here the moment our catalogue is connected.
            </p>
        @endif
    </div>

    {{-- LIVE plans --}}
    @if ($mode === 'live')
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($plans as $plan)
                @php($ref = (string) $plan->id)
                <div wire:key="plan-{{ $plan->id }}" @class([
                    'relative flex flex-col rounded-3xl border bg-white p-6 transition dark:bg-[var(--brand-card-dark)]',
                    'border-primary shadow-xl shadow-primary/10 dark:border-primary' => $plan->is_featured,
                    'border-slate-200 dark:border-[var(--brand-card-border-dark)]' => ! $plan->is_featured,
                ])>
                    @if ($plan->is_featured)
                        <span class="absolute -top-3 left-6 rounded-full bg-primary px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white">Most popular</span>
                    @endif
                    <h3 class="font-display text-lg font-bold text-slate-900 dark:text-white">{{ $plan->name }}</h3>
                    <div class="mt-3 flex items-baseline gap-1">
                        <span class="font-display text-3xl font-bold text-slate-900 dark:text-white">{{ $plan->display_price['usd'] }}</span>
                    </div>
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $plan->display_price['ngn'] }}</p>

                    <ul class="mt-5 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> {{ $plan->data_mb ? number_format($plan->data_mb / 1024, 1).' GB data' : 'Data included' }}</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> {{ $plan->validity_days ? $plan->validity_days.' days validity' : 'Flexible validity' }}</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> {{ is_array($plan->countries) ? count($plan->countries).' '.\Illuminate\Support\Str::plural('country', count($plan->countries)) : 'Wide coverage' }}</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> Instant activation</li>
                    </ul>

                    <button type="button" wire:click="explain('{{ $ref }}')" wire:loading.attr="disabled" wire:target="explain('{{ $ref }}')"
                            class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline dark:text-teal-300">
                        <x-icon name="message-circle" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="explain('{{ $ref }}')">What does this mean for me?</span>
                        <span wire:loading wire:target="explain('{{ $ref }}')">Thinking…</span>
                    </button>

                    @include('livewire.partials.pricing-explainer', ['ref' => $ref])

                    <a href="{{ auth()->check() ? route('checkout', $plan) : route('register') }}"
                       class="nx-btn {{ $plan->is_featured ? 'nx-btn--primary' : 'nx-btn--ghost' }} mt-5 w-full justify-center">
                        {{ auth()->check() ? 'Choose plan' : 'Get started' }}
                    </a>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
            Looking for something specific? <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="font-semibold text-primary hover:underline">Browse the full catalogue</a>.
        </p>
    @else
        {{-- ESTIMATE tiers --}}
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($tiers as $i => $tier)
                @php($ref = 'e'.$i)
                <div wire:key="tier-{{ $i }}" @class([
                    'relative flex flex-col rounded-3xl border bg-white p-6 dark:bg-[var(--brand-card-dark)]',
                    'border-primary shadow-xl shadow-primary/10 dark:border-primary' => $i === 1,
                    'border-slate-200 dark:border-[var(--brand-card-border-dark)]' => $i !== 1,
                ])>
                    @if ($i === 1)
                        <span class="absolute -top-3 left-6 rounded-full bg-primary px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white">Most popular</span>
                    @endif
                    <h3 class="font-display text-lg font-bold text-slate-900 dark:text-white">{{ $tier['name'] }}</h3>
                    <div class="mt-3 flex items-baseline gap-1">
                        <span class="text-xs font-medium text-slate-400">from</span>
                        <span class="font-display text-3xl font-bold text-slate-900 dark:text-white">${{ number_format((float) $tier['from_usd'], 2) }}</span>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $tier['blurb'] }}</p>

                    <ul class="mt-5 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> {{ $tier['data'] }} data</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> {{ $tier['validity'] }} validity</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> 190+ countries</li>
                        <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> Instant activation</li>
                    </ul>

                    <button type="button" wire:click="explain('{{ $ref }}')" wire:loading.attr="disabled" wire:target="explain('{{ $ref }}')"
                            class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline dark:text-teal-300">
                        <x-icon name="message-circle" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="explain('{{ $ref }}')">What does this mean for me?</span>
                        <span wire:loading wire:target="explain('{{ $ref }}')">Thinking…</span>
                    </button>

                    @include('livewire.partials.pricing-explainer', ['ref' => $ref])

                    <a href="{{ route('register') }}" class="nx-btn {{ $i === 1 ? 'nx-btn--primary' : 'nx-btn--ghost' }} mt-5 w-full justify-center">Get started</a>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-center text-xs text-slate-400 dark:text-slate-500">
            Estimates only, for guidance. Final prices are set live at checkout the moment our catalogue is connected — you always pay the price shown before you confirm.
        </p>
    @endif

    {{-- Trust line (Prompt 10 §1): the numbers/OTP refund guarantee, stated
         truthfully — this page is eSIM-plan-focused above, but the platform
         sells numbers too, so the promise belongs here as a real, standing
         line item, not buried only in the purchase modal. Timeout pulled
         from PollSmsOtpJob's own constant, never hardcoded. --}}
    <div class="mt-10 flex items-center justify-center gap-2 text-center text-sm text-slate-500 dark:text-slate-400">
        <x-icon name="shield-check" class="h-4 w-4 shrink-0 text-primary" />
        Verification numbers are covered too: no code within {{ \App\Jobs\PollSmsOtpJob::TIMEOUT_MINUTES }} minutes
        → automatic refund to your wallet, no ticket required.
        <a href="{{ route('refund-policy') }}" class="font-semibold text-primary hover:underline">Refund &amp; reliability policy</a>
    </div>

    {{-- Not sure? -> data estimator --}}
    <div class="mt-14 rounded-3xl bg-gradient-to-br from-primary via-primary-dark to-navy p-8 text-center text-white sm:p-10">
        <h2 class="font-display text-2xl font-bold">Not sure how much data you need?</h2>
        <p class="mx-auto mt-2 max-w-xl text-sm text-teal-100/90">Tell us your trip and how you use your phone — we’ll estimate the right plan size so you never over- or under-buy.</p>
        <a href="{{ auth()->check() ? route('data-estimator') : route('register') }}" class="nx-btn nx-btn--gold mt-6 !px-8 !py-3">Try the data estimator</a>
    </div>
</div>
