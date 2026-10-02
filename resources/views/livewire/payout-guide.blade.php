@php
    $railName = fn ($r) => __('payout_guide.rail_'.$r);
    $card = 'rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/60';
    $inp = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-white/15 dark:bg-slate-800 dark:text-slate-100';
    $cname = \App\Services\Payouts\Rail\PayoutRailRegistry::countryName($country);
    $verdictParams = ['country' => $cname, 'days' => $globalDays, 'rails' => collect($railsAvailable)->map($railName)->implode(', '), 'recommended' => $recommendedRail ? $railName($recommendedRail) : ''];
    $tone = match ($advice['verdict']) {
        'fast_available' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
        'global_only', 'fast_unavailable_global', 'fast_unavailable' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
        default => 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200',
    };
@endphp

<section dir="{{ $dir }}" class="space-y-4" aria-labelledby="payout-guide-title">
    <div>
        <h2 id="payout-guide-title" class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ __('payout_guide.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('payout_guide.subtitle') }}</p>
    </div>

    {{-- Your country + verdict --}}
    <div class="{{ $card }}">
        <label for="guide-country" class="block text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('payout_guide.your_country') }}</label>
        <select id="guide-country" wire:model.live="country" class="{{ $inp }} mt-1 w-full sm:w-72">
            @foreach ($countryOptions as $code => $name) <option value="{{ $code }}">{{ $name }}</option> @endforeach
        </select>
        <p class="mt-1 text-[11px] text-slate-400">{{ __('payout_guide.country_changed') }}</p>

        <div class="mt-3 flex items-start gap-3 rounded-xl border p-3 text-sm {{ $tone }}" role="status">
            <x-icon :name="in_array($advice['verdict'], ['fast_available']) ? 'check' : (in_array($advice['verdict'], ['global_only', 'fast_unavailable_global', 'fast_unavailable']) ? 'clock' : 'alert-triangle')" class="mt-0.5 h-5 w-5 shrink-0" />
            <p>{{ __('payout_guide.verdict_'.$advice['verdict'], $verdictParams) }}</p>
        </div>
        @if (in_array($advice['verdict'], ['none_available', 'blocked']))
            <button type="button" wire:click="notifyMe" wire:loading.attr="disabled" wire:target="notifyMe" class="mt-3 text-sm font-semibold text-primary hover:underline">{{ __('payout_guide.notify_me') }}</button>
        @endif
    </div>

    @if ($error)
        <div class="rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300" role="alert">{{ $error }}</div>
    @endif
    @if ($notice)
        <div class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300" role="status">{{ $notice }}</div>
    @endif

    {{-- Current rail --}}
    @if ($advice['current'] && $recommendedRail && $advice['current']['rail'] !== $recommendedRail && $advice['current']['rail'] === 'global')
        <div class="flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <span>{{ __('payout_guide.current_rail') }}: {{ $railName($advice['current']['rail']) }}. {{ __('payout_guide.switch_hint') }}</span>
            <button type="button" wire:click="choose('{{ $recommendedRail }}')" class="font-semibold underline">{{ __('payout_guide.switch') }}</button>
        </div>
    @endif

    {{-- Rail cards --}}
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($options as $o)
            @php
                $usable = in_array($o['state'], ['recommended', 'available', 'global_fallback'], true);
                $isGlobal = $o['rail'] === 'global';
            @endphp
            <div class="{{ $card }} {{ $o['state'] === 'recommended' ? 'ring-2 ring-primary/50' : '' }} {{ $usable ? '' : 'opacity-60' }} {{ $isGlobal && $usable ? 'border-amber-300 dark:border-amber-500/40' : '' }}" wire:key="rail-{{ $o['rail'] }}">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $railName($o['rail']) }}</h3>
                    <div class="flex flex-wrap justify-end gap-1">
                        @foreach ($o['badges'] as $b)
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary dark:bg-primary/20">{{ __('payout_guide.badge_'.$b) }}</span>
                        @endforeach
                        @if (in_array($o['state'], ['unavailable', 'not_supported', 'disabled', 'global_fallback'], true))
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ __('payout_guide.state_'.$o['state'], ['country' => $cname]) }}</span>
                        @endif
                    </div>
                </div>

                @if ($isGlobal && $usable)
                    <p class="mt-2 flex items-center gap-1 text-xs font-semibold text-amber-700 dark:text-amber-300"><x-icon name="clock" class="h-4 w-4" /> {{ __('payout_guide.global_title', ['days' => $globalDays]) }}</p>
                @endif
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">{{ __('payout_guide.need_'.$o['rail']) }}</p>
                @if ($o['rail'] === 'stripe_connect') <p class="mt-1 text-[11px] text-slate-400">{{ __('payout_guide.stripe_note') }}</p> @endif

                @if ($usable)
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                        <div><dt class="font-semibold">ETA</dt><dd>{{ $o['eta']['text'] }}@if ($o['eta']['typical_hours']) · {{ __('payout_guide.eta_typical', ['hours' => $o['eta']['typical_hours']]) }}@endif</dd></div>
                        <div><dt class="font-semibold">Fee</dt><dd>{{ $o['fee'] }}</dd></div>
                    </dl>
                @endif

                <details class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
                    <summary class="cursor-pointer font-medium">{{ __('payout_guide.why_this') }}</summary>
                    <ul class="mt-1 list-disc ps-4">
                        @foreach ($o['reasons'] as $reason)
                            @php
                                $count = str_starts_with($reason, 'you_paid_with_it_') ? (int) filter_var($reason, FILTER_SANITIZE_NUMBER_INT) : null;
                            @endphp
                            <li>{{ $count !== null ? __('payout_guide.reason_you_paid_with_it', ['count' => $count]) : __('payout_guide.reason_'.$reason) }}</li>
                        @endforeach
                    </ul>
                    @if ($o['state'] === 'recommended') <p class="mt-1">{{ __('payout_guide.fastest_for_you') }}</p> @endif
                </details>

                @if ($usable)
                    <button type="button" wire:click="choose('{{ $o['rail'] }}')" wire:loading.attr="disabled" wire:target="choose('{{ $o['rail'] }}')"
                            class="nx-btn mt-3 w-full !py-2 text-sm">{{ __('payout_guide.use_this_rail') }}</button>
                @endif
            </div>
        @endforeach
    </div>
    @if (collect($options)->contains(fn ($o) => in_array($o['state'], ['recommended', 'available'], true)))
        <p class="text-[11px] text-slate-400">{{ __('payout_guide.honest_note') }}</p>
    @endif

    {{-- Global acknowledgement (mandatory) --}}
    @if ($showAck)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10" role="group" aria-labelledby="ack-title">
            <h3 id="ack-title" class="flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-200"><x-icon name="clock" class="h-4 w-4" /> {{ __('payout_guide.global_title', ['days' => $globalDays]) }}</h3>
            <p class="mt-1 text-xs text-amber-800 dark:text-amber-200">{{ __('payout_guide.need_global') }}</p>
            <div class="mt-3 space-y-2">
                @foreach ([0, 1, 2] as $i)
                    <label class="flex items-start gap-2 text-sm text-amber-900 dark:text-amber-100">
                        <input type="checkbox" wire:model="ack.{{ $i }}" class="mt-0.5 rounded">
                        <span>{{ __('payout_guide.ack_'.($i + 1), ['days' => $globalDays]) }}</span>
                    </label>
                @endforeach
            </div>
            <button type="button" wire:click="confirmGlobal" wire:loading.attr="disabled" wire:target="confirmGlobal" class="nx-btn nx-btn--gold mt-3 !py-2 text-sm">{{ __('payout_guide.ack_confirm') }}</button>
        </div>
    @endif

    {{-- All countries --}}
    <div class="{{ $card }}">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('payout_guide.all_countries') }}</h3>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('payout_guide.search') }}" aria-label="{{ __('payout_guide.search') }}" class="{{ $inp }} w-full sm:w-64">
            <select wire:model.live="sort" class="{{ $inp }}" aria-label="Sort">
                <option value="az">{{ __('payout_guide.sort_az') }}</option><option value="available">{{ __('payout_guide.sort_available') }}</option>
            </select>
        </div>
        <div class="mt-2 flex flex-wrap gap-1" role="group">
            @foreach (['all' => 'filter_all', 'paystack' => 'filter_paystack', 'flutterwave' => 'filter_flutterwave', 'stripe' => 'filter_stripe', 'global' => 'filter_global', 'unavailable' => 'filter_unavailable'] as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                        class="rounded-full border px-3 py-1 text-xs font-medium {{ $filter === $key ? 'border-primary bg-primary text-white' : 'border-slate-200 text-slate-600 dark:border-white/15 dark:text-slate-300' }}">{{ __('payout_guide.'.$label) }}</button>
            @endforeach
        </div>

        @php
            $cell = fn ($state) => match ($state) {
                'available' => ['check', 'text-emerald-600 dark:text-emerald-400', 'legend_available'],
                'coming_soon' => ['clock', 'text-amber-600 dark:text-amber-400', 'legend_coming_soon'],
                'blocked' => ['shield-alert', 'text-red-600 dark:text-red-400', 'legend_blocked'],
                default => ['x', 'text-slate-300 dark:text-slate-600', 'legend_not_supported'],
            };
        @endphp
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-100 text-start text-[11px] uppercase tracking-wide text-slate-400 dark:border-white/10">
                    <th class="py-2 pe-3 text-start">{{ __('payout_guide.col_country') }}</th>
                    @foreach (['paystack', 'flutterwave', 'stripe_connect', 'global'] as $r) <th class="px-2 py-2 text-center">{{ $railName($r) }}</th> @endforeach
                </tr></thead>
                <tbody>
                    @forelse ($matrix['rows'] as $row)
                        <tr class="border-b border-slate-50 dark:border-white/5 {{ ! empty($row['pinned']) ? 'bg-primary/5 font-semibold dark:bg-primary/10' : '' }}" wire:key="mx-{{ $row['country'] }}">
                            <td class="py-2 pe-3 text-slate-900 dark:text-slate-100">{{ $row['name'] }} @if (! empty($row['pinned'])) <span class="ms-1 rounded-full bg-primary/10 px-1.5 text-[10px] text-primary">{{ __('payout_guide.your_country_pinned') }}</span> @endif</td>
                            @foreach (['paystack', 'flutterwave', 'stripe_connect', 'global'] as $r)
                                @php
                                    [$icon, $cls, $legend] = $cell($row['rails'][$r]);
                                @endphp
                                <td class="px-2 py-2 text-center"><x-icon :name="$icon" class="mx-auto h-4 w-4 {{ $cls }}" /><span class="sr-only">{{ __('payout_guide.'.$legend) }}</span></td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-400">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($matrix['pages'] > 1)
            <div class="mt-3 flex items-center justify-center gap-3 text-xs">
                <button type="button" wire:click="$set('page', {{ max(1, $page - 1) }})" @disabled($page <= 1) class="rounded border border-slate-200 px-2 py-1 disabled:opacity-40 dark:border-white/15 dark:text-slate-300">{{ __('payout_guide.prev') }}</button>
                <span class="text-slate-500 dark:text-slate-400">{{ __('payout_guide.page_of', ['page' => $page, 'pages' => $matrix['pages']]) }}</span>
                <button type="button" wire:click="$set('page', {{ min($matrix['pages'], $page + 1) }})" @disabled($page >= $matrix['pages']) class="rounded border border-slate-200 px-2 py-1 disabled:opacity-40 dark:border-white/15 dark:text-slate-300">{{ __('payout_guide.next') }}</button>
            </div>
        @endif

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
            <span class="font-semibold">{{ __('payout_guide.legend') }}:</span>
            @foreach (['available' => ['check', 'text-emerald-600'], 'coming_soon' => ['clock', 'text-amber-600'], 'not_supported' => ['x', 'text-slate-400'], 'blocked' => ['shield-alert', 'text-red-600']] as $k => [$ic, $c])
                <span class="inline-flex items-center gap-1"><x-icon :name="$ic" class="h-3.5 w-3.5 {{ $c }}" /> {{ __('payout_guide.legend_'.$k) }}</span>
            @endforeach
            <span class="ms-auto">{{ $verifiedAt ? __('payout_guide.last_verified', ['date' => $verifiedAt->format('M j, Y')]) : __('payout_guide.never_verified') }}</span>
        </div>
    </div>
</section>
