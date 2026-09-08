<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
    {{-- Poll keeps the unread badge current without a websocket server. --}}
    <div wire:poll.30s class="hidden"></div>

    {{-- Bell --}}
    <button type="button" @click="open = !open" aria-label="Notifications"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10">
        <x-icon name="bell" class="h-5 w-5" />
        @if ($unread > 0)
            <span class="absolute -right-0.5 -top-0.5 flex min-w-[18px] items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold leading-none text-white"
                  style="height:18px">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    {{-- Mobile backdrop — the panel is a bottom sheet on phones (BUILD-3 §6.1);
         sm+ keeps the right-anchored dropdown and needs no backdrop. --}}
    <div x-show="open" x-cloak @click="open = false" x-transition.opacity
         class="fixed inset-0 z-40 bg-black/30 sm:hidden"></div>

    {{-- Panel: full-width bottom sheet on mobile (anchored to the viewport, so it
         never overflows off the left edge of a narrow header), right-anchored
         dropdown from sm up. --}}
    <div x-show="open" x-cloak @click.outside="open = false" x-transition
         class="fixed inset-x-0 bottom-0 z-50 max-h-[85vh] w-full overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-xl dark:border-[var(--brand-card-border-dark)] dark:bg-[#1B2A44]
                sm:absolute sm:inset-x-auto sm:bottom-auto sm:right-0 sm:mt-2 sm:w-80 sm:max-w-[92vw] sm:rounded-2xl">
        {{-- Grab handle (mobile only). --}}
        <div class="flex justify-center pt-2 sm:hidden"><span class="h-1 w-10 rounded-full bg-slate-300 dark:bg-white/20"></span></div>
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-white/10">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Notifications</p>
            @if ($unread > 0)
                <button wire:click="markAllRead" class="text-xs font-medium text-primary hover:underline">Mark all read</button>
            @endif
        </div>

        <div class="max-h-[60vh] overflow-y-auto">
            @forelse ($recent as $note)
                @php $d = $note->data; $isUnread = is_null($note->read_at); @endphp
                <button type="button" wire:click="go('{{ $note->id }}')"
                        class="flex w-full items-start gap-3 border-b border-slate-50 px-4 py-3 text-left transition hover:bg-slate-50 dark:border-white/5 dark:hover:bg-white/5 {{ $isUnread ? 'bg-primary/5 dark:bg-primary/10' : '' }}">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                        <x-icon :name="$d['icon'] ?? 'bell'" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">{{ $d['title'] ?? 'Notification' }}</span>
                        @if (! empty($d['body']))
                            <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $d['body'] }}</span>
                        @endif
                        <span class="mt-1 flex items-center gap-2">
                            <span class="text-[11px] text-slate-400">{{ $note->created_at->diffForHumans() }}</span>
                            @if (! empty($d['action_label']))
                                <span class="text-[11px] font-medium text-primary">{{ $d['action_label'] }} →</span>
                            @endif
                        </span>
                    </span>
                    @if ($isUnread)
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                    @endif
                </button>
            @empty
                <div class="flex flex-col items-center gap-2 px-4 py-10 text-center text-slate-400">
                    <x-icon name="bell" class="h-8 w-8 opacity-40" />
                    <p class="text-sm">You’re all caught up.</p>
                </div>
            @endforelse
        </div>

        <a href="{{ route('notifications') }}" wire:navigate
           class="block border-t border-slate-100 px-4 py-3 text-center text-xs font-medium text-primary hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5">
            See all notifications
        </a>
    </div>
</div>
