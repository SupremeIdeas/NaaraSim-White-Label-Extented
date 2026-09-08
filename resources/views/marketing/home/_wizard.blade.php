{{-- "Meet the Intelligent Wizard" promo (decorative, non-CMS). A branded static
     preview of the real in-app Wizard, side-by-side with the pitch. --}}
<section class="mx-auto max-w-6xl px-4 py-20">
    <div class="grid items-center gap-10 lg:grid-cols-2">
        {{-- Pitch --}}
        <div>
            <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent-dark dark:text-accent">On-demand, on your terms</p>
            <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">
                Stop juggling numbers. Just ask the Wizard.
            </h2>
            <p data-reveal class="mt-4 max-w-lg leading-relaxed text-slate-600 dark:text-slate-300">
                No more hunting for the right number or the best plan. Tell our
                <span class="font-semibold text-primary dark:text-teal-300">NaaraSim Intelligent Wizard</span>
                what you need — a country, a service, a budget — and it outsources the search
                for you, matching your taste to the perfect number or eSIM for any credible
                purpose, in seconds.
            </p>
            <ul data-reveal class="mt-6 space-y-3">
                @foreach ([['zap','Tell it your preference — it does the matching'], ['message-circle','OTP & SMS numbers for any verification'], ['phone','Voice-ready virtual lines, on demand']] as [$icon, $text])
                    <li class="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon name="{{ $icon }}" class="h-4 w-4" /></span>
                        {{ $text }}
                    </li>
                @endforeach
            </ul>
            <a data-reveal href="{{ auth()->check() ? route('dashboard') : route('register') }}"
               class="nx-btn nx-btn--primary mt-8 !px-8 !py-3 !text-base">
                <x-icon name="zap" class="h-4 w-4" /> Try the Wizard free
            </a>
        </div>

        {{-- Wizard preview (styled like the real widget panel) --}}
        <div data-reveal class="relative">
            <div class="pointer-events-none absolute -inset-4 -z-10 rounded-[2rem] bg-gradient-to-br from-primary/20 via-accent/10 to-transparent blur-2xl dark:from-primary/30 dark:via-accent/20" aria-hidden="true"></div>
            <div class="mx-auto max-w-sm overflow-hidden rounded-3xl border border-white/60 bg-white/90 shadow-2xl shadow-slate-900/10 ring-1 ring-black/5 backdrop-blur-xl dark:border-white/10 dark:bg-[#101d33]/90 dark:ring-white/10">
                {{-- Header --}}
                <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4 dark:border-white/10">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary text-white"><x-icon name="zap" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">Ask NaaraSim</p>
                        <p class="text-[11px] text-slate-400">Your intelligent connectivity wizard</p>
                    </div>
                    <span class="ml-auto flex items-center gap-1 text-[11px] font-medium text-green-600 dark:text-green-400"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> online</span>
                </div>
                {{-- Body --}}
                <div class="space-y-3 p-5">
                    <p class="w-fit rounded-2xl rounded-tl-sm bg-slate-100 px-3.5 py-2 text-sm text-slate-700 dark:bg-white/5 dark:text-slate-200">What do you need today?</p>
                    <p class="ml-auto flex w-fit items-center gap-1.5 rounded-2xl rounded-tr-sm bg-primary px-3.5 py-2 text-sm text-white">
                        A US number for WhatsApp
                        <span class="fi fi-us h-3.5 w-5 rounded-[2px] bg-cover ring-1 ring-white/30" role="img" aria-label="USA"></span>
                    </p>
                    <div class="rounded-2xl border border-slate-200 bg-white p-3.5 dark:border-white/10 dark:bg-white/5">
                        <div class="flex items-center gap-2">
                            <span class="fi fi-us h-5 w-7 rounded-[3px] bg-cover shadow-sm ring-1 ring-black/10" role="img" aria-label="USA"></span>
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">Best match found</span>
                            <span class="ml-auto flex items-center gap-2 text-slate-400">
                                <x-icon name="message-circle" class="h-4 w-4" />
                                <x-icon name="phone" class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">SMS + voice · instant OTP · refunded if no code</p>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-lg font-bold text-slate-900 dark:text-white">$1.20</span>
                            <span class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white">Get it now</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
