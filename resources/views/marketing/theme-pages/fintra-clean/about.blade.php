{{-- Per-theme custom About page — "fintra-clean" ("Ledger" persona, Theme
     visual rebuild, 2026-09-07). Persona established on this theme's
     header/bottom-nav/login/landing: slate-blue and champagne-gold, only
     lightly rounded, dense tabular numbers. Structurally different from
     neon-vertex's rotated-photo/card-fan composition, midnight-signal's
     annotated diagram, and aries-contrast's numbered mission/vision rows:
       - Hero: headline/intro on the left, the shared coworking-desk photo
         on the right inside the same receipt-style framed card used on the
         landing hero, with a "Verified" caption row instead of a floating
         badge.
       - Mission/Vision: a literal accounting T-ACCOUNT — two columns,
         "Mission (Dr)" and "Vision (Cr)", separated by a vertical rule,
         never stacked numbered rows.
       - Values: an AUDIT CHECKLIST — a single list of ticked line items
         with monospace reference codes (AUD-01/02/03), never a 3-up card
         grid.
       - Founder: a STAFF-RECORD CARD — a bordered card with its own
         ledger-style header strip ("Staff record" / "No. 001"), never a
         plain two-column block. The initials avatar stays: no photo of
         the founder is on file, and a stock photo mislabelled with his
         name would misrepresent a real person. --}}
<section class="bg-white dark:bg-navy">
    <div class="mx-auto max-w-6xl px-5 py-16 sm:px-6 sm:py-20">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
            <div>
                <span class="inline-flex items-center gap-2 rounded-md border border-primary/20 bg-primary/5 px-3 py-1.5 font-mono text-[11px] font-semibold uppercase tracking-[0.15em] text-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
                    <x-icon name="file-text" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
                </span>
                <h1 class="mt-6 max-w-xl font-display text-4xl font-bold leading-[1.1] text-slate-900 sm:text-5xl dark:text-white">
                    {{ $content['headline'] }}
                </h1>
                <p class="mt-5 max-w-lg text-base leading-relaxed text-slate-600 dark:text-slate-400 sm:text-lg">
                    {{ $content['intro'] }}
                </p>
            </div>

            <div class="relative">
                <div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
                    <img src="{{ asset('images/themes/shared/coworking-desk.webp') }}" alt="" class="h-64 w-full rounded-lg object-cover sm:h-72">
                    <div class="mt-3 flex items-center justify-between gap-3 border-t border-dashed border-slate-200 pt-3 dark:border-white/10">
                        <span class="font-mono text-xs uppercase tracking-wider text-slate-400 dark:text-slate-500">Ref. NS-AUDIT-01</span>
                        <span class="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary dark:bg-white/5 dark:text-slate-200">
                            <x-icon name="check" class="h-2.5 w-2.5" /> Verified
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Mission / Vision as a literal accounting T-account: two columns, Dr / Cr. --}}
<section class="border-t border-slate-100 bg-[#F8F9FA] py-16 dark:border-white/5 dark:bg-[#0c1220] sm:py-20">
    <div class="mx-auto max-w-4xl px-5 sm:px-6">
        <h2 class="text-center font-display text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">The T-account</h2>
        <div class="mt-10 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-navy">
            <div class="grid grid-cols-1 divide-y divide-slate-200 border-b border-slate-200 dark:divide-white/10 dark:border-white/10 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                <p class="flex items-center justify-center gap-2 px-6 py-3 text-center text-xs font-bold uppercase tracking-[0.2em] text-primary">
                    <x-icon name="target" class="h-3.5 w-3.5" /> Mission (Dr)
                </p>
                <p class="flex items-center justify-center gap-2 px-6 py-3 text-center text-xs font-bold uppercase tracking-[0.2em] text-accent-dark dark:text-accent">
                    <x-icon name="eye" class="h-3.5 w-3.5" /> Vision (Cr)
                </p>
            </div>
            <div class="grid grid-cols-1 divide-y divide-slate-200 dark:divide-white/10 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                <p class="p-6 text-sm leading-relaxed text-slate-600 dark:text-slate-400 sm:p-8">{{ $content['mission'] }}</p>
                <p class="p-6 text-sm leading-relaxed text-slate-600 dark:text-slate-400 sm:p-8">{{ $content['vision'] }}</p>
            </div>
        </div>
    </div>
</section>

{{-- Values as an audit checklist — a single list of ticked line items with
     monospace reference codes, never a 3-up card grid. --}}
<section class="bg-white py-16 dark:bg-navy sm:py-20">
    <div class="mx-auto max-w-3xl px-5 sm:px-6">
        <h2 class="text-center font-display text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">Audited line items</h2>
        <div class="mt-10 divide-y divide-slate-200 border-y border-slate-200 dark:divide-white/10 dark:border-white/10">
            @foreach ([
                ['ref' => 'AUD-01', 'icon' => 'shield-check', 'title' => 'Radical transparency', 'body' => 'The price you see is the price you pay — no hidden roaming fees, ever.'],
                ['ref' => 'AUD-02', 'icon' => 'globe', 'title' => 'Built for the continent', 'body' => 'Designed first for African travellers, then extended to 190+ countries.'],
                ['ref' => 'AUD-03', 'icon' => 'badge-check', 'title' => 'Every stat sourced', 'body' => 'Coverage, pricing and uptime numbers are pulled live, never estimated.'],
            ] as $entry)
                <div class="flex items-start gap-4 py-5">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary dark:bg-white/5 dark:text-slate-200">
                        <x-icon :name="$entry['icon']" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $entry['title'] }}</h3>
                            <span class="shrink-0 font-mono text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-600">{{ $entry['ref'] }}</span>
                        </div>
                        <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $entry['body'] }}</p>
                    </div>
                    <x-icon name="check" class="mt-1 h-4 w-4 shrink-0 text-accent" />
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Founder — a staff-record card with its own ledger-style header strip,
     never a plain two-column block. Initials avatar: no photo of the
     founder is on file. --}}
<section class="border-t border-slate-100 bg-[#F8F9FA] py-16 dark:border-white/5 dark:bg-[#0c1220] sm:py-20">
    <div class="mx-auto max-w-2xl px-5 sm:px-6">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-navy">
            <div class="flex items-center justify-between border-b border-dashed border-slate-200 px-6 py-3 dark:border-white/10">
                <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">Staff record</span>
                <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">No. 001</span>
            </div>
            <div class="flex flex-col items-center gap-5 p-8 text-center sm:flex-row sm:text-left">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-md bg-primary text-lg font-bold text-white">
                    {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                </span>
                <div>
                    <p class="font-display text-lg font-bold text-slate-900 dark:text-white">{{ $content['founder_name'] }}</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary dark:text-slate-300">{{ $content['founder_title'] }}</p>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $content['founder_bio'] }}</p>
                </div>
            </div>
        </div>
    </div>
</section>

@include('marketing._reused-sections')
