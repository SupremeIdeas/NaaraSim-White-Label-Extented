{{-- Premium brand card — shared by the Featured band and the category
     sections below it, so both stay visually identical (BUILD-9 §8.1). --}}
<div class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-white/10 dark:bg-[#16233d]"
     wire:key="brand-{{ $brand->id }}">
    @php($hero = $brand->hero_image_path ?: $brand->fallback_image)
    <a href="{{ route('rewards.hunt.profile', $brand) }}" wire:navigate class="relative block h-36 w-full overflow-hidden">
        @if ($hero)
            <img src="{{ $hero }}" alt="{{ $brand->brand_name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="h-full w-full" style="background: linear-gradient(135deg, {{ $brand->background_color }}, {{ $brand->background_color }}99)"></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/0 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 p-4">
            <h3 class="text-lg font-bold leading-tight text-white drop-shadow-sm">{{ $brand->brand_name }}</h3>
            @if ($brand->is_featured)
                <span class="flex shrink-0 items-center gap-1 rounded-full bg-accent px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow">
                    <x-icon name="badge-check" class="h-3 w-3" /> Featured
                </span>
            @endif
        </div>
    </a>
    <div class="p-5">
        <div class="flex items-center justify-between gap-2">
            @if ($brand->category)
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500 dark:bg-white/5 dark:text-slate-400">
                    <x-icon name="tag" class="h-3 w-3" /> {{ $brand->category }}
                </span>
            @else
                <span></span>
            @endif
            {{-- Connect (owner request, 2026-09-22): a plain in-platform
                 follow, distinct from the handle-follow-for-credits grid
                 below — no reward, just a relationship + Naara follower
                 count, toggling to "Connected" on click. --}}
            @php($following = isset($followingBrandIds[$brand->id]))
            <button type="button" wire:click="toggleConnect({{ $brand->id }})" wire:loading.attr="disabled" wire:target="toggleConnect({{ $brand->id }})"
                    @class([
                        'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold transition disabled:opacity-60',
                        'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-950/40 dark:text-green-300' => $following,
                        'bg-primary/10 text-primary hover:bg-primary/15 dark:bg-primary/20 dark:text-teal-300' => ! $following,
                    ])>
                <x-icon :name="$following ? 'check' : 'user-plus'" class="h-3 w-3" />
                {{ $following ? 'Connected' : 'Connect' }}
            </button>
        </div>
        @if ($brand->short_description)
            <p class="mt-2.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $brand->short_description }}</p>
        @endif

        @if ($brand->handles->isNotEmpty())
            <div class="mt-4 grid gap-2.5 sm:grid-cols-2">
                @foreach ($brand->handles as $h)
                    @include('livewire.partials.hunt-handle-card', ['handle' => $h, 'claimed' => isset($claimedBrandHandles[$h->id]), 'action' => 'followBrandHandle'])
                @endforeach
            </div>
        @endif

        {{-- Video previews with watch-to-earn (server-confirmed watch-time). --}}
        @foreach ($brand->videos as $v)
            @php($embed = $v->embedUrl())
            @if ($embed)
                <div class="mt-4" wire:key="vid-{{ $v->id }}"
                     x-data="{
                        playing: false, token: null, elapsed: 0, confirmed: 0, done: false, timer: null, startedAt: 0,
                        async start() {
                            const r = await $wire.videoStart({{ $v->id }});
                            if (!r) return;
                            this.token = r.token; this.playing = true; this.startedAt = Date.now();
                            this.timer = setInterval(async () => {
                                this.elapsed = Math.floor((Date.now() - this.startedAt) / 1000);
                                const s = await $wire.videoHeartbeat(this.token, this.elapsed);
                                this.confirmed = s.confirmed;
                                if (s.claimed || s.already) { this.done = true; clearInterval(this.timer); }
                            }, 10000);
                        }
                     }" x-init="() => { window.addEventListener('beforeunload', () => timer && clearInterval(timer)); }">
                    <template x-if="!playing">
                        <button @click="start()" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                            <x-icon name="play" class="h-4 w-4" /> Watch to earn
                        </button>
                    </template>
                    <template x-if="playing">
                        <div>
                            <div class="aspect-video overflow-hidden rounded-xl bg-black">
                                <iframe :src="token ? '{{ $embed }}' : ''" class="h-full w-full" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                            </div>
                            <p class="mt-1.5 text-center text-xs text-slate-500 dark:text-slate-400">
                                <span x-show="!done">Keep watching — <span x-text="confirmed"></span>s / 60s confirmed</span>
                                <span x-show="done" class="font-semibold text-green-600 dark:text-green-400">Reward unlocked!</span>
                            </p>
                        </div>
                    </template>
                </div>
            @endif
        @endforeach
    </div>
</div>
