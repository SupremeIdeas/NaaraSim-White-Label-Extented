<div class="mx-auto max-w-2xl">
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">My Journey</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Your rewards path and your connectivity history — built from your own activity.</p>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 inline-flex w-full rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-white/10 dark:bg-white/5">
        <button type="button" wire:click="setTab('milestones')"
                class="flex-1 rounded-full px-3 py-2 text-sm font-semibold transition {{ $tab === 'milestones' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400' }}">
            <x-icon name="gift" class="mr-1 inline h-4 w-4" /> Milestones
        </button>
        <button type="button" wire:click="setTab('goals')"
                class="flex-1 rounded-full px-3 py-2 text-sm font-semibold transition {{ $tab === 'goals' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400' }}">
            <x-icon name="star" class="mr-1 inline h-4 w-4" /> Goals
        </button>
        <button type="button" wire:click="setTab('travel')"
                class="flex-1 rounded-full px-3 py-2 text-sm font-semibold transition {{ $tab === 'travel' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-teal-300' : 'text-slate-500 dark:text-slate-400' }}">
            <x-icon name="globe" class="mr-1 inline h-4 w-4" /> Travel
        </button>
    </div>

    @if ($tab === 'milestones')
        @if (! $creditsEnabled)
            <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-400 dark:border-white/10">NaaraCredits isn't enabled right now.</div>
        @else
            <div class="relative space-y-0">
                @foreach ($milestones as $i => $m)
                    @php
                        $dot = match ($m['status']) {
                            'done' => 'bg-emerald-500 text-white',
                            'ongoing' => 'bg-primary text-white',
                            default => 'bg-slate-200 text-slate-400 dark:bg-white/10 dark:text-slate-500',
                        };
                    @endphp
                    <div class="relative flex gap-4 pb-8 last:pb-0" wire:key="ms-{{ $m['key'] }}">
                        {{-- Connector line --}}
                        @unless ($loop->last)
                            <span class="absolute left-[19px] top-10 h-full w-px {{ $m['status'] === 'locked' ? 'bg-slate-200 dark:bg-white/10' : 'bg-primary/30' }}"></span>
                        @endunless
                        <span class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $dot }}">
                            @if ($m['status'] === 'done')<x-icon name="check" class="h-5 w-5" />@else<x-icon name="{{ $m['icon'] }}" class="h-4 w-4" />@endif
                        </span>
                        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10 {{ $m['status'] === 'locked' ? 'opacity-60' : '' }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $m['title'] }}</p>
                                @if ($m['status'] === 'ongoing')<span class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase text-primary dark:bg-primary/20 dark:text-teal-300">Ongoing</span>@endif
                            </div>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $m['description'] }}</p>
                            @if ($m['meta'])<p class="mt-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ $m['meta'] }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('rewards') }}" wire:navigate class="mt-2 block text-center text-sm font-semibold text-primary hover:underline dark:text-teal-300">Go earn more on Rewards →</a>
        @endif
    @elseif ($tab === 'goals')
        @if (! $creditsEnabled)
            <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-400 dark:border-white/10">NaaraCredits isn't enabled right now.</div>
        @elseif (empty($goals))
            <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-400 dark:border-white/10">No goals are live yet — check back soon.</div>
        @else
            <div class="space-y-3">
                @foreach ($goals as $row)
                    @php
                        $goal = $row['goal']; $p = $row['progress'];
                        $pct = $p['target'] > 0 ? min(100, (int) round($p['current'] / $p['target'] * 100)) : 0;
                        $periodLabel = match ($goal->period_type) {
                            'monthly' => 'This month', 'quarterly' => 'This quarter', 'yearly' => 'This year',
                            'campaign' => 'Limited time', default => 'Lifetime',
                        };
                    @endphp
                    <div wire:key="goal-{{ $goal->id }}" class="rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10">
                        <div class="flex items-start justify-between gap-3">
                            @if ($goal->image_path && ! $p['claimed'])
                                <img src="{{ $goal->image_path }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
                            @else
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $p['claimed'] ? 'bg-emerald-500 text-white' : 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300' }}">
                                    <x-icon name="{{ $p['claimed'] ? 'check' : ($goal->icon ?: 'star') }}" class="h-5 w-5" />
                                </span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ $goal->title }}</p>
                                    <span class="shrink-0 rounded-full bg-accent/15 px-2 py-0.5 text-[10px] font-bold uppercase text-accent-dark dark:text-accent">+{{ rtrim(rtrim(number_format((float) $goal->reward_credits, 2), '0'), '.') }}</span>
                                </div>
                                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $goal->description }}</p>
                                <div class="mt-2.5">
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                        <div class="h-full rounded-full {{ $p['claimed'] ? 'bg-emerald-500' : 'bg-primary' }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ $periodLabel }} ·
                                        @if ($p['claimed']) Reached — {{ $p['claimed_at']?->format('M j, Y') }}
                                        @else {{ rtrim(rtrim(number_format($p['current'], 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($p['target'], 2), '0'), '.') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        @if ($orders->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-400 dark:border-white/10">
                No eSIMs yet — your first purchase will show up here as a stamp on your journey.
                <a href="{{ route('catalogue') }}" wire:navigate class="mt-2 block font-semibold text-primary hover:underline dark:text-teal-300">Browse eSIM plans →</a>
            </div>
        @else
            <div class="relative space-y-0">
                @foreach ($orders as $order)
                    @php
                        $country = $order->plan?->countries[0] ?? null;
                        $isActive = $order->status === 'active' && (! $order->expires_at || $order->expires_at->isFuture());
                        $isExpired = $order->expires_at && $order->expires_at->isPast();
                        $tone = $isExpired ? 'bg-slate-200 text-slate-500 dark:bg-white/10 dark:text-slate-400' : ($isActive ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-white');
                    @endphp
                    <div class="relative flex gap-4 pb-8 last:pb-0" wire:key="ord-{{ $order->id }}">
                        @unless ($loop->last)
                            <span class="absolute left-[19px] top-10 h-full w-px bg-primary/30"></span>
                        @endunless
                        <span class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full ring-2 ring-white dark:ring-[#0D1B2A]">
                            <x-country-flag :country="$country" class="h-10 w-10 rounded-full" />
                        </span>
                        <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900 dark:text-white">{{ $order->plan?->name ?? 'eSIM plan' }}</p>
                                    <p class="text-xs text-slate-400">Purchased {{ $order->created_at->format('M j, Y') }}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $tone }}">{{ $isExpired ? 'Expired' : ($isActive ? 'Active' : ucfirst($order->status)) }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                @if ($order->plan?->data_mb)<span><x-icon name="wifi" class="mr-1 inline h-3.5 w-3.5" />{{ number_format($order->plan->data_mb / 1024, 1) }} GB</span>@endif
                                @if ($order->expires_at)
                                    <span><x-icon name="info" class="mr-1 inline h-3.5 w-3.5" />{{ $isExpired ? 'Expired '.$order->expires_at->diffForHumans() : 'Valid until '.$order->expires_at->format('M j, Y') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
