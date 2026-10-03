{{-- List your brand: a business-facing marketing page on the skin system (S3 Batch 6). The hero is a deliberate dark brand panel; the sheet below it carries the skin. --}}
<div>
<x-nx.page>
<div class="ns-pg__hero">
    <span class="ns-pg__kicker">For businesses</span>
    <h1>Grow your following. List your brand on {{ \App\Support\BrandSettings::name() }}.</h1>
    <p>Real {{ \App\Support\BrandSettings::name() }} users actively follow brands to earn credit: genuine engagement from people already motivated to follow, not passive impressions. You get real follows and subscribers on the exact handles you list, plus (on eligible plans) real watch-time on a featured video.</p>
</div>

<div class="ns-pg__sheet">
<div class="ns-pg ns-pg--wide" style="padding:0">

    @if ($error)<div class="ns-pg__err" role="alert" style="margin-top:0">{{ $error }}</div>@endif

    @if ($alreadyListed)
        <div class="ns-pg__callout" style="margin-bottom:20px"><x-nx.icon name="info" /><div><b>You already have a listing.</b><p><a href="{{ route('brand.manage') }}" wire:navigate class="ns-linkink" style="font-weight:600;text-decoration:underline">Manage it here</a>.</p></div></div>
    @endif

    {{-- How it works: the value story in structure, not just prose. --}}
    <div class="ns-pg__cols3" style="margin-bottom:32px">
        <div class="ns-pg__card ns-ring" style="margin-top:0"><span class="ns-tile"><x-nx.icon name="users" /></span><h3 class="ns-pg__h2" style="margin-top:12px">Real people, already motivated</h3><p class="ns-pg__hint">{{ \App\Support\BrandSettings::name() }} users follow your handles to earn NaaraCredits: genuine accounts, genuine intent to follow.</p></div>
        <div class="ns-pg__card ns-ring" style="margin-top:0"><span class="ns-tile"><x-nx.icon name="pin" /></span><h3 class="ns-pg__h2" style="margin-top:12px">A guaranteed monthly target</h3><p class="ns-pg__hint">Every plan guarantees a real follower count per handle per month. Not a hope, a number you can hold us to.</p></div>
        <div class="ns-pg__card ns-ring" style="margin-top:0"><span class="ns-tile"><x-nx.icon name="bars" /></span><h3 class="ns-pg__h2" style="margin-top:12px">Short a target? You get boosted</h3><p class="ns-pg__hint">Miss the guarantee and your listing gets priority placement next cycle, automatically, until it catches up.</p></div>
    </div>

    {{-- Plan comparison: live from the plans table, never a hardcoded list. --}}
    @php($mostPopularIndex = $plans->count() > 2 ? 1 : null)
    <div class="ns-pg__plans">
        @foreach ($plans as $i => $plan)
            @php($cpf = $plan->costPerGuaranteedFollower())
            @php($popular = $i === $mostPopularIndex)
            <div class="ns-pg__plan ns-ring {{ $popular ? 'is-pop' : '' }}">
                @if ($popular)<x-nx.pill variant="gold" class="ns-pg__badge">Most popular</x-nx.pill>@endif
                <h3 class="ns-pg__h2" style="font-size:18px">{{ $plan->name }}</h3>
                <p class="ns-pg__price">${{ number_format($plan->price_usd_per_month, 0) }}<small>/mo</small></p>
                <ul class="ns-pg__ticks">
                    <li><x-nx.icon name="check" /> {{ $plan->handles_included }} social {{ Str::plural('handle', $plan->handles_included) }}</li>
                    <li><x-nx.icon name="check" /> <span><strong>{{ $plan->guaranteed_followers_per_handle_per_month }}</strong> guaranteed follows / handle / month</span></li>
                    <li><x-nx.icon name="check" /> {{ $plan->video_previews_allowed ? $plan->video_previews_allowed.' video preview'.($plan->video_previews_allowed > 1 ? 's' : '') : 'No video previews' }}</li>
                </ul>
                @if ($cpf !== null)<p class="ns-small" style="margin:12px 0 0">≈ ${{ number_format($cpf, 3) }} per guaranteed follower</p>@endif
                <button type="button" wire:click="choose({{ $plan->id }})" wire:loading.attr="disabled" wire:target="choose({{ $plan->id }})" class="ns-cta {{ $popular ? '' : 'ns-cta--ghost' }}" style="margin-top:18px;height:48px;font-size:16px">
                    <span wire:loading.remove wire:target="choose({{ $plan->id }})" class="ns-cta__label">Choose {{ $plan->name }}</span>
                    <span wire:loading wire:target="choose({{ $plan->id }})" class="ns-cta__label"><x-ui.spinner class="h-4 w-4" /> One moment…</span>
                </button>
            </div>
        @endforeach
    </div>

    {{-- The guarantee, explained honestly: an accountability statement, not fine print. --}}
    <div class="ns-pg__card ns-ring" style="margin:32px auto 0;max-width:42rem">
        <h2 class="ns-pg__h2" style="display:flex;align-items:center;gap:8px"><x-nx.icon name="shield" /> Our follower guarantee</h2>
        <p class="ns-sub" style="margin-top:8px">If a handle doesn't hit its guaranteed follows in a month, your listing gets <strong>boosted priority placement</strong> the next cycle, automatically, until it catches up. This is a real, accountable system, not a flat fee for a static listing. The charge renews monthly from your wallet; you can pause or cancel anytime.</p>
    </div>
</div>
</div>
</x-nx.page>
</div>
