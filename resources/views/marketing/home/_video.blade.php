{{-- Homepage video section (BUILD-3 §8) — admin-managed entries, each a
     self-hosted upload or a YouTube clip with a poster + orientation. Tapping a
     card opens ONE modal player (brand frame, no page chrome); the player is
     lazy — the <iframe>/<video> is only created when the modal opens (x-if),
     and torn down on close so playback stops. Self-hides when there are no
     active entries. --}}
@if (\App\Support\HomeVideos::isVisible())
    <section class="bg-[#F8F9FA] py-16 sm:py-24 dark:bg-[#0B1626]"
             x-data="{ show: false, kind: '', src: '', title: '', orient: 'landscape',
                       play(d) { this.kind = d.kind; this.src = d.src; this.title = d.title; this.orient = d.orient; this.show = true; document.body.style.overflow = 'hidden'; },
                       close() { this.show = false; this.src = ''; document.body.style.overflow = ''; } }"
             @keydown.escape.window="close()">
        <div class="mx-auto max-w-6xl px-4">
            <div class="mx-auto mb-10 max-w-2xl text-center">
                <h2 class="font-display text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ \App\Support\HomeVideos::heading() }}</h2>
                <p class="mt-3 text-slate-600 dark:text-slate-300">{{ \App\Support\HomeVideos::subheading() }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach (\App\Support\HomeVideos::active() as $v)
                    <button type="button"
                            data-kind="{{ $v->source_type === 'youtube' ? 'youtube' : 'upload' }}"
                            data-src="{{ $v->source_type === 'youtube' ? $v->youTubeEmbedUrl() : $v->video_url }}"
                            data-title="{{ $v->title }}"
                            data-orient="{{ $v->orientation }}"
                            @click="play($el.dataset)"
                            class="group relative block overflow-hidden rounded-2xl border border-slate-200 bg-slate-900 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-white/10
                                   {{ $v->orientation === 'portrait' ? 'aspect-[9/16]' : 'aspect-video' }}">
                        @if ($v->posterOrFallback())
                            <img src="{{ $v->posterOrFallback() }}" alt="{{ $v->title }}" loading="lazy" decoding="async"
                                 class="absolute inset-0 h-full w-full object-cover opacity-90 transition group-hover:scale-105 group-hover:opacity-100">
                        @endif
                        <span class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></span>
                        <span class="absolute left-1/2 top-1/2 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-primary shadow-lg backdrop-blur transition group-hover:scale-110 group-hover:bg-accent group-hover:text-navy">
                            <x-icon name="play" class="h-6 w-6" />
                        </span>
                        <span class="absolute inset-x-0 bottom-0 p-3 text-left text-sm font-semibold text-white">{{ $v->title }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- One modal player for the whole section. --}}
        <div x-show="show" x-cloak x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/90" @click="close()"></div>
            <div class="relative w-full" :class="orient === 'portrait' ? 'max-w-sm' : 'max-w-3xl'">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <p class="truncate text-sm font-semibold text-white" x-text="title"></p>
                    <button type="button" @click="close()" aria-label="Close" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>
                <div class="overflow-hidden rounded-2xl bg-black shadow-2xl ring-1 ring-white/10"
                     :class="orient === 'portrait' ? 'aspect-[9/16]' : 'aspect-video'">
                    {{-- Lazy: only mounted while the modal is open. --}}
                    <template x-if="show && kind === 'youtube'">
                        <iframe :src="src" title="Video" class="h-full w-full" frameborder="0"
                                allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                    </template>
                    <template x-if="show && kind === 'upload'">
                        {{-- Self-hosted: native controls tinted to the brand (progress
                             bar), inside a clean brand frame — no page chrome. --}}
                        <video :src="src" class="h-full w-full bg-black" controls autoplay playsinline
                               style="accent-color: var(--nia-glow-1, #0A6E6E)"></video>
                    </template>
                </div>
            </div>
        </div>
    </section>
@endif
