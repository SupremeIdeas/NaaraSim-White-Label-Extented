{{-- My Journey on the skin system (S3 Batch 3): milestones as a timeline of nodes + cards, goals with progress bars, travel stamps. Same data,
     tabs and rules as before. --}}
<x-nx.page class="ns-narrow">
    <h1 class="ns-h1" style="margin-top:6px">My Journey</h1>
    <p class="ns-sub">Your rewards path and your connectivity history — built from your own activity.</p>

    {{-- Tabs --}}
    <div class="ns-seg" style="margin-top:16px" role="tablist">
        @foreach (['milestones' => ['gift', 'Milestones'], 'goals' => ['star', 'Goals'], 'travel' => ['globe', 'Travel']] as $key => [$icon, $text])
            <button type="button" role="tab" wire:click="setTab('{{ $key }}')" class="{{ $tab === $key ? 'is-on' : '' }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"><x-nx.icon :name="$icon" /> {{ $text }}</button>
        @endforeach
    </div>

    @if ($tab === 'milestones')
        @if (! $creditsEnabled)
            <x-nx.empty text="NaaraCredits isn't enabled right now." style="margin-top:18px" />
        @else
            <div class="ns-jlist">
                @foreach ($milestones as $i => $m)
                    <div class="ns-jnode ns-jnode--{{ $m['status'] }}" wire:key="ms-{{ $m['key'] }}">
                        <span class="ns-jdot">@if ($m['status'] === 'done')<x-nx.icon name="check" />@else<x-nx.icon :name="$m['icon']" />@endif</span>
                        <div class="ns-card ns-ring ns-jcard">
                            <span style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                                <b>{{ $m['title'] }}</b>
                                @if ($m['status'] === 'ongoing')<x-nx.pill variant="best">Ongoing</x-nx.pill>@endif
                            </span>
                            <p>{{ $m['description'] }}</p>
                            @if ($m['meta'])<small style="color:rgb(var({{ $m['status'] === 'done' ? '--nx-ok' : '--nx-text-2' }}));font-weight:600">{{ $m['meta'] }}</small>@endif
                        </div>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('rewards') }}" wire:navigate class="ns-btn" style="margin-top:6px;display:flex;justify-content:center">Go earn more on Rewards <x-nx.icon name="chevron-right" /></a>
        @endif
    @elseif ($tab === 'goals')
        @if (! $creditsEnabled)
            <x-nx.empty text="NaaraCredits isn't enabled right now." style="margin-top:18px" />
        @elseif (empty($goals))
            <x-nx.empty text="No goals are live yet — check back soon." style="margin-top:18px" />
        @else
            <div style="margin-top:14px">
                @foreach ($goals as $row)
                    @php
                        $goal = $row['goal']; $p = $row['progress'];
                        $pct = $p['target'] > 0 ? min(100, (int) round($p['current'] / $p['target'] * 100)) : 0;
                        $periodLabel = match ($goal->period_type) {
                            'monthly' => 'This month', 'quarterly' => 'This quarter', 'yearly' => 'This year',
                            'campaign' => 'Limited time', default => 'Lifetime',
                        };
                    @endphp
                    <div wire:key="goal-{{ $goal->id }}" class="ns-card ns-ring ns-goal">
                        @if ($goal->image_path && ! $p['claimed'])
                            <img src="{{ $goal->image_path }}" alt="" class="ns-goal__img">
                        @else
                            <span class="ns-tile" @if ($p['claimed']) style="--nx-tone:var(--nx-ok);color:rgb(var(--nx-ok))" @endif><x-nx.icon :name="$p['claimed'] ? 'check' : ($goal->icon ?: 'star')" /></span>
                        @endif
                        <div style="min-width:0;flex:1">
                            <span style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                                <b style="font-size:16px;font-weight:600">{{ $goal->title }}</b>
                                <x-nx.pill variant="gold"><x-naara-coin class="h-3.5 w-3.5" /> +{{ rtrim(rtrim(number_format((float) $goal->reward_credits, 2), '0'), '.') }}</x-nx.pill>
                            </span>
                            <p class="ns-sub" style="font-size:14px;margin:4px 0 0">{{ $goal->description }}</p>
                            <div class="ns-progress" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><i style="width:{{ $pct }}%"></i></div>
                            <small class="ns-small" style="display:block;margin-top:6px">
                                {{ $periodLabel }} ·
                                @if ($p['claimed']) Reached — {{ $p['claimed_at']?->format('M j, Y') }}
                                @else {{ rtrim(rtrim(number_format($p['current'], 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($p['target'], 2), '0'), '.') }}
                                @endif
                            </small>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        @if ($orders->isEmpty())
            <x-nx.empty text="No eSIMs yet — your first purchase will show up here as a stamp on your journey." style="margin-top:18px">
                <a href="{{ route('catalogue') }}" wire:navigate class="ns-btn ns-btn--solid" style="margin-top:12px;display:inline-flex">Browse eSIM plans</a>
            </x-nx.empty>
        @else
            <div class="ns-jlist">
                @foreach ($orders as $order)
                    @php
                        $country = $order->plan?->countries[0] ?? null;
                        $isActive = $order->status === 'active' && (! $order->expires_at || $order->expires_at->isFuture());
                        $isExpired = $order->expires_at && $order->expires_at->isPast();
                    @endphp
                    <div class="ns-jnode ns-jnode--{{ $isExpired ? 'locked' : 'done' }}" wire:key="ord-{{ $order->id }}">
                        <span class="ns-jdot ns-jdot--flag"><x-country-flag :country="$country" class="h-10 w-10 rounded-full" /></span>
                        <div class="ns-card ns-ring ns-jcard">
                            <span style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
                                <span style="min-width:0">
                                    <b style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $order->plan?->name ?? 'eSIM plan' }}</b>
                                    <small class="ns-small">Purchased {{ $order->created_at->format('M j, Y') }}</small>
                                </span>
                                <x-nx.pill :variant="$isExpired ? 'out' : ($isActive ? 'rec' : 'best')">{{ $isExpired ? 'Expired' : ($isActive ? 'Active' : ucfirst($order->status)) }}</x-nx.pill>
                            </span>
                            <span style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;margin-top:8px;font-size:13px;color:rgb(var(--nx-text-2))">
                                @if ($order->plan?->data_mb)<span style="display:inline-flex;align-items:center;gap:4px"><x-nx.icon name="bars" />{{ number_format($order->plan->data_mb / 1024, 1) }} GB</span>@endif
                                @if ($order->expires_at)<span style="display:inline-flex;align-items:center;gap:4px"><x-nx.icon name="info" />{{ $isExpired ? 'Expired '.$order->expires_at->diffForHumans() : 'Valid until '.$order->expires_at->format('M j, Y') }}</span>@endif
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-nx.page>
