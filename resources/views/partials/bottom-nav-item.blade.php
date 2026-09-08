{{-- One slot in the mobile bottom bar. $item may be null (padding slot). --}}
@if ($item)
    <a href="{{ route($item['route']) }}" wire:navigate
       @class([
           'flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium transition',
           'text-primary dark:text-primary' => $isActive($item['route']),
           'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => ! $isActive($item['route']),
       ])>
        {{-- Active item: solid brand colour. Inactive: two-tone brand gradient. --}}
        <span class="relative">
            <x-icon :name="$item['icon']" class="h-5 w-5" :gradient="! $isActive($item['route'])" />
            @if ($item['badge'] ?? null)
                <span class="absolute -right-3 -top-1.5 rounded-full bg-accent px-1 py-px text-[8px] font-bold uppercase leading-tight text-navy">{{ $item['badge'] }}</span>
            @endif
        </span>
        <span class="truncate">{{ $item['label'] }}</span>
    </a>
@else
    <span aria-hidden="true"></span>
@endif
