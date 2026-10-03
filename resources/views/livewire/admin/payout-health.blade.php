@php
    $card = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]';
    $inp = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100';
    $btn = 'rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-dark disabled:opacity-50';
    $btn2 = 'rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5';
@endphp
<div class="mx-auto max-w-4xl space-y-6 pb-12">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Payout health &amp; operations</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Proof the books are right, and the few things that need a human decision. Every action needs a note and is logged.</p>
        </div>
        <a href="{{ route('admin.payouts', ['adminGateway' => request()->route('adminGateway')]) }}" wire:navigate class="text-sm font-semibold text-primary hover:underline">Back to Payouts</a>
    </div>

    @if ($message)<div class="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $message }}</div>@endif
    @if ($error)<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300" role="alert">{{ $error }}</div>@endif

    {{-- Automation status --}}
    <section class="{{ $card }}" aria-labelledby="h-auto">
        <div class="flex items-start gap-3">
            <x-icon :name="$automation['automatic'] ? 'check' : 'alert-triangle'" class="mt-0.5 h-5 w-5 shrink-0 {{ $automation['automatic'] ? 'text-emerald-500' : 'text-amber-500' }}" />
            <div class="min-w-0">
                <h2 id="h-auto" class="text-base font-bold text-slate-900 dark:text-slate-100">
                    {{ $automation['automatic'] ? 'Payouts are automatic' : 'Payouts are NOT fully automatic yet' }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if ($automation['automatic']) Low-risk requests on {{ implode(', ', array_map('ucfirst', $automation['rails'])) }} are approved and sent by the system. Unusual or large ones still wait for a person.
                    @else Fix the red items below and supported payouts will run by themselves. @endif
                </p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($automation['checks'] as $c)
                        <li class="flex items-start gap-2">
                            <x-icon :name="$c['ok'] ? 'check' : 'x'" class="mt-0.5 h-4 w-4 shrink-0 {{ $c['ok'] ? 'text-emerald-500' : 'text-red-500' }}" />
                            <span class="text-slate-800 dark:text-slate-200">{{ $c['label'] }}@if ($c['fix']) <span class="block text-xs text-red-700 dark:text-red-300">{{ $c['fix'] }}</span>@endif</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Rails delivered later by the Platform Updater --}}
    <section class="{{ $card }}" aria-labelledby="h-rails">
        <h2 id="h-rails" class="text-base font-bold text-slate-900 dark:text-slate-100">Rails coming through the Platform Updater</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">These need a provider account first. Until their package is installed they show as "coming soon" to users and nothing else is affected: Paystack, Flutterwave and Stripe keep paying out as normal.</p>
        <ul class="mt-3 divide-y divide-slate-100 text-sm dark:divide-white/10">
            @foreach ($rails as $r)
                <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                    <div class="min-w-0">
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $r['label'] }}</span>
                        @if ($r['version'])<span class="ml-1 text-xs text-slate-500 dark:text-slate-400">v{{ $r['version'] }}</span>@endif
                        @if ($r['note'])<span class="block text-xs text-slate-500 dark:text-slate-400">{{ $r['note'] }}</span>@endif
                    </div>
                    @php
                        $badge = match ($r['status']) {
                            'installed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                            'problem' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
                            'disabled' => 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
                            default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                        };
                    @endphp
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge }}">{{ ['installed' => 'Installed', 'problem' => 'Needs attention', 'disabled' => 'Switched off', 'coming_soon' => 'Coming soon'][$r['status']] ?? $r['status'] }}</span>
                </li>
            @endforeach
        </ul>
        @if ($railProblems !== [])
            <p class="mt-2 text-xs text-red-700 dark:text-red-300">Skipped extensions: {{ collect($railProblems)->map(fn ($p) => $p['slug'].' ('.$p['message'].')')->implode('; ') }}</p>
        @endif
    </section>

    {{-- Money invariants --}}
    <section class="{{ $card }}" aria-labelledby="h-inv">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 id="h-inv" class="text-base font-bold text-slate-900 dark:text-slate-100">Money checks</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Runs every night. Read-only: it proves the ledgers agree with every payout, and never changes anything.</p>
            </div>
            @if ($canReview)<button type="button" wire:click="runInvariants" wire:loading.attr="disabled" wire:target="runInvariants" class="{{ $btn }}">Run now</button>@endif
        </div>
        @if ($run)
            <p class="mt-3 text-sm {{ $run->status === 'ok' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">
                {{ $run->status === 'ok' ? 'All checks passed' : $run->violation_count.' problem(s) found' }} · {{ $run->finished_at?->diffForHumans() ?? 'running' }} · {{ $run->triggered_by }}
            </p>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($run->results ?? [] as $r)
                    <li class="flex items-start gap-2">
                        <x-icon :name="$r['ok'] ? 'check' : 'alert-triangle'" class="mt-0.5 h-4 w-4 shrink-0 {{ $r['ok'] ? 'text-emerald-500' : 'text-red-500' }}" />
                        <div class="min-w-0">
                            <span class="text-slate-800 dark:text-slate-200">{{ $r['name'] }}</span>
                            @unless ($r['ok'])
                                <span class="text-xs text-red-600 dark:text-red-300">({{ $r['count'] }})</span>
                                <ul class="mt-1 space-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    @foreach (array_slice($r['offenders'], 0, 8) as $o)<li class="font-mono">{{ json_encode($o) }}</li>@endforeach
                                </ul>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-sm text-slate-500">No run yet. It runs nightly, or press Run now.</p>
        @endif
    </section>

    {{-- Needs a decision --}}
    @if ($canReview)
        <section class="{{ $card }}" aria-labelledby="h-act">
            <h2 id="h-act" class="text-base font-bold text-slate-900 dark:text-slate-100">Needs your decision</h2>
            @if ($held->isEmpty() && $unknown->isEmpty() && $manual->isEmpty())
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Nothing waiting.</p>
            @endif

            @foreach ($held as $r)
                <div class="mt-4 rounded-xl border border-amber-200 p-3 dark:border-amber-500/30" wire:key="held-{{ $r->id }}">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">#{{ $r->id }} · {{ number_format((float) $r->amount, 2) }} {{ $r->currency }} · {{ $r->user?->email }}</p>
                    <p class="text-xs text-amber-700 dark:text-amber-300">Not sent: the payout account was {{ $r->hold_reason === 'account_deleted_after_request' ? 'removed' : 'changed' }} after the request was made. Check with the user first.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <input type="text" wire:model="notes.{{ $r->id }}" placeholder="Note (required)" class="{{ $inp }} sm:max-w-xs" aria-label="Note for request {{ $r->id }}">
                        @if ($r->hold_reason === 'account_changed_after_request')<button type="button" wire:click="acceptDestination({{ $r->id }})" wire:loading.attr="disabled" class="{{ $btn }}">It's genuine — accept new account</button>@endif
                        <button type="button" wire:click="rejectHeld({{ $r->id }})" wire:confirm="Decline this payout and return the funds?" wire:loading.attr="disabled" class="{{ $btn2 }}">Decline &amp; return funds</button>
                    </div>
                </div>
            @endforeach

            @foreach ($unknown as $r)
                <div class="mt-4 rounded-xl border border-red-200 p-3 dark:border-red-500/30" wire:key="unk-{{ $r->id }}">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">#{{ $r->id }} · {{ number_format((float) $r->amount, 2) }} {{ $r->currency }} · {{ ucfirst($r->provider) }}</p>
                    <p class="text-xs text-red-700 dark:text-red-300">We sent this but never heard the result and the provider can't confirm it. Check the provider's own dashboard, then say what happened. Do not refund blindly.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <input type="text" wire:model="notes.{{ $r->id }}" placeholder="Evidence (required)" class="{{ $inp }} sm:max-w-xs" aria-label="Evidence for request {{ $r->id }}">
                        <button type="button" wire:click="resolveUnknown({{ $r->id }}, 'paid')" wire:confirm="Provider shows it was PAID?" class="{{ $btn }}">It was paid</button>
                        <button type="button" wire:click="resolveUnknown({{ $r->id }}, 'failed')" wire:confirm="Provider shows it did NOT go out? The funds will be returned." class="{{ $btn2 }}">It did not go out</button>
                    </div>
                </div>
            @endforeach

            @foreach ($manual as $r)
                <div class="mt-4 rounded-xl border border-sky-200 p-3 dark:border-sky-500/30" wire:key="man-{{ $r->id }}">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Pay by hand · #{{ $r->id }} · {{ number_format((float) $r->amount, 2) }} {{ $r->currency }} · {{ $r->user?->email }}</p>
                    <p class="text-xs text-sky-700 dark:text-sky-300">Pay this person from your own bank/app, then record the proof so the books stay right.</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <input type="text" wire:model="proofs.{{ $r->id }}" placeholder="Proof reference (transfer / receipt no.)" class="{{ $inp }}" aria-label="Proof reference for request {{ $r->id }}">
                        <input type="text" wire:model="notes.{{ $r->id }}" placeholder="Note (required)" class="{{ $inp }}" aria-label="Note for request {{ $r->id }}">
                        <button type="button" wire:click="recordManual({{ $r->id }})" wire:loading.attr="disabled" class="{{ $btn }}">Record payment</button>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="{{ $card }}" aria-labelledby="h-ret">
            <h2 id="h-ret" class="text-base font-bold text-slate-900 dark:text-slate-100">A delivered payout came back</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">If the bank or provider returned money after we marked it delivered: enter the payout number and the evidence. The money goes back to the user's balance once, and their payout account is flagged.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-[120px_1fr_auto]">
                <input type="number" wire:model="returnedId" placeholder="Payout #" class="{{ $inp }}" aria-label="Payout number">
                <input type="text" wire:model="returnedEvidence" placeholder="Evidence (bank notice, provider message…)" class="{{ $inp }}" aria-label="Evidence">
                <button type="button" wire:click="markReturned" wire:confirm="Mark this payout as returned and re-credit the user?" wire:loading.attr="disabled" class="{{ $btn }}">Mark returned</button>
            </div>
        </section>
    @endif

    {{-- Frozen payees --}}
    <section class="{{ $card }}" aria-labelledby="h-frz">
        <h2 id="h-frz" class="text-base font-bold text-slate-900 dark:text-slate-100">Paused payees</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">People who pressed "This wasn't me" or were paused by an admin. They cannot request payouts until a super admin clears them.</p>
        @forelse ($freezes as $f)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-sm dark:border-white/5" wire:key="frz-{{ $f->id }}">
                <span class="text-slate-800 dark:text-slate-200">User #{{ $f->user_id }} · {{ $f->reason }} · {{ $f->frozen_at->diffForHumans() }}</span>
                @if ($isSuper)
                    <span class="flex items-center gap-2">
                        <input type="text" wire:model="releaseNote" placeholder="Why is it safe? (required)" class="{{ $inp }} w-56" aria-label="Release note">
                        <button type="button" wire:click="releaseFreeze({{ $f->user_id }})" wire:confirm="Re-enable payouts for this payee?" class="{{ $btn2 }}">Clear</button>
                    </span>
                @endif
            </div>
        @empty
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No one is paused.</p>
        @endforelse
    </section>

    {{-- Settlement reconciliation + accounting --}}
    @if ($canFinance)
        <section class="{{ $card }}" aria-labelledby="h-rec">
            <h2 id="h-rec" class="text-base font-bold text-slate-900 dark:text-slate-100">Match against a provider statement</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Upload the provider's statement (CSV with <code>reference, amount, currency</code>, optional <code>fee</code>). It only flags differences — nothing is changed automatically.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-4">
                <input type="text" wire:model="reconProvider" class="{{ $inp }}" aria-label="Provider" placeholder="paystack">
                <input type="date" wire:model="reconFrom" class="{{ $inp }}" aria-label="From">
                <input type="date" wire:model="reconTo" class="{{ $inp }}" aria-label="To">
                <input type="file" wire:model="statement" accept=".csv,.txt" class="text-xs text-slate-600 dark:text-slate-300" aria-label="Statement file">
            </div>
            @error('statement')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <button type="button" wire:click="reconcile" wire:loading.attr="disabled" wire:target="reconcile,statement" class="{{ $btn }} mt-3">Reconcile</button>

            @if ($runs->isNotEmpty())
                <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Recent runs</p>
                <ul class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                    @foreach ($runs as $r)<li>#{{ $r->id }} · {{ $r->provider }} · {{ $r->period_from->toDateString() }} → {{ $r->period_to->toDateString() }} · {{ $r->matched }} matched · {{ $r->flagged }} to review</li>@endforeach
                </ul>
            @endif

            @foreach ($items as $i)
                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-sm dark:border-white/5" wire:key="item-{{ $i->id }}">
                    <span class="min-w-0 flex-1 text-slate-800 dark:text-slate-200"><strong>{{ str_replace('_', ' ', $i->kind) }}</strong> · {{ $i->provider_reference }} · ours {{ $i->our_amount ?? '—' }} / provider {{ $i->provider_amount ?? '—' }} {{ $i->currency }}</span>
                    <input type="text" wire:model="itemNotes.{{ $i->id }}" placeholder="Resolution note" class="{{ $inp }} w-48" aria-label="Resolution note">
                    <button type="button" wire:click="resolveItem({{ $i->id }})" class="{{ $btn2 }}">Resolve</button>
                </div>
            @endforeach
        </section>

        <section class="{{ $card }}" aria-labelledby="h-acc">
            <h2 id="h-acc" class="text-base font-bold text-slate-900 dark:text-slate-100">Month-end accounting export</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Balanced double-entry lines for your accountant (liability, in transit, provider float, fees). Downloads are logged.</p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <input type="month" wire:model="exportMonth" class="{{ $inp }} w-44" aria-label="Month">
                <button type="button" wire:click="exportAccounting" wire:loading.attr="disabled" wire:target="exportAccounting" class="{{ $btn }}">Download CSV</button>
            </div>
        </section>
    @endif

    @if ($canFinance)
        <section class="{{ $card }}" aria-labelledby="h-claw">
            <h2 id="h-claw" class="text-base font-bold text-slate-900 dark:text-slate-100">Reverse earnings after a refund or lost chargeback</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Always a person's decision. Find the sale by the buyer's email or the order reference, pick the earnings it created, and reverse them. If the earner already withdrew the money their balance goes below zero as an <em>adjustment</em> that their next earnings repay; nothing is taken from them in cash, and they cannot withdraw until it is cleared.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                <input type="text" wire:model="clawQuery" wire:keydown.enter="findEarnings" placeholder="Buyer email or order reference" class="{{ $inp }}" aria-label="Buyer email or order reference">
                <button type="button" wire:click="findEarnings" wire:loading.attr="disabled" wire:target="findEarnings" class="{{ $btn }}">Find earnings</button>
            </div>
            @if ($clawResults !== [])
                <input type="text" wire:model="clawReason" placeholder="Reason (shown to the earner) — required" class="{{ $inp }} mt-3" aria-label="Reason">
                @foreach ($clawResults as $c)
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-sm dark:border-white/5" wire:key="claw-{{ $c['kind'] }}-{{ $c['id'] }}">
                        <span class="min-w-0 text-slate-800 dark:text-slate-200">{{ $c['owner'] }} @if ($c['owner_email'])({{ $c['owner_email'] }})@endif · ${{ number_format($c['amount'], 2) }} · {{ $c['source'] }} · {{ $c['at'] }}</span>
                        @if ($c['reversed'])
                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Already reversed</span>
                        @else
                            <button type="button" wire:click="reverseEarnings('{{ $c['kind'] }}', {{ $c['id'] }})" wire:confirm="Reverse ${{ number_format($c['amount'], 2) }} of this earner's earnings?" wire:loading.attr="disabled" class="{{ $btn2 }}">Reverse ${{ number_format($c['amount'], 2) }}</button>
                        @endif
                    </div>
                @endforeach
            @endif
        </section>
    @endif

    @if ($isSuper)
        <section class="{{ $card }}" aria-labelledby="h-fx">
            <h2 id="h-fx" class="text-base font-bold text-slate-900 dark:text-slate-100">Accept a new exchange rate</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">If you got a "rate moved too far" alert and have checked the market, accept the new rate so quotes resume.</p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <input type="text" wire:model="fxCurrency" maxlength="3" placeholder="KES" class="{{ $inp }} w-24" aria-label="Currency">
                <input type="number" step="any" wire:model="fxRate" placeholder="Rate per 1 USD" class="{{ $inp }} w-44" aria-label="Rate per USD">
                <button type="button" wire:click="acceptFx" wire:confirm="Accept this rate as the new reference?" class="{{ $btn2 }}">Accept rate</button>
            </div>
            @error('fxCurrency')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @error('fxRate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </section>
    @endif

    <section class="{{ $card }}" aria-labelledby="h-ev">
        <h2 id="h-ev" class="text-base font-bold text-slate-900 dark:text-slate-100">Latest provider notifications</h2>
        @forelse ($events as $e)
            <p class="mt-2 text-xs text-slate-600 dark:text-slate-300" wire:key="ev-{{ $e->id }}">{{ $e->received_at->diffForHumans() }} · {{ $e->provider }} · {{ $e->event_type ?: '—' }} · {{ $e->outcome ?? 'received' }}</p>
        @empty
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">None yet.</p>
        @endforelse
    </section>
</div>
