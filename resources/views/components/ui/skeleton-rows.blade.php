{{-- Reusable skeleton list (Module 32 / owner request — premium loading feel).
     Renders N shimmer rows; drop it inside a wire:loading block so a list shows
     a skeleton while the server round-trips (search, filter, navigate). --}}
@props(['count' => 5, 'avatar' => true])
<div {{ $attributes->merge(['class' => 'divide-y divide-slate-100 dark:divide-[#243352]']) }} aria-hidden="true">
    @for ($i = 0; $i < (int) $count; $i++)
        <div class="flex items-center gap-3 px-4 py-3">
            @if ($avatar)
                <x-ui.skeleton class="h-9 w-9 shrink-0 rounded-full" />
            @endif
            <div class="flex-1 space-y-2">
                <x-ui.skeleton class="h-3 w-1/3 rounded" />
                <x-ui.skeleton class="h-2.5 w-1/2 rounded" />
            </div>
            <x-ui.skeleton class="h-6 w-16 shrink-0 rounded-lg" />
        </div>
    @endfor
</div>
