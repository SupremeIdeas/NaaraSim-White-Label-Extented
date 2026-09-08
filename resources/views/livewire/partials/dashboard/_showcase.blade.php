{{-- First-visit value showcase (Theme Batch 2 §2). Reference-matched horizontal
     "Explore" rows: a circular brand-tinted icon, title (+ optional badge), a
     two-line description, and a chevron — stacked full-width. Only for a
     not-yet-active account (@unless hasAny). Same routes/behaviour as before,
     only the presentation changed. --}}
@unless ($hasAny)
    @php($rows = [
        ['naara-connect', 'sim', 'Naara Connect', 'eSIM', 'One eSIM for calls, SMS and high-speed data anywhere in the world.', route('catalogue', ['tab' => 'full'])],
        ['esim-data-plans', 'globe', 'eSIM Data Plans', null, 'Local data in 190+ countries — installed before you fly, connected when you land.', route('catalogue')],
        ['verification-numbers', 'shield-check', 'Verification Numbers', null, 'Receive one-time codes for WhatsApp, Google, Facebook and more — in seconds.', route('numbers')],
        ['virtual-numbers', 'phone', 'Virtual Numbers', null, 'A permanent second line for calls and SMS, without a second phone.', route('numbers')],
    ])
    @if (\App\Support\FeatureFlags::adminEnabled('naara_gift'))
        @php($giftLive = \App\Support\FeatureFlags::configured('naara_gift'))
        @php($rows[] = ['naara-gift', 'gift', 'Naara Gift', $giftLive ? null : 'Soon', 'Send gift cards for 1,000+ brands — delivered instantly by email or WhatsApp.', route('gift-cards')])
    @endif

    <div class="mb-8 space-y-3">
        @foreach ($rows as [$bkey, $icon, $title, $badge, $text, $url])
            <a href="{{ $url }}" wire:navigate
               class="group flex items-center gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-primary/30 hover:shadow-md dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/[0.12] to-teal-400/[0.10] text-primary dark:from-primary/25 dark:to-teal-400/15 dark:text-teal-300">
                    <x-icon name="{{ $icon }}" class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="flex items-center gap-2 font-display text-base font-bold text-slate-900 dark:text-white">
                        {{ $title }}
                        @if ($badge)<span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary dark:bg-teal-500/15 dark:text-teal-300">{{ $badge }}</span>@endif
                    </h3>
                    <p class="mt-0.5 line-clamp-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $text }}</p>
                </div>
                <x-icon name="chevron-right" class="h-5 w-5 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 dark:text-slate-600" />
            </a>
        @endforeach
    </div>
@endunless
