{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Blog reader</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">How readers share an article and what they are offered when they finish one. Counts below are daily totals only; no visitor is identified.</p>
    </div>

    <div class="{{ $card }}">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Last 30 days</h2>
        <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([['Share opened', $stats['share_open']], ['Channel clicks', $stats['share_click']], ['Links copied', $stats['share_copy']], ['Keep reading shown', $stats['shown']], ['Keep reading clicks', $stats['clicked']], ['Click rate', $stats['ctr'] === null ? '-' : $stats['ctr'].'%'], ['Dismiss rate', $stats['dismiss_rate'] === null ? '-' : $stats['dismiss_rate'].'%'], ['Turned off', $stats['disabled']]] as [$k, $v])
                <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3"><dt class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $k }}</dt><dd class="mt-0.5 text-lg font-semibold text-[rgb(var(--nx-text))]">{{ $v }}</dd></div>
            @endforeach
        </dl>
        @if ($channelClicks->isNotEmpty())
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">By channel: @foreach ($channelClicks as $c){{ ($all[$c->channel]['label'] ?? ucfirst($c->channel ?: 'other')) }} {{ $c->n }}{{ $loop->last ? '' : ' · ' }}@endforeach</p>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="{{ $card }}">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Share channels</h2>
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Tick the channels to offer, and order them. Endpoints live in config/blog_share.php.</p>
            <ul class="mt-3 space-y-1.5">
                @foreach (array_values(array_unique([...$channels, ...array_keys($all)])) as $key)
                    @continue(! isset($all[$key]))
                    <li wire:key="ch-{{ $key }}" class="flex items-center gap-2 rounded-xl border border-[rgb(var(--nx-line))] px-3 py-2">
                        <label class="flex flex-1 items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" @checked(in_array($key, $channels, true)) wire:click="toggleChannel('{{ $key }}')"> {{ $all[$key]['label'] }}</label>
                        @if (in_array($key, $channels, true))
                            <button type="button" wire:click="moveChannel('{{ $key }}', 'up')" class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs">Up</button>
                            <button type="button" wire:click="moveChannel('{{ $key }}', 'down')" class="rounded-lg border border-[rgb(var(--nx-line))] px-2 py-1 text-xs">Down</button>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div><label class="{{ $lb }}">Default share text</label><input type="text" wire:model="share_text" maxlength="200" placeholder="Defaults to the article title" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Hashtags</label><input type="text" wire:model="share_hashtags" maxlength="120" placeholder="#eSIM #travel" class="{{ $in }}"></div>
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="share_native"> Offer the phone's own share sheet ("More options") where available</label>
        </div>

        <div class="{{ $card }}">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Keep reading</h2>
            <div class="mt-3 space-y-2 text-sm text-[rgb(var(--nx-text))]">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="keep_enabled"> Show suggested articles under every article</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="keep_popup"> Also offer them in a pop-up once the reader has finished (off = under the article only)</label>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div><label class="{{ $lb }}">Articles shown (3-8)</label><input type="number" min="3" max="8" wire:model="keep_count" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Scrolled through (%)</label><input type="number" min="50" max="100" wire:model="keep_scroll_pct" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Time on page (% of read time)</label><input type="number" min="0" max="100" wire:model="keep_dwell_pct" class="{{ $in }}"></div>
            </div>
            @error('keep_count') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            @if ($categories->isNotEmpty())
                <p class="{{ $lb }} mt-3">Never suggest these categories</p>
                <div class="flex flex-wrap gap-3 text-sm text-[rgb(var(--nx-text))]">@foreach ($categories as $c)<label class="flex items-center gap-1.5"><input type="checkbox" value="{{ $c }}" wire:model="exclude"> {{ $c }}</label>@endforeach</div>
            @endif
        </div>

        <div class="{{ $card }}">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Header "More" menu: from the blog</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div><label class="{{ $lb }}">Layout</label><select wire:model="header_blog_layout" class="{{ $in }}"><option value="grid">Grid</option><option value="list">List</option><option value="carousel">Carousel</option></select></div>
                <div><label class="{{ $lb }}">Posts (1-8)</label><input type="number" min="1" max="8" wire:model="header_blog_count" class="{{ $in }}"></div>
            </div>
        </div>

        <div class="flex justify-end"><button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">Save</button></div>
    </form>
</div>
