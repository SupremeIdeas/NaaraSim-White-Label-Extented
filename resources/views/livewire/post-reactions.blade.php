{{-- Post reactions (batch 2 §5). Neutral surface tokens so it reads as a quiet
     utility strip, not a coloured call-to-action; the active reaction lifts to
     the brand teal. Every button shows a loading state (platform rule). --}}
<div class="flex flex-wrap items-center gap-2" wire:loading.class="opacity-70">
    @foreach ($types as $key => [$label, $icon])
        @php($count = $counts[$key] ?? 0)
        @php($active = $mine === $key)
        <button type="button"
                wire:click="react('{{ $key }}')"
                wire:loading.attr="disabled"
                aria-pressed="{{ $active ? 'true' : 'false' }}"
                title="{{ $label }}"
                @class([
                    'group inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                    'border-slate-200 bg-slate-50 text-slate-600 hover:border-primary/40 hover:text-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:text-teal-300' => ! $active,
                    'border-primary/50 bg-primary/10 text-primary dark:border-teal-300/40 dark:bg-teal-300/10 dark:text-teal-300' => $active,
                ])>
            <x-icon :name="$icon" class="h-4 w-4 transition group-hover:scale-110" />
            <span>{{ $label }}</span>
            @if ($count > 0)
                <span class="tabular-nums opacity-80">{{ $count }}</span>
            @endif
        </button>
    @endforeach

    @if ($total > 0)
        <span class="ml-1 text-xs text-slate-400 dark:text-slate-500">{{ $total }} {{ \Illuminate\Support\Str::plural('reaction', $total) }}</span>
    @endif
</div>
