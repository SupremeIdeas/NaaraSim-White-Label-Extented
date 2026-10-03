{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
{{-- Shared "My Connectivity" block (blueprint Section 12) — the active +
     archived, Model-grouped view of a user's eSIMs and numbers. Rendered on the
     dedicated My Lines page and reusable anywhere the same hub is wanted. Expects
     $esimsActive, $esimsArchived, $numberGroups, $numbersArchived from
     ConnectivityHub::for(). The user never sees a provider name or a cost. --}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <section>
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
            <x-icon name="package" class="h-4 w-4" /> eSIMs
        </h2>
        <div class="space-y-3">
            @forelse ($esimsActive as $esim)
                <div wire:key="esim-{{ $esim->id }}" x-data="{ setup: false, usage: false }" class="nx-card !p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex min-w-0 items-center gap-2.5">
                            @foreach (array_slice($esim->plan?->countries ?? [], 0, 3) as $iso)
                                <x-country-flag :country="$iso" class="h-4 w-6 shrink-0" />
                            @endforeach
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-[rgb(var(--nx-text))]">{{ $esim->plan?->name ?? 'eSIM' }}</span>
                                <x-model-badge :esim="true" class="mt-0.5" />
                            </span>
                        </span>
                        <x-ui.tag :variant="$esim->status === 'active' ? 'live' : (in_array($esim->status, ['pending', 'processing']) ? 'gold' : 'soon')">
                            {{ ucfirst($esim->status) }}
                        </x-ui.tag>
                    </div>

                    {{-- Data-remaining meter --}}
                    @if ($esim->data_remaining_mb !== null && $esim->plan?->data_mb)
                        @php($pct = max(0, min(100, (int) round($esim->data_remaining_mb / $esim->plan->data_mb * 100))))
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-[11px] text-[rgb(var(--nx-text-2))]">
                                <span>{{ number_format($esim->data_remaining_mb / 1024, 1) }} GB left</span>
                                <span>{{ $pct }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-[rgb(var(--nx-surface-3))]">
                                <div class="h-full rounded-full {{ $pct > 20 ? 'bg-[rgb(var(--nx-cta-b))]' : 'bg-action' }} transition-all" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if ($esim->iccid)
                        <div class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">ICCID {{ $esim->iccid }}</div>
                    @endif

                    @if ($esim->qr_code_url || $esim->lpa_string)
                        <button type="button" @click="setup = ! setup" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                            <x-icon name="wifi" class="h-3.5 w-3.5" /> <span x-text="setup ? 'Hide setup' : 'Show setup'"></span>
                        </button>

                        <div x-show="setup" x-cloak class="mt-3 space-y-3 border-t border-[rgb(var(--nx-line))] pt-3">
                            {{-- QR: the provider's image if it gave one, otherwise generated
                                 from the LPA so a scannable code always exists. --}}
                            @if ($esim->qr_code_url || $esim->lpa_string)
                                <div class="flex flex-col items-center">
                                    <img src="{{ $esim->qr_code_url ?: route('esim.qr', $esim) }}" alt="eSIM QR code" class="h-40 w-40 rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-1">
                                    <span class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Scan to install</span>
                                </div>
                            @endif

                            {{-- Manual LPA fallback — shown beside every QR (Section 32) --}}
                            <div>
                                <p class="text-xs font-semibold text-[rgb(var(--nx-text-2))]">Can’t scan? Add it manually:</p>
                                @if ($esim->lpa_string)
                                    <div class="mt-1 flex items-center gap-2" x-data="{ copied: false }">
                                        <code class="min-w-0 flex-1 break-all rounded-lg bg-[rgb(var(--nx-surface-3))] px-2 py-1.5 font-mono text-[11px] text-[rgb(var(--nx-text))]">{{ $esim->lpa_string }}</code>
                                        <button type="button" @click="navigator.clipboard.writeText(@js($esim->lpa_string)); copied = true; setTimeout(() => copied = false, 1500)"
                                                class="shrink-0 rounded-lg border border-[rgb(var(--nx-line-strong))] p-1.5 text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]" aria-label="Copy activation code">
                                            <x-icon name="copy" class="h-4 w-4" x-show="! copied" />
                                            <x-icon name="check" class="h-4 w-4 text-[rgb(var(--nx-ok))]" x-show="copied" x-cloak />
                                        </button>
                                    </div>
                                @else
                                    <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">The manual activation code will appear here once your eSIM finishes provisioning.</p>
                                @endif
                                <ul class="mt-2 space-y-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">
                                    @foreach (\App\Support\Niche\LpaActivation::steps() as $step)
                                        <li>• {{ $step }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    {{-- Usage panel (Connectivity Analytics blueprint Part A §2.6):
                         real burn-rate text + a Chart.js timeline of snapshot
                         history. Chart.js is lazy-loaded on first open — never
                         part of the main bundle for a page most visits never
                         touch. --}}
                    @php($usage = $usageByEsim[$esim->id] ?? ['burn' => null, 'timeline' => []])
                    <button type="button" @click="usage = ! usage; usage && $nextTick(() => window.NaaraUsageCharts?.mount($refs.usageChart))"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-[rgb(var(--nx-teal-ink))] hover:underline">
                        <x-icon name="signal" class="h-3.5 w-3.5" /> <span x-text="usage ? 'Hide usage' : 'Show usage'"></span>
                    </button>

                    <div x-show="usage" x-cloak class="mt-3 space-y-2 border-t border-[rgb(var(--nx-line))] pt-3">
                        @if ($usage['burn'])
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">
                                Using about
                                @if ($usage['burn']['mb_per_day'] >= 1024)
                                    {{ number_format($usage['burn']['mb_per_day'] / 1024, 2) }} GB/day
                                @else
                                    {{ number_format($usage['burn']['mb_per_day'], 0) }} MB/day
                                @endif
                                @if ($usage['burn']['days_left'] !== null)
                                    — roughly {{ $usage['burn']['days_left'] }} days of data left at this pace.
                                @endif
                            </p>
                        @endif

                        @if (count($usage['timeline']) >= 2)
                            <div class="h-32">
                                <canvas x-ref="usageChart" data-usage-chart data-usage='@json($usage['timeline'])'></canvas>
                            </div>
                        @else
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">Not enough history yet to chart usage — check back after your next reading.</p>
                        @endif
                    </div>
                </div>
            @empty
                @if ($esimsArchived->isEmpty())
                    <div class="rounded-2xl border border-dashed border-[rgb(var(--nx-line-strong))] p-8 text-center text-sm text-[rgb(var(--nx-text-2))]">
                        No eSIMs yet. <a href="{{ route('catalogue') }}" class="text-[rgb(var(--nx-teal-ink))] hover:underline">Browse plans</a>.
                    </div>
                @endif
            @endforelse
        </div>

        {{-- Archive: expired eSIMs, tucked away so the active view stays clean. --}}
        @if ($esimsArchived->isNotEmpty())
            <div x-data="{ open: false }" class="mt-3">
                <button type="button" @click="open = ! open" class="flex w-full items-center justify-between rounded-xl border border-[rgb(var(--nx-line))] px-3 py-2 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="package" class="h-3.5 w-3.5" /> Archive ({{ $esimsArchived->count() }} expired)</span>
                    <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform" ::class="open && 'rotate-90'" />
                </button>
                <div x-show="open" x-cloak class="mt-2 space-y-2">
                    @foreach ($esimsArchived as $esim)
                        <div wire:key="esim-arch-{{ $esim->id }}" class="flex items-center justify-between gap-2 rounded-xl bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate text-[rgb(var(--nx-text-2))]">{{ $esim->plan?->name ?? 'eSIM' }}</span>
                                <x-model-badge :esim="true" class="mt-0.5 opacity-70" />
                            </span>
                            <span class="shrink-0 text-xs font-medium text-[rgb(var(--nx-text-2))]">Expired</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    <section>
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
            <x-icon name="hash" class="h-4 w-4" /> Numbers
        </h2>

        @forelse ($numberGroups as $group)
            {{-- Each Model is its own tidy group — no mixing of OTP / rental / permanent. --}}
            <div class="mb-4">
                <div class="mb-2 flex items-center gap-2">
                    <x-model-badge :provider="null" :type="collect($group['model']['caps'])->contains('permanent') ? 'permanent' : (collect($group['model']['caps'])->contains('rental') ? 'rental' : 'otp')" />
                    <span class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $group['model']['tagline'] }}</span>
                </div>
                <div class="space-y-3">
                    @foreach ($group['items'] as $number)
                        <div wire:key="num-{{ $number->id }}" class="nx-card !p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2.5">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]">
                                        <x-service-icon :slug="$number->service_name ?? 'sms'" class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-[rgb(var(--nx-text))]">{{ $number->phone_number ?? ucfirst($number->service_name) }}</span>
                                        <span class="block text-xs capitalize text-[rgb(var(--nx-text-2))]">{{ $number->service_name }}</span>
                                    </span>
                                </span>
                                <div class="flex flex-col items-end gap-1">
                                    <x-ui.tag :variant="in_array($number->status, ['completed', 'active']) ? 'live' : ($number->status === 'waiting' ? 'gold' : 'soon')">
                                        {{ ucfirst($number->status) }}
                                    </x-ui.tag>
                                    @if ($number->otp_code)
                                        <span class="rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] px-2 py-0.5 font-mono text-sm font-bold tracking-widest text-[rgb(var(--nx-teal-ink))]">{{ $number->otp_code }}</span>
                                    @endif
                                </div>
                            </div>
                            {{-- Per-line quick actions for a live permanent/rental number. --}}
                            @if ($number->phone_number && in_array($number->status, ['completed', 'active'], true))
                                <div class="mt-3 flex items-center gap-2 border-t border-[rgb(var(--nx-line))] pt-3">
                                    <a href="{{ route('numbers.dialer', ['to' => $number->phone_number]) }}" wire:navigate
                                       class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-ok)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-ok))] transition hover:bg-[rgb(var(--nx-ok)/0.15)]">
                                        <x-icon name="phone" class="h-3.5 w-3.5" /> Call
                                    </a>
                                    <button type="button"
                                            wire:click="$dispatch('open-send-message', { to: '{{ $number->phone_number }}', name: '' })"
                                            class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] transition hover:bg-[rgb(var(--nx-cta-b)/0.15)]">
                                        <x-icon name="message-circle" class="h-3.5 w-3.5" /> Message
                                    </button>
                                    <a href="{{ route('numbers.messages') }}" wire:navigate
                                       class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]">
                                        Inbox <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                                    </a>
                                </div>
                            @endif

                            {{-- Naara Line billing + auto-renewal (Prompt 10): a real subscription,
                                 so this card is the only one that shows a recurring price, the next
                                 charge date, and an explicit opt-out — never a silent forever-charge. --}}
                            @if ($group['model']['key'] === 'naara_line' && in_array($number->status, ['active', 'past_due'], true))
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[rgb(var(--nx-line))] pt-3 text-xs">
                                    <span class="text-[rgb(var(--nx-text-2))]">
                                        ${{ number_format((float) $number->monthly_retail, 2) }}/mo
                                        @if ($number->auto_renew)
                                            · renews {{ $number->next_billing_date?->format('M j') }}
                                        @else
                                            · <span class="font-medium text-[rgb(var(--nx-warn))]">ends {{ $number->next_billing_date?->format('M j') }}</span>
                                        @endif
                                    </span>
                                    <button type="button" wire:click="toggleAutoRenew({{ $number->id }})"
                                            wire:loading.attr="disabled" wire:target="toggleAutoRenew({{ $number->id }})"
                                            @if ($number->auto_renew) wire:confirm="Turn off auto-renew? This number will be released on its next billing date instead of renewing." @endif
                                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 font-semibold transition disabled:opacity-60 {{ $number->auto_renew ? 'border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-bad))] hover:text-[rgb(var(--nx-bad))]' : 'border-[rgb(var(--nx-teal)/0.4)] text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.1)]' }}">
                                        <x-icon name="{{ $number->auto_renew ? 'x' : 'refresh' }}" class="h-3 w-3" />
                                        {{ $number->auto_renew ? 'Turn off auto-renew' : 'Turn auto-renew back on' }}
                                    </button>
                                </div>
                            @endif

                            {{-- Port-out / right-to-leave (Prompt 11): a customer can take a
                                 US/Canada Naara Line to another carrier and we never obstruct
                                 it. Only +1 numbers are portable via our providers (audit
                                 finding) — a +1 line gets the action; any other country gets an
                                 honest "not yet" note rather than silence, so a customer is never
                                 left wondering. --}}
                            @if ($group['model']['key'] === 'naara_line' && in_array($number->status, ['active', 'past_due'], true))
                                <div class="mt-2 border-t border-[rgb(var(--nx-line))] pt-2 text-xs">
                                    @if (! $number->isUsCanada())
                                        <p class="flex items-center gap-1.5 text-[rgb(var(--nx-text-2))]">
                                            <x-icon name="info" class="h-3.5 w-3.5 shrink-0" />
                                            Sorry — numbers in this country can’t be moved to another carrier yet.
                                        </p>
                                    @elseif ($number->port_out_requested_at)
                                        <p class="flex items-center gap-1.5 text-[rgb(var(--nx-text-2))]">
                                            <x-icon name="phone-forwarded" class="h-3.5 w-3.5 text-[rgb(var(--nx-teal-ink))]" />
                                            Port-out requested — check your email for what your new carrier needs.
                                        </p>
                                    @else
                                        <button type="button" wire:click="requestPortOut({{ $number->id }})"
                                                wire:loading.attr="disabled" wire:target="requestPortOut({{ $number->id }})"
                                                wire:confirm="Request to move this number to another carrier? We'll email you the details your new carrier needs and won't block the transfer."
                                                class="inline-flex items-center gap-1.5 font-semibold text-[rgb(var(--nx-text-2))] transition hover:text-[rgb(var(--nx-teal-ink))] disabled:opacity-60">
                                            <x-icon name="phone-forwarded" class="h-3.5 w-3.5" />
                                            Take this number to another carrier
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            @if ($numbersArchived->isEmpty())
                <div class="rounded-2xl border border-dashed border-[rgb(var(--nx-line-strong))] p-8 text-center text-sm text-[rgb(var(--nx-text-2))]">
                    No numbers yet. <a href="{{ route('numbers') }}" class="text-[rgb(var(--nx-teal-ink))] hover:underline">Get one</a>.
                </div>
            @endif
        @endforelse

        {{-- Archive: cancelled / expired / refunded numbers. --}}
        @if ($numbersArchived->isNotEmpty())
            <div x-data="{ open: false }" class="mt-1">
                <button type="button" @click="open = ! open" class="flex w-full items-center justify-between rounded-xl border border-[rgb(var(--nx-line))] px-3 py-2 text-xs font-medium text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-2))]">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="hash" class="h-3.5 w-3.5" /> Archive ({{ $numbersArchived->count() }})</span>
                    <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform" ::class="open && 'rotate-90'" />
                </button>
                <div x-show="open" x-cloak class="mt-2 space-y-2">
                    @foreach ($numbersArchived as $number)
                        <div wire:key="num-arch-{{ $number->id }}" class="flex items-center justify-between gap-2 rounded-xl bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate text-[rgb(var(--nx-text-2))]">{{ $number->phone_number ?? ucfirst($number->service_name) }}</span>
                                <span class="mt-0.5 flex items-center gap-1.5">
                                    <span class="text-xs capitalize text-[rgb(var(--nx-text-2))]">{{ $number->service_name }}</span>
                                    <x-model-badge :type="$number->type" :provider="$number->provider" class="opacity-70" />
                                </span>
                            </span>
                            <span class="shrink-0 text-xs font-medium capitalize text-[rgb(var(--nx-text-2))]">{{ $number->status }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</div>
