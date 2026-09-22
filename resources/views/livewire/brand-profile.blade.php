<div>
    {{-- Cover (owner request, 2026-09-22): a true Facebook/Twitter-style
         cover — edge-to-edge across the whole viewport, no side gutters, and
         bleeding up behind the sticky header. `app-shell.blade.php` never
         gives anything between here and <body> a `position`, so this layer
         escapes <main>'s own max-w-6xl/px-4 box entirely and resolves
         against the true page edges. `-z-10` drops it into the CSS
         "background" paint step, so it renders behind ordinary in-flow
         content too (the email-verification banner above, the rounded sheet
         below) without hiding either — only the explicitly-stacked header
         (z-30) and this cover's own overlay layer sit in front of it.

         Height MUST track the real header+banner+overlay stack closely, not
         just be "generously tall": because this layer reaches the true
         viewport edge while the sheet below stays inset inside <main>'s own
         padding, any excess height shows as a raw, un-scrimmed strip of
         photo down the page's side gutters wherever it runs past the
         overlay's real bottom edge (caught after shipping the first, overly
         generous version — a fixed h-[40rem] leaked badly on mobile). Values
         below are measured header (64px both breakpoints) + the
         verify-email banner's real rendered height (~110px wrapped on a
         narrow phone, ~90px on one line desktop) + <main>'s own top padding
         + the overlay box height, each with a small buffer for text-wrap
         variance — not a guess-tall-and-hope number.
         `lg:left-72`/`lg:!left-24` (bound to the same `navCollapsed` store
         app-shell.blade.php already exposes on this element's ancestor):
         the fixed desktop sidebar's own top fade is deliberately
         translucent against the plain page background it normally sits on,
         so left unconstrained a full-bleed photo bleeds straight through
         it — stopping the cover at the sidebar's own edge avoids that
         without touching the sidebar itself. --}}
    @php($hero = $brandPartner->hero_image_path ?: $brandPartner->fallback_image)
    @php($coverHeight = \App\Support\MailSettings::shouldNudge(auth()->user()) ? 'h-[32rem] lg:h-[36rem]' : 'h-[22rem] lg:h-[27rem]')
    <div class="absolute inset-x-0 top-0 -z-10 w-full overflow-hidden lg:left-72 lg:w-auto {{ $coverHeight }}"
         :class="navCollapsed ? 'lg:!left-24' : 'lg:!left-72'">
        @if ($hero)
            <img src="{{ $hero }}" alt="{{ $brandPartner->brand_name }}" class="h-full w-full object-cover">
        @else
            <div class="h-full w-full" style="background: linear-gradient(135deg, {{ $brandPartner->background_color }}, {{ $brandPartner->background_color }}99)"></div>
        @endif
    </div>

    {{-- Overlay content sits in normal flow — same box the cover used to be,
         so the back button/name/badge keep the exact geometry (and the tight
         hand-off into the rounded sheet below) that already works; only the
         raw photo behind it moved to the full-bleed layer above. The
         darkening scrim lives here (not on the photo layer) so it always
         lines up with the readable band regardless of what's stacked above. --}}
    <div class="relative h-64 w-full lg:h-80">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-black/5"></div>

        <a href="{{ route('rewards.hunt') }}" wire:navigate
           class="absolute left-4 top-4 inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-black/30 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-md transition hover:bg-black/45 lg:left-8 lg:top-6">
            <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" /> The Hunt
        </a>

        @if ($brandPartner->is_featured)
            <span class="absolute right-4 top-4 flex shrink-0 items-center gap-1 rounded-full bg-accent px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wide text-white shadow lg:right-8 lg:top-6">
                <x-icon name="badge-check" class="h-3 w-3" /> Featured
            </span>
        @endif

        {{-- Name + description sit on a frosted glass panel, not directly on
             the gradient — owner request: readable "no matter the colour of
             the image in cover" (a busy/light photo can beat a plain
             gradient's contrast in places; a semi-opaque backdrop can't). --}}
        <div class="absolute inset-x-4 bottom-5 rounded-2xl bg-black/35 px-4 py-3.5 backdrop-blur-md lg:inset-x-8 lg:px-5">
            @if ($brandPartner->category)
                <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold text-white">
                    <x-icon name="tag" class="h-3 w-3" /> {{ $brandPartner->category }}
                </span>
            @endif
            <h1 class="mt-2 font-display text-2xl font-extrabold leading-tight text-white drop-shadow-sm sm:text-3xl">{{ $brandPartner->brand_name }}</h1>
            @if ($brandPartner->short_description)
                <p class="mt-1.5 max-w-lg text-sm leading-relaxed text-white/90">{{ $brandPartner->short_description }}</p>
            @endif
        </div>
    </div>

    {{-- Sheet "climbs" the cover with a deep, symmetric top-corner curve on
         both sides — no flat/sharp seam anywhere (CLAUDE.md §5) — pulled up
         further than the platform's usual -mt-6 so the curve reads
         deliberately, not incidental. --}}
    <div class="relative -mt-8 rounded-t-[30px] bg-white pt-7 dark:bg-[#0F1D33]">
        @if ($flash)
            <div class="mb-5 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm font-semibold text-primary dark:border-primary/30 dark:bg-primary/10 dark:text-teal-200">{{ $flash }}</div>
        @endif

        {{-- Connect — a plain in-platform follow (owner request, 2026-09-22),
             distinct from following an external handle for a credit reward:
             no reward here, just a relationship + a Naara follower count. --}}
        <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
            <p class="flex items-center gap-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <x-icon name="users" class="h-4 w-4 text-accent-dark dark:text-accent" />
                {{ number_format($brandPartner->naara_followers_count) }} Naara {{ \Illuminate\Support\Str::plural('follower', $brandPartner->naara_followers_count) }}
            </p>
            <button type="button" wire:click="toggleConnect" wire:loading.attr="disabled" wire:target="toggleConnect"
                    @class([
                        'inline-flex shrink-0 items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition disabled:opacity-60',
                        'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-950/40 dark:text-green-300 dark:hover:bg-green-950/60' => $isFollowing,
                        'bg-primary text-white hover:bg-primary-dark' => ! $isFollowing,
                    ])>
                <x-icon :name="$isFollowing ? 'check' : 'user-plus'" class="h-3.5 w-3.5" />
                {{ $isFollowing ? 'Connected' : 'Connect' }}
            </button>
        </div>

        {{-- Follow everywhere — every active handle, same open-then-confirm
             claim as the directory card (server-granted, never trusted from
             the client). Wrapped in its own card (matching the platform
             band's own treatment on the Hunt directory) so it doesn't feel
             like a bare grid floating in white space. --}}
        @if ($brandPartner->handles->isNotEmpty())
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
                <h2 class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    <x-icon name="sparkles" class="h-3.5 w-3.5 text-accent-dark dark:text-accent" /> Follow {{ $brandPartner->brand_name }}
                </h2>
                <div class="grid gap-2.5 sm:grid-cols-2">
                    @foreach ($brandPartner->handles as $h)
                        @include('livewire.partials.hunt-handle-card', ['handle' => $h, 'claimed' => isset($claimedBrandHandles[$h->id]), 'action' => 'followBrandHandle'])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Last post per handle — an admin-curated teaser (thumbnail +
             caption), never a live API pull, so it can't silently break when
             a platform changes its API. "View post" sends the visitor
             straight out to the brand's own handle, on their platform of
             choice, exactly like the owner asked. --}}
        @php($withPosts = $brandPartner->handles->filter->hasLastPost())
        @if ($withPosts->isNotEmpty())
            <section class="mt-8">
                <h2 class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    <x-icon name="message-circle" class="h-3.5 w-3.5 text-accent-dark dark:text-accent" /> Latest from {{ $brandPartner->brand_name }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($withPosts as $h)
                        <a href="{{ $h->last_post_url ?: $h->handle_url }}" target="_blank" rel="noopener"
                           class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:shadow-md dark:border-white/10 dark:bg-[#16233d]">
                            @if ($h->last_post_image_path)
                                <div class="h-40 w-full overflow-hidden">
                                    <img src="{{ $h->last_post_image_path }}" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                </div>
                            @endif
                            <div class="flex items-start gap-3 p-4">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 dark:bg-white/10">
                                    <x-service-icon :slug="$h->platform" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-semibold text-slate-500 dark:text-slate-400">
                                        {{ $h->handle_label }}
                                        @if ($h->last_post_at)
                                            <span class="text-slate-400 dark:text-slate-500">· {{ $h->last_post_at->diffForHumans() }}</span>
                                        @endif
                                    </p>
                                    @if ($h->last_post_caption)
                                        <p class="mt-1 text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ $h->last_post_caption }}</p>
                                    @endif
                                    <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary dark:text-teal-300">
                                        View post <x-icon name="chevron-right" class="h-3 w-3" />
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Featured images — a small curated gallery, separate from the
             single card-listing hero above. --}}
        @if ($brandPartner->images->isNotEmpty())
            <section class="mb-2 mt-8">
                <h2 class="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                    <x-icon name="image" class="h-3.5 w-3.5 text-accent-dark dark:text-accent" /> Featured
                </h2>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                    @foreach ($brandPartner->images as $img)
                        <figure class="group relative overflow-hidden rounded-2xl" wire:key="img-{{ $img->id }}">
                            <img src="{{ $img->image_path }}" alt="{{ $img->caption }}" class="aspect-square w-full object-cover transition duration-500 group-hover:scale-105">
                            @if ($img->caption)
                                <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-3 py-2 text-xs font-medium text-white opacity-0 transition group-hover:opacity-100">
                                    {{ $img->caption }}
                                </figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
