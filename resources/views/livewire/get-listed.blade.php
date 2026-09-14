<div>
{{-- Hero — a real marketing page, not a bare plan-picker (BUILD-9 §9). --}}
<div class="bg-gradient-to-br from-[#0D1B2A] via-[#0D1B2A] to-[#0A6E6E] px-4 pb-16 pt-12 text-white">
    <div class="mx-auto max-w-4xl text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent">For businesses</p>
        <h1 class="mt-3 text-3xl font-bold sm:text-5xl">Grow your following. List your brand on {{ \App\Support\BrandSettings::name() }}.</h1>
        <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-white/75">Real {{ \App\Support\BrandSettings::name() }} users actively follow brands to earn credit — genuine engagement from people already motivated to follow, not passive impressions. You get real follows and subscribers on the exact handles you list, plus (on eligible plans) real watch-time on a featured video.</p>
    </div>
</div>

<div class="mx-auto -mt-8 max-w-4xl rounded-t-[30px] bg-slate-50 px-4 pb-12 pt-8 dark:bg-[#0F1D33]">

    @if ($error)
        <div class="mx-auto mb-6 max-w-xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">{{ $error }}</div>
    @endif

    @if ($alreadyListed)
        <div class="mx-auto mb-6 max-w-xl rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-center text-sm text-primary dark:border-primary/30 dark:bg-primary/10 dark:text-teal-200">
            You already have a listing. <a href="{{ route('brand.manage') }}" wire:navigate class="font-semibold underline">Manage it here</a>.
        </div>
    @endif

    {{-- How it works — 3-step visual, the value story in structure, not just prose. --}}
    <div class="mb-10 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:text-teal-300"><x-icon name="users" class="h-4 w-4" /></div>
            <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">Real people, already motivated</h3>
            <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ \App\Support\BrandSettings::name() }} users follow your handles to earn NaaraCredits — genuine accounts, genuine intent to follow.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:text-teal-300"><x-icon name="target" class="h-4 w-4" /></div>
            <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">A guaranteed monthly target</h3>
            <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Every plan guarantees a real follower count per handle per month — not a hope, a number you can hold us to.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:text-teal-300"><x-icon name="trending-up" class="h-4 w-4" /></div>
            <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">Short a target? You get boosted</h3>
            <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Miss the guarantee and your listing gets priority placement next cycle, automatically, until it catches up.</p>
        </div>
    </div>

    {{-- Plan comparison — live from the plans table, never a hardcoded list. --}}
    @php($mostPopularIndex = $plans->count() > 2 ? 1 : null)
    <div class="grid gap-5 sm:grid-cols-3">
        @foreach ($plans as $i => $plan)
            @php($cpf = $plan->costPerGuaranteedFollower())
            @php($popular = $i === $mostPopularIndex)
            <div class="relative flex flex-col rounded-2xl border p-6 shadow-sm transition {{ $popular ? 'border-primary bg-white ring-2 ring-primary dark:bg-[#16233d]' : 'border-slate-200 bg-white dark:border-white/10 dark:bg-[#16233d]' }}">
                @if ($popular)
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow">Most popular</span>
                @endif
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $plan->name }}</h3>
                <p class="mt-2"><span class="text-3xl font-bold text-slate-900 dark:text-white">${{ number_format($plan->price_usd_per_month, 0) }}</span><span class="text-sm text-slate-400">/mo</span></p>
                <ul class="mt-4 flex-1 space-y-2.5 text-sm text-slate-600 dark:text-slate-300">
                    <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-primary" /> {{ $plan->handles_included }} social {{ Str::plural('handle', $plan->handles_included) }}</li>
                    <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-primary" /> <strong>{{ $plan->guaranteed_followers_per_handle_per_month }}</strong> guaranteed follows / handle / month</li>
                    <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-primary" /> {{ $plan->video_previews_allowed ? $plan->video_previews_allowed.' video preview'.($plan->video_previews_allowed > 1 ? 's' : '') : 'No video previews' }}</li>
                </ul>
                @if ($cpf !== null)
                    <p class="mt-3 text-xs text-slate-400">≈ ${{ number_format($cpf, 3) }} per guaranteed follower</p>
                @endif
                <button wire:click="choose({{ $plan->id }})" wire:loading.attr="disabled" wire:target="choose({{ $plan->id }})"
                        class="mt-5 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition disabled:opacity-60 {{ $popular ? 'bg-primary hover:bg-primary-dark' : 'bg-slate-900 hover:bg-slate-800 dark:bg-white/10 dark:hover:bg-white/20' }}">
                    Choose {{ $plan->name }}
                </button>
            </div>
        @endforeach
    </div>

    {{-- The guarantee, explained honestly — an accountability statement, not fine print. --}}
    <div class="mx-auto mt-10 max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-[#16233d]">
        <div class="flex items-center gap-2">
            <x-icon name="shield-check" class="h-5 w-5 text-primary dark:text-teal-300" />
            <h2 class="font-bold text-slate-900 dark:text-white">Our follower guarantee</h2>
        </div>
        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">If a handle doesn't hit its guaranteed follows in a month, your listing gets <strong>boosted priority placement</strong> the next cycle — automatically, until it catches up. This is a real, accountable system, not a flat fee for a static listing. The charge renews monthly from your wallet; you can pause or cancel anytime.</p>
    </div>
</div>
</div>
