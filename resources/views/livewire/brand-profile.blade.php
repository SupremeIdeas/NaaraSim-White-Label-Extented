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
    {{-- nx:allow:start the cover is a photo under a fixed dark scrim with fixed light ink: brand artwork, identical in every skin --}}
    @php($hero = $brandPartner->hero_image_path ?: $brandPartner->fallback_image)
    @php($coverHeight = \App\Support\MailSettings::shouldNudge(auth()->user()) ? 'h-[32rem] lg:h-[36rem]' : 'h-[22rem] lg:h-[27rem]')
    <div class="absolute inset-x-0 top-0 -z-10 w-full overflow-hidden lg:hidden {{ $coverHeight }}">
        @if ($hero)
            <img src="{{ $hero }}" alt="{{ $brandPartner->brand_name }}" class="h-full w-full object-cover">
        @else
            <div class="h-full w-full" style="background: linear-gradient(135deg, {{ $brandPartner->background_color }}, {{ $brandPartner->background_color }}99)"></div>
        @endif
        {{-- ONE scrim, on the photo itself, so it can never stop short of the photo's edges. --}}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-black/5"></div>
    </div>

    {{-- Overlay content sits in normal flow — same box the cover used to be,
         so the back button/name/badge keep the exact geometry (and the tight
         hand-off into the rounded sheet below) that already works; only the
         raw photo behind it moved to the full-bleed layer above. The
         darkening scrim lives here (not on the photo layer) so it always
         lines up with the readable band regardless of what's stacked above. --}}
    <div class="relative -mx-4 h-64 lg:mx-0 lg:mt-2 lg:h-[22rem]">
        {{-- Desktop: a contained cover card with a deep top-left / top-right curve (no photo behind the header, no sharp corners); the sheet below curves up into it. --}}
        <div class="absolute inset-0 hidden overflow-hidden rounded-t-[30px] lg:block">
            @if ($hero)
                <img src="{{ $hero }}" alt="" loading="lazy" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full" style="background: linear-gradient(135deg, {{ $brandPartner->background_color }}, {{ $brandPartner->background_color }}99)"></div>
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-black/5"></div>
        </div>
        <a href="{{ route('rewards.hunt') }}" wire:navigate
           class="absolute left-4 top-4 inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-black/35 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-black/50 lg:left-6 lg:top-6">
            <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" /> The Hunt
        </a>

        @if ($brandPartner->is_featured)
            <span class="absolute right-4 top-4 flex shrink-0 items-center gap-1 rounded-full bg-accent px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wide text-white shadow lg:right-6 lg:top-6">
                <x-icon name="badge-check" class="h-3 w-3" /> Featured
            </span>
        @endif

        <div class="absolute inset-x-4 bottom-10 lg:inset-x-6 lg:bottom-12">
            @if ($brandPartner->category)
                <span class="inline-flex items-center gap-1 rounded-full bg-black/35 px-2.5 py-1 text-[11px] font-semibold text-white ring-1 ring-white/20">
                    <x-icon name="tag" class="h-3 w-3" /> {{ $brandPartner->category }}
                </span>
            @endif
            <h1 class="mt-2 flex flex-wrap items-center gap-2 font-display text-3xl font-extrabold leading-tight text-white sm:text-4xl" style="text-shadow:0 2px 12px rgb(0 0 0 / .55)">
                {{ $brandPartner->brand_name }}
            </h1>
            @if ($brandPartner->short_description)
                <p class="mt-1.5 max-w-lg text-[15px] leading-relaxed text-white" style="text-shadow:0 1px 8px rgb(0 0 0 / .6)">{{ $brandPartner->short_description }}</p>
            @endif
        </div>
    </div>
    {{-- nx:allow:end --}}

    {{-- Sheet "climbs" the cover with a deep, symmetric top-corner curve on both sides: no flat seam anywhere. Skin tokens from here down. --}}
    <div class="ns-pg__sheet -mx-4 lg:mx-0" style="padding:0">
    <x-nx.page class="ns-pg ns-pg--wide" style="padding:28px 16px 0">
        @if ($flash)<div class="ns-bh__flash" role="status" style="margin-top:0">{{ $flash }}</div>@endif

        {{-- Connect: a plain in-platform follow (owner request, 2026-09-22), distinct from following an external handle for a credit reward. --}}
        <div class="ns-pg__kv" style="padding:12px 16px;margin-top:{{ $flash ? '16px' : '0' }}">
            <span style="display:flex;align-items:center;gap:8px;font-weight:600;color:rgb(var(--nx-text))"><x-nx.icon name="users" /> {{ number_format($brandPartner->naara_followers_count) }} Naara {{ \Illuminate\Support\Str::plural('follower', $brandPartner->naara_followers_count) }}</span>
            <button type="button" wire:click="toggleConnect" wire:loading.attr="disabled" wire:target="toggleConnect" class="ns-bh__connect {{ $isFollowing ? 'is-on' : '' }}" aria-pressed="{{ $isFollowing ? 'true' : 'false' }}" style="padding:7px 16px;font-size:13.5px">
                <x-nx.icon :name="$isFollowing ? 'check' : 'plus'" style="font-size:14px" /> {{ $isFollowing ? 'Connected' : 'Connect' }}
            </button>
        </div>

        {{-- Follow everywhere: every active handle, same open-then-confirm claim as the directory card (server-granted, never trusted from the client). --}}
        @if ($brandPartner->handles->isNotEmpty())
            <section class="ns-pg__card ns-ring" style="margin-top:24px">
                <h2 class="ns-bh__h"><x-nx.icon name="star" /> Follow {{ $brandPartner->brand_name }}</h2>
                <div class="ns-bh__grid">
                    @foreach ($brandPartner->handles as $h)
                        @include('livewire.partials.hunt-handle-card', ['handle' => $h, 'claimed' => isset($claimedBrandHandles[$h->id]), 'action' => 'followBrandHandle'])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Last post per handle: an admin-curated teaser (thumbnail + caption), never a live API pull, so it can't silently break when a platform changes its API. --}}
        @php($withPosts = $brandPartner->handles->filter->hasLastPost())
        @if ($withPosts->isNotEmpty())
            <section class="ns-bh__section">
                <h2 class="ns-bh__h"><x-nx.icon name="chat" /> Latest from {{ $brandPartner->brand_name }}</h2>
                <div class="ns-bh__cards">
                    @foreach ($withPosts as $h)
                        <a href="{{ $h->last_post_url ?: $h->handle_url }}" target="_blank" rel="noopener" class="ns-bh__post ns-ring">
                            @if ($h->last_post_image_path)<img src="{{ $h->last_post_image_path }}" alt="">@endif
                            <div class="ns-bh__postbody">
                                <span class="ns-bh__icon" style="width:32px;height:32px;border-radius:9px"><x-service-icon :slug="$h->platform" class="h-4 w-4" /></span>
                                <div style="min-width:0;flex:1">
                                    <small class="ns-small" style="display:block;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $h->handle_label }}@if ($h->last_post_at) · {{ $h->last_post_at->diffForHumans() }}@endif</small>
                                    @if ($h->last_post_caption)<p style="margin:4px 0 0;font-size:15px;line-height:1.45;color:rgb(var(--nx-text))">{{ $h->last_post_caption }}</p>@endif
                                    <span class="ns-pg__act ns-pg__act--link" style="margin-top:8px">View post <x-nx.icon name="chev" style="font-size:13px" /></span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Featured images: a small curated gallery, separate from the single card-listing hero above. --}}
        @if ($brandPartner->images->isNotEmpty())
            <section class="ns-bh__section">
                <h2 class="ns-bh__h"><x-nx.icon name="globe" /> Featured</h2>
                <div class="ns-bh__gallery">
                    @foreach ($brandPartner->images as $img)
                        <figure wire:key="img-{{ $img->id }}">
                            <img src="{{ $img->image_path }}" alt="{{ $img->caption }}">
                            @if ($img->caption)<figcaption>{{ $img->caption }}</figcaption>@endif
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif
    </x-nx.page>
    </div>
</div>
