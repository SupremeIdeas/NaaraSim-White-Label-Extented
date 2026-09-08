<x-layouts.app :title="'Welcome to '.$appName">
    {{-- First-run onboarding carousel. Portrait slides, swipe or Next, final
         slide → login. A localStorage flag skips it on later opens. --}}
    <div class="fixed inset-0 z-50 flex flex-col bg-navy text-white"
        x-data="{
            i: 0,
            count: {{ count($slides) }},
            init() {
                // Already onboarded → go straight to login.
                if (localStorage.getItem('nx_onboarded')) { this.finish(true); }
            },
            next() { if (this.i < this.count - 1) { this.i++; } else { this.finish(); } },
            skip() { this.finish(); },
            finish(silent) { try { localStorage.setItem('nx_onboarded', '1'); } catch (e) {} window.location.href = @js(route('login')); },
            touchStart(e) { this._x = e.changedTouches[0].clientX; },
            touchEnd(e) {
                const dx = e.changedTouches[0].clientX - this._x;
                if (dx < -40) { this.next(); }
                else if (dx > 40 && this.i > 0) { this.i--; }
            }
        }"
        @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)">

        {{-- Skip --}}
        <div class="flex items-center justify-between p-5">
            <x-brand-logo variant="family" theme="dark" size="sm" fallback-icon="signal" />
            <button type="button" @click="skip()" class="text-sm font-medium text-white/70 hover:text-white">Skip</button>
        </div>

        {{-- Slides --}}
        <div class="relative flex-1 overflow-hidden">
            @foreach ($slides as $idx => $slide)
                <div class="absolute inset-0 flex flex-col transition-opacity duration-500"
                     x-show="i === {{ $idx }}" x-transition.opacity>
                    <div class="relative flex-1 overflow-hidden">
                        <img src="{{ $slide['image'] }}" alt="" class="h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-navy via-navy/40 to-transparent"></div>
                    </div>
                    <div class="px-8 pb-6 text-center">
                        <h2 class="text-2xl font-bold">{{ $slide['title'] ?? '' }}</h2>
                        @if (! empty($slide['subtitle']))
                            <p class="mx-auto mt-2 max-w-sm text-sm text-white/70">{{ $slide['subtitle'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Dots + CTA --}}
        <div class="p-6">
            <div class="mb-5 flex justify-center gap-2">
                @foreach ($slides as $idx => $slide)
                    <span class="h-1.5 rounded-full transition-all" :class="i === {{ $idx }} ? 'w-6 bg-white' : 'w-1.5 bg-white/30'"></span>
                @endforeach
            </div>
            <button type="button" @click="next()"
                class="w-full rounded-full bg-white px-6 py-3.5 text-sm font-semibold text-navy transition hover:bg-white/90">
                <span x-show="i < count - 1">Next</span>
                <span x-show="i === count - 1">Get started</span>
            </button>
        </div>
    </div>
</x-layouts.app>
