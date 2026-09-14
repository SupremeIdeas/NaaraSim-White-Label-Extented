<div class="mx-auto max-w-2xl">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">White-label license</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Run NaaraSim under your own brand. Pick a plan, pay to activate, and upgrade to Extended whenever you're ready.</p>
    </div>

    @if ($error)
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">{{ $error }}</div>
    @endif

    @unless ($this->isV2)
        {{-- Visible-but-locked: the same pattern the Merchant-V2 gate itself uses. --}}
        <div class="rounded-2xl border border-slate-200 nx-glass-tile p-6 text-center dark:border-white/10">
            <x-icon name="lock" class="mx-auto mb-3 h-8 w-8 text-slate-400" />
            <p class="font-semibold text-slate-900 dark:text-white">Merchant V2 required</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upgrade to Merchant V2 to request your own white-label license.</p>
            <a href="{{ route('merchant.dashboard') }}" class="mt-4 inline-flex items-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark">Go to storefront</a>
        </div>
    @else
        @if ($this->instance)
            {{-- An existing request/instance: show its status and the relevant action. --}}
            <div class="rounded-2xl border border-slate-200 nx-glass-tile p-5 dark:border-white/10">
                <div class="mb-3 flex items-center justify-between">
                    <p class="font-semibold text-slate-900 dark:text-white">{{ $this->instance->licensePlan?->name ?? 'Your license' }}</p>
                    @php
                        $tone = match ($this->instance->status) {
                            'active' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                            'suspended' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                            'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                            default => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
                        };
                    @endphp
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tone }}">{{ ucfirst($this->instance->status) }}</span>
                </div>

                @if ($this->instance->status === 'pending' && $this->instance->price_usd === null)
                    <p class="text-sm text-slate-500 dark:text-slate-400">Your request is being reviewed. An admin will confirm your price shortly.</p>
                @elseif ($this->instance->status === 'pending' && $this->instance->price_usd !== null)
                    <p class="mb-3 text-sm text-slate-600 dark:text-slate-300">Price confirmed: <span class="font-semibold text-slate-900 dark:text-white">${{ number_format((float) $this->instance->price_usd, 2) }}</span></p>
                    <button type="button" wire:click="payNow" wire:loading.attr="disabled" wire:target="payNow"
                        class="w-full rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="payNow">Pay ${{ number_format((float) $this->instance->price_usd, 2) }} to activate</span>
                        <span wire:loading wire:target="payNow">Processing…</span>
                    </button>
                @elseif ($this->instance->status === 'active')
                    <p class="text-sm text-slate-500 dark:text-slate-400">Tier: <span class="font-medium text-slate-700 dark:text-slate-200">{{ ucfirst($this->instance->tier) }}</span></p>

                    @if ($this->balanceOwed !== null && $this->balanceOwed > 0)
                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                            <p class="mb-2 text-sm text-slate-600 dark:text-slate-300">Complete the remaining <span class="font-semibold text-slate-900 dark:text-white">${{ number_format($this->balanceOwed, 2) }}</span> to unlock Extended — no downtime, your fork keeps its existing token.</p>
                            <button type="button" wire:click="payBalance" wire:loading.attr="disabled" wire:target="payBalance"
                                class="w-full rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark disabled:opacity-60">
                                <span wire:loading.remove wire:target="payBalance">Pay ${{ number_format($this->balanceOwed, 2) }} to upgrade</span>
                                <span wire:loading wire:target="payBalance">Processing…</span>
                            </button>
                        </div>
                    @endif
                @elseif ($this->instance->status === 'rejected')
                    <p class="text-sm text-slate-500 dark:text-slate-400">This request was not approved.</p>
                @endif
            </div>
        @else
            {{-- Prompt 21-EXT §2.1 — swipeable plan carousel. Each card falls back
                 to a tier-tinted gradient (richer for Extended/Extended V2) until
                 an admin uploads real cover art, so nothing ships as a bare box. --}}
            <div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-3" style="scroll-padding-inline:1rem;">
                @foreach ($this->plans as $plan)
                    @php
                        $open = \App\Models\WhiteLabelLicensePlan::resellOpenForTier($plan->tier);
                        $balance = \App\Models\WhiteLabelLicensePlan::balanceToExtended($plan);
                        $isV2Plan = $plan->support_level === \App\Models\WhiteLabelLicensePlan::SUPPORT_PRIORITY;
                        $gradient = $isV2Plan
                            ? 'from-[#0D1B2A] to-[#3a2f0a]'
                            : ($plan->tier === \App\Models\WhiteLabelInstance::TIER_EXTENDED ? 'from-[#0A6E6E] to-[#7a5b0c]' : 'from-[#0A6E6E] to-[#0A6E6E]/60');
                    @endphp
                    <div wire:key="plan-card-{{ $plan->id }}" class="w-[80%] shrink-0 snap-center overflow-hidden rounded-2xl border border-slate-200 nx-glass-tile sm:w-64 dark:border-white/10 {{ $open ? '' : 'opacity-60' }}">
                        <div class="relative flex h-28 items-end bg-gradient-to-br {{ $gradient }} p-3"
                             @if ($plan->cover_image_url) style="background-image:url('{{ $plan->cover_image_url }}');background-size:cover;background-position:center;" @endif>
                            <span class="inline-flex items-center gap-1 rounded-full bg-black/30 px-2 py-0.5 text-[11px] font-semibold text-white backdrop-blur-sm">
                                <x-icon name="{{ $isV2Plan ? 'shield-check' : ($plan->tier === \App\Models\WhiteLabelInstance::TIER_EXTENDED ? 'star' : 'zap') }}" class="h-3 w-3" />
                                {{ $plan->name }}
                            </span>
                            @unless ($open)
                                <span class="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-amber-500/90 px-2 py-0.5 text-[11px] font-semibold text-white">
                                    <x-icon name="lock" class="h-3 w-3" /> Closed
                                </span>
                            @endunless
                        </div>
                        <div class="p-4">
                            <div class="mb-1 flex items-baseline justify-between">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $plan->name }}</p>
                                <p class="text-lg font-bold text-slate-900 dark:text-white">${{ number_format((float) $plan->price_usd, 0) }}</p>
                            </div>
                            <p class="mb-2 text-xs text-slate-400">{{ $plan->tagline }}</p>
                            <ul class="mb-3 space-y-1">
                                @foreach (array_slice($plan->features ?? [], 0, 3) as $feature)
                                    <li class="flex items-start gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                                        <x-icon name="check" class="mt-0.5 h-3 w-3 shrink-0 text-primary" /> {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                            @if ($balance !== null)
                                <p class="mb-3 text-[11px] text-slate-400">Pay ${{ number_format((float) $plan->price_usd, 0) }} now, complete ${{ number_format($balance, 0) }} later to unlock Extended.</p>
                            @endif
                            @if (! $open)
                                <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Temporarily closed for new requests — check back later.</p>
                            @else
                                <button type="button" wire:click="requestLicense({{ $plan->id }})" class="w-full rounded-xl border border-primary px-3 py-2 text-sm font-semibold text-primary transition hover:bg-primary/5">Request this plan</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Plan comparison table --}}
            <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 nx-glass-tile dark:border-white/10">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-white/10">
                            <th class="p-3 font-medium text-slate-400"></th>
                            @foreach ($this->plans as $plan)
                                <th class="p-3 text-center font-semibold text-slate-900 dark:text-white">{{ $plan->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <tr>
                            <td class="p-3 text-slate-500 dark:text-slate-400">Price</td>
                            @foreach ($this->plans as $plan)
                                <td class="p-3 text-center font-semibold text-slate-900 dark:text-white">${{ number_format((float) $plan->price_usd, 0) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="p-3 text-slate-500 dark:text-slate-400">Support</td>
                            @foreach ($this->plans as $plan)
                                <td class="p-3 text-center text-slate-600 dark:text-slate-300">{{ ucfirst($plan->support_level) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="p-3 text-slate-500 dark:text-slate-400">Full platform, unlocked day one</td>
                            @foreach ($this->plans as $plan)
                                <td class="p-3 text-center">
                                    @if ($plan->tier === \App\Models\WhiteLabelInstance::TIER_EXTENDED)
                                        <x-icon name="check" class="mx-auto h-4 w-4 text-primary" />
                                    @else
                                        <x-icon name="x" class="mx-auto h-4 w-4 text-slate-300 dark:text-slate-600" />
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="p-3 text-slate-500 dark:text-slate-400">Pay balance later to reach Extended</td>
                            @foreach ($this->plans as $plan)
                                <td class="p-3 text-center">
                                    @if ($plan->tier === \App\Models\WhiteLabelInstance::TIER_NORMAL)
                                        <x-icon name="check" class="mx-auto h-4 w-4 text-primary" />
                                    @else
                                        <x-icon name="x" class="mx-auto h-4 w-4 text-slate-300 dark:text-slate-600" />
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10">
                <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">Hosting preference</label>
                <select wire:model="hostingPreference" class="mb-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                    <option value="supreme_ideas_server">Host on Supreme Ideas' server</option>
                    <option value="own_server">Host on my own server</option>
                </select>
                <label class="flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <input type="checkbox" wire:model="disclaimerAcknowledged" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                    I understand hosting responsibilities and support boundaries for my chosen option.
                </label>
            </div>
        @endif
    @endif
</div>
