@php
    $railName = fn ($r) => __('payout_guide.rail_'.$r);
    $cname = \App\Services\Payouts\Rail\PayoutRailRegistry::countryName($country);
    $verdictParams = ['country' => $cname, 'days' => $globalDays, 'rails' => collect($railsAvailable)->map($railName)->implode(', '), 'recommended' => $recommendedRail ? $railName($recommendedRail) : ''];
    $fast = $advice['verdict'] === 'fast_available';
    $soon = in_array($advice['verdict'], ['global_only', 'fast_unavailable_global', 'fast_unavailable'], true);
@endphp

<section dir="{{ $dir }}" aria-labelledby="payout-guide-title">
    <h2 id="payout-guide-title" class="ns-h1" style="font-size:22px;margin:0">{{ __('payout_guide.title') }}</h2>
    <p class="ns-sub">{{ __('payout_guide.subtitle') }}</p>

    {{-- Your country + verdict --}}
    <div class="ns-card ns-ring" style="padding:14px;margin-top:14px">
        <label for="guide-country" class="ns-lbl" style="display:block">{{ __('payout_guide.your_country') }}</label>
        <select id="guide-country" wire:model.live="country" class="ns-select">
            @foreach ($countryOptions as $code => $name) <option value="{{ $code }}">{{ $name }}</option> @endforeach
        </select>
        <p class="ns-small" style="margin-top:6px">{{ __('payout_guide.country_changed') }}</p>

        <x-nx.note :icon="$fast ? 'check' : ($soon ? 'cal' : 'info')" :variant="$fast ? null : 'warn'" role="status">{{ __('payout_guide.verdict_'.$advice['verdict'], $verdictParams) }}</x-nx.note>
        @if ($advice['verdict'] === 'none_available' && \App\Support\PayoutSettings::peerEnabled())
            <x-nx.note icon="send" variant="dash">
                {{ __('payouts.peer.cta') }}
                <a href="{{ route('send-earnings') }}" wire:navigate style="display:block;margin-top:4px;font-weight:600;text-decoration:underline">{{ __('payouts.peer.cta_button') }}</a>
            </x-nx.note>
        @endif
        @if (in_array($advice['verdict'], ['none_available', 'blocked']))
            <button type="button" class="ns-btn" style="margin-top:12px" wire:click="notifyMe" wire:loading.attr="disabled" wire:target="notifyMe">{{ __('payout_guide.notify_me') }}</button>
        @endif
    </div>

    @if ($error)
        <x-nx.note icon="info" variant="warn" role="alert">{{ $error }}</x-nx.note>
    @endif
    @if ($notice)
        <x-nx.note icon="check" role="status">{{ $notice }}</x-nx.note>
    @endif

    {{-- Current rail --}}
    @if ($advice['current'] && $recommendedRail && $advice['current']['rail'] !== $recommendedRail && $advice['current']['rail'] === 'global')
        <x-nx.note icon="cal" variant="warn">
            {{ __('payout_guide.current_rail') }}: {{ $railName($advice['current']['rail']) }}. {{ __('payout_guide.switch_hint') }}
            <button type="button" wire:click="choose('{{ $recommendedRail }}')" style="font-weight:600;text-decoration:underline">{{ __('payout_guide.switch') }}</button>
        </x-nx.note>
    @endif

    {{-- Rail cards --}}
    <div class="ns-cards-grid">
        @foreach ($options as $o)
            @php
                $usable = in_array($o['state'], ['recommended', 'available', 'global_fallback'], true);
                $isGlobal = $o['rail'] === 'global';
            @endphp
            <div class="ns-card ns-ring" style="padding:14px;{{ $o['state'] === 'recommended' ? 'outline:2px solid rgb(var(--nx-teal-ink) / .6);outline-offset:-2px;' : '' }}{{ $usable ? '' : 'opacity:.7;' }}" wire:key="rail-{{ $o['rail'] }}">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
                    <h3 style="font-size:16px;font-weight:600;margin:0">{{ $railName($o['rail']) }}</h3>
                    <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:4px">
                        @foreach ($o['badges'] as $b)
                            <x-nx.pill variant="best">{{ __('payout_guide.badge_'.$b) }}</x-nx.pill>
                        @endforeach
                        @if (in_array($o['state'], ['unavailable', 'not_supported', 'disabled', 'global_fallback'], true))
                            <x-nx.pill variant="out">{{ __('payout_guide.state_'.$o['state'], ['country' => $cname]) }}</x-nx.pill>
                        @endif
                    </div>
                </div>

                @if ($isGlobal && $usable)
                    <p style="display:flex;align-items:center;gap:6px;margin:8px 0 0;font-size:13px;font-weight:600;color:rgb(var(--nx-warn))"><x-nx.icon name="cal" /> {{ __('payout_guide.global_title', ['days' => $globalDays]) }}</p>
                @endif
                <p class="ns-sub" style="font-size:13px;margin:8px 0 0">{{ __('payout_guide.need_'.$o['rail']) }}</p>
                @if ($o['rail'] === 'stripe_connect') <p class="ns-small" style="margin-top:4px">{{ __('payout_guide.stripe_note') }}</p> @endif

                @if ($usable)
                    <dl style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:10px 0 0;font-size:12px;color:rgb(var(--nx-text-2))">
                        <div><dt style="font-weight:600">ETA</dt><dd style="margin:0">{{ $o['eta']['text'] }}@if ($o['eta']['typical_hours']) · {{ __('payout_guide.eta_typical', ['hours' => $o['eta']['typical_hours']]) }}@endif</dd></div>
                        <div><dt style="font-weight:600">Fee</dt><dd style="margin:0">{{ $o['fee'] }}</dd></div>
                    </dl>
                @endif

                <details class="ns-small" style="margin-top:10px">
                    <summary style="cursor:pointer;font-weight:500">{{ __('payout_guide.why_this') }}</summary>
                    <ul style="margin:6px 0 0;padding-inline-start:18px;list-style:disc">
                        @foreach ($o['reasons'] as $reason)
                            @php
                                $count = str_starts_with($reason, 'you_paid_with_it_') ? (int) filter_var($reason, FILTER_SANITIZE_NUMBER_INT) : null;
                            @endphp
                            <li>{{ $count !== null ? __('payout_guide.reason_you_paid_with_it', ['count' => $count]) : __('payout_guide.reason_'.$reason) }}</li>
                        @endforeach
                    </ul>
                    @if ($o['state'] === 'recommended') <p style="margin:6px 0 0">{{ __('payout_guide.fastest_for_you') }}</p> @endif
                </details>

                @if ($usable)
                    <button type="button" class="ns-btn" style="margin-top:12px;width:100%;justify-content:center" wire:click="choose('{{ $o['rail'] }}')" wire:loading.attr="disabled" wire:target="choose('{{ $o['rail'] }}')">{{ __('payout_guide.use_this_rail') }}</button>
                @endif
            </div>
        @endforeach
    </div>
    @if (collect($options)->contains(fn ($o) => in_array($o['state'], ['recommended', 'available'], true)))
        <p class="ns-small" style="margin-top:10px">{{ __('payout_guide.honest_note') }}</p>
    @endif

    {{-- Global acknowledgement (mandatory) --}}
    @if ($showAck)
        <div class="ns-note ns-note--warn" style="display:block" role="group" aria-labelledby="ack-title">
            <h3 id="ack-title" style="display:flex;align-items:center;gap:8px;font-size:15px;font-weight:600;margin:0;color:rgb(var(--nx-text))"><x-nx.icon name="cal" /> {{ __('payout_guide.global_title', ['days' => $globalDays]) }}</h3>
            <p style="margin:6px 0 0">{{ __('payout_guide.need_global') }}</p>
            @foreach ([0, 1, 2] as $i)
                <label class="ns-chk" style="cursor:pointer;margin-top:12px">
                    <input type="checkbox" wire:model="ack.{{ $i }}" class="ns-check-input">
                    <span>{{ __('payout_guide.ack_'.($i + 1), ['days' => $globalDays]) }}</span>
                </label>
            @endforeach
            <x-nx.cta variant="gold" loading="confirmGlobal" wire:click="confirmGlobal" style="margin-top:16px">{{ __('payout_guide.ack_confirm') }}</x-nx.cta>
        </div>
    @endif

    {{-- All countries --}}
    <div class="ns-card ns-ring" style="padding:14px;margin-top:14px">
        <h3 style="font-size:16px;font-weight:600;margin:0">{{ __('payout_guide.all_countries') }}</h3>
        <label class="ns-search"><x-nx.icon name="search" /><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('payout_guide.search') }}" aria-label="{{ __('payout_guide.search') }}"></label>
        <select wire:model.live="sort" class="ns-select" aria-label="Sort">
            <option value="az">{{ __('payout_guide.sort_az') }}</option><option value="available">{{ __('payout_guide.sort_available') }}</option>
        </select>
        <div class="ns-chips-filter" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px" role="group">
            @foreach (['all' => 'filter_all', 'paystack' => 'filter_paystack', 'flutterwave' => 'filter_flutterwave', 'stripe' => 'filter_stripe', 'global' => 'filter_global', 'unavailable' => 'filter_unavailable'] as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}" class="{{ $filter === $key ? 'is-on' : '' }}">{{ __('payout_guide.'.$label) }}</button>
            @endforeach
        </div>

        @php
            $cell = fn ($state) => match ($state) {
                'available' => ['check', 'ok', 'legend_available'],
                'coming_soon' => ['cal', 'warn', 'legend_coming_soon'],
                'blocked' => ['shield', 'bad', 'legend_blocked'],
                default => ['x', 'text-3', 'legend_not_supported'],
            };
        @endphp
        <div style="margin-top:12px;overflow-x:auto">
            <table class="ns-table">
                <thead><tr>
                    <th style="text-align:start">{{ __('payout_guide.col_country') }}</th>
                    @foreach (['paystack', 'flutterwave', 'stripe_connect', 'global'] as $r) <th style="text-align:center">{{ $railName($r) }}</th> @endforeach
                </tr></thead>
                <tbody>
                    @forelse ($matrix['rows'] as $row)
                        <tr class="{{ ! empty($row['pinned']) ? 'is-pinned' : '' }}" wire:key="mx-{{ $row['country'] }}">
                            <td>{{ $row['name'] }} @if (! empty($row['pinned'])) <x-nx.pill variant="best">{{ __('payout_guide.your_country_pinned') }}</x-nx.pill> @endif</td>
                            @foreach (['paystack', 'flutterwave', 'stripe_connect', 'global'] as $r)
                                @php
                                    [$icon, $tone, $legend] = $cell($row['rails'][$r]);
                                @endphp
                                <td style="text-align:center"><x-nx.icon :name="$icon" style="color:rgb(var(--nx-{{ $tone }}))" /><span class="sr-only">{{ __('payout_guide.'.$legend) }}</span></td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($matrix['pages'] > 1)
            <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-top:12px;font-size:13px">
                <button type="button" class="ns-btn" style="margin:0;height:38px" wire:click="$set('page', {{ max(1, $page - 1) }})" @disabled($page <= 1)>{{ __('payout_guide.prev') }}</button>
                <span class="ns-small">{{ __('payout_guide.page_of', ['page' => $page, 'pages' => $matrix['pages']]) }}</span>
                <button type="button" class="ns-btn" style="margin:0;height:38px" wire:click="$set('page', {{ min($matrix['pages'], $page + 1) }})" @disabled($page >= $matrix['pages'])>{{ __('payout_guide.next') }}</button>
            </div>
        @endif

        <div class="ns-small" style="display:flex;flex-wrap:wrap;align-items:center;gap:4px 16px;margin-top:12px">
            <b>{{ __('payout_guide.legend') }}:</b>
            @foreach (['available' => ['check', 'ok'], 'coming_soon' => ['cal', 'warn'], 'not_supported' => ['x', 'text-3'], 'blocked' => ['shield', 'bad']] as $k => [$ic, $c])
                <span style="display:inline-flex;align-items:center;gap:4px"><x-nx.icon :name="$ic" style="color:rgb(var(--nx-{{ $c }}))" /> {{ __('payout_guide.legend_'.$k) }}</span>
            @endforeach
            <span style="margin-inline-start:auto">{{ $verifiedAt ? __('payout_guide.last_verified', ['date' => $verifiedAt->format('M j, Y')]) : __('payout_guide.never_verified') }}</span>
        </div>
    </div>
</section>
