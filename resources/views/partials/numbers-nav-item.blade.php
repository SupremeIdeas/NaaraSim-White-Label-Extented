{{-- One slot in the Numbers section bottom nav (Numbers overhaul §2). Mirrors
     the global bottom-nav-item, plus an unread badge for Messages. --}}
@php($on = $isActive($item['route']))
<a href="{{ route($item['route']) }}" wire:navigate
   class="relative flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium transition {{ $on ? 'text-primary dark:text-teal-300' : 'text-slate-400 dark:text-slate-500' }}">
    <span class="relative">
        <x-icon :name="$item['icon']" class="h-5 w-5" />
        @if (($item['badge'] ?? 0) > 0)
            <span class="absolute -right-2 -top-1.5 min-w-[1rem] rounded-full bg-primary px-1 text-center text-[9px] font-bold leading-4 text-white">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>
        @endif
    </span>
    {{ $item['label'] }}
</a>
