<div class="mx-auto max-w-4xl">
    @php($input = 'w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Brand directory</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Subscription plans and the self-service brand listings. Admin-placed brands (via Social Hunt) are always featured and sit above these.</p>
    </div>

    @if ($saved)<div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-300">{{ $saved }}</div>@endif

    {{-- Plans --}}
    <section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Subscription plans</h2>
        <div class="space-y-3">
            @foreach ($planModels as $p)
                <div wire:key="plan-{{ $p->id }}" class="rounded-lg border border-slate-100 p-3 dark:border-white/5">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-7">
                        <input wire:model="plans.{{ $p->id }}.name" class="{{ $input }} sm:col-span-2" placeholder="Name">
                        <input wire:model="plans.{{ $p->id }}.price_usd_per_month" type="number" step="1" class="{{ $input }}" placeholder="$/mo">
                        <input wire:model="plans.{{ $p->id }}.handles_included" type="number" class="{{ $input }}" placeholder="Handles">
                        <input wire:model="plans.{{ $p->id }}.guaranteed_followers_per_handle_per_month" type="number" class="{{ $input }}" placeholder="Guar./handle">
                        <input wire:model="plans.{{ $p->id }}.video_previews_allowed" type="number" class="{{ $input }}" placeholder="Videos">
                        <input wire:model="plans.{{ $p->id }}.credit_reward_per_follow" type="number" step="0.5" class="{{ $input }}" placeholder="Credits/follow" title="NaaraCredits earned per handle-follow for brands on this plan">
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <button wire:click="savePlan({{ $p->id }})" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Save</button>
                        <button wire:click="togglePlan({{ $p->id }})" class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $p->is_active ? 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300' : 'bg-slate-100 text-slate-500 dark:bg-white/5' }}">{{ $p->is_active ? 'Active' : 'Archived' }}</button>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-4 sm:grid-cols-7 dark:border-white/5">
            <input wire:model="newPlan.name" class="{{ $input }} sm:col-span-2" placeholder="New plan name">
            <input wire:model="newPlan.price_usd_per_month" type="number" class="{{ $input }}" placeholder="$/mo">
            <input wire:model="newPlan.handles_included" type="number" class="{{ $input }}" placeholder="Handles">
            <input wire:model="newPlan.guaranteed_followers_per_handle_per_month" type="number" class="{{ $input }}" placeholder="Guar./handle">
            <input wire:model="newPlan.video_previews_allowed" type="number" class="{{ $input }}" placeholder="Videos">
            <input wire:model="newPlan.credit_reward_per_follow" type="number" step="0.5" class="{{ $input }}" placeholder="Credits/follow">
            <button wire:click="addPlan" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 sm:col-span-7 dark:border-[#2D4060] dark:text-slate-200">Add plan</button>
        </div>
    </section>

    {{-- Self-service brands --}}
    <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Self-service listings</h2>
        <div class="space-y-2">
            @forelse ($brands as $b)
                <div wire:key="brand-{{ $b->id }}" class="flex items-center gap-3 rounded-lg border border-slate-100 px-3 py-2 dark:border-white/5">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $b->brand_name }} <span class="text-xs text-slate-400">· {{ $b->owner?->email }}</span></p>
                        <p class="text-xs text-slate-400">{{ $b->plan?->name ?? '—' }} · {{ ucwords(str_replace('_',' ',$b->listing_status)) }} · priority {{ $b->priority_score }}
                            @if ($b->subscription) · next {{ $b->subscription->next_billing_at?->format('d M') }} · {{ $b->subscription->status }}@endif
                        </p>
                    </div>
                    @if ($b->listing_status === 'disabled')
                        <button wire:click="restoreBrand({{ $b->id }})" class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-950/40 dark:text-green-300">Restore</button>
                    @else
                        <button wire:click="suspendBrand({{ $b->id }})" wire:confirm="Suspend this listing (policy override)?" class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">Suspend</button>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-400">No self-service brands yet.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $brands->links() }}</div>
    </section>
</div>
