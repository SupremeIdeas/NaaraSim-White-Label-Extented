<div class="mx-auto max-w-3xl px-4 py-6"
     x-data="{ bg: '' }" :style="bg ? `background-color:${bg}` : ''"
     style="transition: background-color .7s ease">

    <div class="mb-6">
        <a href="{{ route('rewards') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back to Rewards
        </a>
        <h1 class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">The Hunt</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Follow brands to earn surprise NaaraCredits. Each follow is a one-time reward.
            <span class="font-medium text-primary dark:text-teal-300">${{ number_format($dailyRemaining, 0) }} left to earn today.</span>
        </p>
    </div>

    @if ($flash)
        <div class="mb-5 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm font-semibold text-primary dark:border-primary/30 dark:bg-primary/10 dark:text-teal-200">{{ $flash }}</div>
    @endif

    {{-- Platform: follow NaaraSim's own handles. --}}
    @if ($platformHandles->isNotEmpty())
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">Follow NaaraSim</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($platformHandles as $h)
                    @include('livewire.partials.hunt-handle-card', ['handle' => $h, 'claimed' => isset($claimedHandles[$h->id]), 'action' => 'followHandle'])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Category filter --}}
    @if ($categories->isNotEmpty())
        <div class="mb-5 flex flex-wrap gap-2">
            <button wire:click="setCategory(null)" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ ! $category ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300' }}">All</button>
            @foreach ($categories as $cat)
                <button wire:click="setCategory('{{ $cat }}')" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $category === $cat ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300' }}">{{ $cat }}</button>
            @endforeach
        </div>
    @endif

    {{-- Brand partners — each drives the page background as it scrolls into view. --}}
    @forelse ($brands as $brand)
        <section class="mb-10 scroll-mt-6"
                 x-intersect.threshold.40="bg = '{{ $brand->background_color }}1a'"
                 wire:key="brand-{{ $brand->id }}">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#16233d]">
                @php($hero = $brand->hero_image_path ?: $brand->fallback_image)
                @if ($hero)
                    <img src="{{ $hero }}" alt="{{ $brand->brand_name }}" class="h-40 w-full object-cover">
                @else
                    <div class="h-24 w-full" style="background: linear-gradient(135deg, {{ $brand->background_color }}, {{ $brand->background_color }}99)"></div>
                @endif
                <div class="p-5">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $brand->brand_name }}</h3>
                        @if ($brand->is_featured)<span class="rounded-full bg-accent/15 px-2 py-0.5 text-[10px] font-bold uppercase text-accent-dark dark:text-accent">Featured</span>@endif
                    </div>
                    @if ($brand->category)<p class="text-xs text-slate-400">{{ $brand->category }}</p>@endif
                    @if ($brand->short_description)<p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $brand->short_description }}</p>@endif

                    @if ($brand->handles->isNotEmpty())
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
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
        </section>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-[var(--brand-card-border-dark)] dark:text-slate-400">
            @if ($platformHandles->isEmpty())No brands to follow yet — check back soon.@else No brands in this category yet.@endif
        </div>
    @endforelse

    {{-- Get-listed CTA (business pitch, distinct from the credit-hunter content). --}}
    <a href="{{ route('brand.get-listed') }}" wire:navigate
       class="mt-4 flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-900 p-5 text-white dark:border-white/10">
        <div>
            <p class="text-sm font-bold">Grow your following. List your brand on {{ \App\Support\BrandSettings::name() }}.</p>
            <p class="mt-0.5 text-xs text-white/70">Real follows from people motivated to follow. See plans →</p>
        </div>
        <x-icon name="chevron-right" class="h-5 w-5 shrink-0" />
    </a>
</div>
