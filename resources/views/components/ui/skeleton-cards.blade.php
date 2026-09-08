{{-- Card-grid skeleton (owner request — premium loading). Mirrors a plan/card
     grid's shape so there's no layout shift when real cards swap in. Drop inside
     a wire:loading block targeting search/filter/pagination. --}}
@props(['count' => 6, 'columns' => 'sm:grid-cols-2 lg:grid-cols-3'])
<div {{ $attributes->merge(['class' => 'grid gap-5 '.$columns]) }} aria-hidden="true">
    @for ($i = 0; $i < (int) $count; $i++)
        <div class="rounded-2xl border border-slate-200 p-5 dark:border-[var(--brand-card-border-dark)]">
            <div class="flex items-center gap-3">
                <x-ui.skeleton class="h-10 w-10 shrink-0 rounded-xl" />
                <div class="flex-1 space-y-2">
                    <x-ui.skeleton class="h-3 w-2/3 rounded" />
                    <x-ui.skeleton class="h-2.5 w-1/2 rounded" />
                </div>
            </div>
            <x-ui.skeleton class="mt-4 h-3 w-full rounded" />
            <x-ui.skeleton class="mt-2 h-3 w-4/5 rounded" />
            <div class="mt-4 flex items-center justify-between">
                <x-ui.skeleton class="h-6 w-20 rounded-lg" />
                <x-ui.skeleton class="h-8 w-24 rounded-lg" />
            </div>
        </div>
    @endfor
</div>
