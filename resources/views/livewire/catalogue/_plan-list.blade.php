{{-- A paginated list of plan ROWS: one column on mobile, two on desktop
     (owner request — a tidy list, not a busy grid). Expects: $plans, $fmt. --}}
<div wire:loading.flex wire:target="search,gotoPage,nextPage,previousPage,setView,openCountry,openRegion,openGlobal,setTab" class="hidden">
    <x-ui.skeleton-cards :count="4" class="w-full" columns="grid-cols-1 lg:grid-cols-2" />
</div>

<div wire:loading.remove wire:target="search,gotoPage,nextPage,previousPage,setView,openCountry,openRegion,openGlobal,setTab"
     class="grid grid-cols-1 gap-3 lg:grid-cols-2">
    @forelse ($plans as $plan)
        @include('livewire.catalogue._plan-row', ['plan' => $plan, 'fmt' => $fmt])
    @empty
        <div class="col-span-full rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500 dark:border-[var(--brand-card-border-dark)] dark:text-slate-400">
            <x-icon name="package" class="mx-auto mb-2 h-8 w-8" />
            No plans here yet. Try another tab or search.
        </div>
    @endforelse
</div>

@if ($plans->hasPages())
    <div class="mt-6">{{ $plans->links() }}</div>
@endif
