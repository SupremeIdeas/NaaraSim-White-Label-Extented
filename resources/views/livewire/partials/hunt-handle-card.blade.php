{{-- One follow-to-earn handle card (BUILD-6 §C). Open the handle, then confirm to
     claim. Verified vs self-confirmed is tagged honestly; a claimed card is
     greyed and re-checked server-side ($claimed). --}}
@php
    $verified = ($handle->verification ?? 'self') === 'api';
@endphp
<div x-data="{ opened: false }"
     class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-white/10">
        <x-service-icon :slug="$handle->platform" class="h-5 w-5" />
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $handle->handle_label }}</p>
        <p class="text-[11px] font-medium {{ $verified ? 'text-primary dark:text-teal-300' : 'text-slate-400' }}">
            {{ $verified ? 'Verified automatically' : 'Self-confirmed' }}
        </p>
    </div>

    @if ($claimed)
        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-green-100 px-3 py-1.5 text-xs font-semibold text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="check" class="h-3.5 w-3.5" /> Followed
        </span>
    @else
        {{-- Step 1: open the handle. Step 2: confirm the follow → server grant. --}}
        <a x-show="!opened" href="{{ $handle->handle_url }}" target="_blank" rel="noopener"
           @click="opened = true"
           class="shrink-0 rounded-full bg-primary px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-dark">
            Follow
        </a>
        <button x-show="opened" x-cloak type="button"
                wire:click="{{ $action }}({{ $handle->id }})"
                wire:loading.attr="disabled" wire:target="{{ $action }}({{ $handle->id }})"
                class="shrink-0 rounded-full bg-accent px-3.5 py-1.5 text-xs font-semibold text-navy transition hover:brightness-105 disabled:opacity-60">
            I followed — claim
        </button>
    @endif
</div>
