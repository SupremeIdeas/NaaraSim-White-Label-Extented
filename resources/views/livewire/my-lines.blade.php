<div class="mx-auto max-w-5xl">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">My Lines</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Everything you own — eSIMs and numbers, active and archived.</p>
        </div>
        <div class="hidden items-center gap-2 sm:flex">
            <a href="{{ route('catalogue') }}" wire:navigate
               class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 px-3.5 py-2 text-sm font-semibold text-slate-600 transition hover:border-primary/40 hover:text-primary dark:border-[var(--brand-card-border-dark)] dark:text-slate-300 dark:hover:text-teal-300">
                <x-icon name="package" class="h-4 w-4" /> Buy eSIM
            </a>
            <a href="{{ route('numbers') }}" wire:navigate
               class="inline-flex items-center gap-1.5 rounded-full bg-primary px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark">
                <x-icon name="hash" class="h-4 w-4" /> Get number
            </a>
        </div>
    </div>

    {{-- Port-in entry (Prompt 11): bring an existing US/Canada number to Naara.
         Honest — a multi-day carrier process, surfaced where numbers are managed. --}}
    <a href="{{ route('numbers.port-in') }}" wire:navigate
       class="mb-5 flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm transition hover:border-primary/40 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
        <span class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
            <x-icon name="phone-forwarded" class="h-4 w-4 shrink-0 text-primary" />
            Already have a US or Canada number? <span class="font-semibold text-primary">Bring it to Naara</span>
        </span>
        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400" />
    </a>

    @if ($hasAny)
        @include('partials.my-connectivity')
        @include('partials.my-lines-analytics')
    @else
        <div class="rounded-3xl border border-dashed border-slate-300 p-10 text-center dark:border-[var(--brand-card-border-dark)]">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                <x-icon name="signal" class="h-7 w-7" />
            </div>
            <h2 class="mt-4 text-lg font-bold text-slate-900 dark:text-white">Nothing here yet</h2>
            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                Buy an eSIM for data abroad or get a virtual number — they’ll all show up here to manage.
            </p>
            <div class="mt-5 flex items-center justify-center gap-2">
                <a href="{{ route('catalogue') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:border-primary/40 hover:text-primary dark:border-[var(--brand-card-border-dark)] dark:text-slate-300">
                    <x-icon name="package" class="h-4 w-4" /> Browse eSIM plans
                </a>
                <a href="{{ route('numbers') }}" wire:navigate
                   class="inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark">
                    <x-icon name="hash" class="h-4 w-4" /> Get a number
                </a>
            </div>
        </div>
    @endif

    {{-- Modal host so the per-line "Message" action can open the composer. --}}
    @livewire('send-message')
</div>
