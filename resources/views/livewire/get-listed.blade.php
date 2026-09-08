<div class="mx-auto max-w-4xl px-4 py-8">
    <div class="text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent-dark dark:text-accent">For businesses</p>
        <h1 class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">Grow your following. List your brand on {{ \App\Support\BrandSettings::name() }}.</h1>
        <p class="mx-auto mt-4 max-w-2xl text-slate-600 dark:text-slate-300">Real {{ \App\Support\BrandSettings::name() }} users actively follow brands to earn credit — genuine engagement from people already motivated to follow, not passive impressions. You get real follows and subscribers on the exact handles you list, plus (on eligible plans) real watch-time on a featured video.</p>
    </div>

    @if ($error)
        <div class="mx-auto mt-6 max-w-xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">{{ $error }}</div>
    @endif

    @if ($alreadyListed)
        <div class="mx-auto mt-6 max-w-xl rounded-xl border border-primary/20 bg-primary/5 px-4 py-3 text-center text-sm text-primary dark:border-primary/30 dark:bg-primary/10 dark:text-teal-200">
            You already have a listing. <a href="{{ route('brand.manage') }}" wire:navigate class="font-semibold underline">Manage it here</a>.
        </div>
    @endif

    {{-- Plan comparison — live from the plans table. --}}
    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        @foreach ($plans as $plan)
            @php($cpf = $plan->costPerGuaranteedFollower())
            <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#16233d]">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $plan->name }}</h3>
                <p class="mt-2"><span class="text-3xl font-bold text-slate-900 dark:text-white">${{ number_format($plan->price_usd_per_month, 0) }}</span><span class="text-sm text-slate-400">/mo</span></p>
                <ul class="mt-4 flex-1 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> {{ $plan->handles_included }} social {{ Str::plural('handle', $plan->handles_included) }}</li>
                    <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> <strong>{{ $plan->guaranteed_followers_per_handle_per_month }}</strong> guaranteed follows / handle / month</li>
                    <li class="flex items-center gap-2"><x-icon name="check" class="h-4 w-4 text-primary" /> {{ $plan->video_previews_allowed ? $plan->video_previews_allowed.' video preview'.($plan->video_previews_allowed > 1 ? 's' : '') : 'No video previews' }}</li>
                </ul>
                @if ($cpf !== null)
                    <p class="mt-3 text-xs text-slate-400">≈ ${{ number_format($cpf, 3) }} per guaranteed follower</p>
                @endif
                <button wire:click="choose({{ $plan->id }})" wire:loading.attr="disabled" wire:target="choose({{ $plan->id }})"
                        class="mt-5 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                    Choose {{ $plan->name }}
                </button>
            </div>
        @endforeach
    </div>

    <div class="mx-auto mt-8 max-w-2xl rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300">
        <h2 class="font-bold text-slate-900 dark:text-white">Our follower guarantee</h2>
        <p class="mt-1.5">If a handle doesn't hit its guaranteed follows in a month, your listing gets <strong>boosted priority placement</strong> the next cycle — automatically, until it catches up. This is a real, accountable system, not a flat fee for a static listing. The charge renews monthly from your wallet; you can pause or cancel anytime.</p>
    </div>
</div>
