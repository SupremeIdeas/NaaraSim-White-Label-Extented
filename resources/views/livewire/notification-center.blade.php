<div class="relative">
    {{-- Poll keeps the unread badge current without a websocket server. --}}
    <div wire:poll.30s class="hidden"></div>

    {{-- Bell --}}
    <button type="button" @click="$dispatch('open-modal', { name: 'notifications' })" aria-label="Notifications"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10">
        <x-icon name="bell" class="h-5 w-5" />
        @if ($unread > 0)
            <span class="absolute -right-0.5 -top-0.5 flex min-w-[18px] items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold leading-none text-white"
                  style="height:18px">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    {{-- The ONE modal engine (S31) — a proper slide-up-from-the-bottom sheet on
         mobile (centered dialog on desktop), not the generic pop that used to
         just fade/scale in. Same content as before, same "See all" hand-off
         to the full /notifications page. --}}
    <x-ui.modal name="notifications" title="Notifications" max-width="md">
        @if ($unread > 0)
            <div class="mb-2 flex items-center justify-end">
                <button wire:click="markAllRead" class="text-xs font-medium text-primary hover:underline">Mark all read</button>
            </div>
        @endif

        <div class="-mx-6 max-h-[60vh] overflow-y-auto border-t border-slate-100 dark:border-white/10">
            @forelse ($recent as $note)
                @php $d = $note->data; $isUnread = is_null($note->read_at); @endphp
                <button type="button" wire:click="go('{{ $note->id }}')"
                        class="flex w-full items-start gap-3 border-b border-slate-50 px-6 py-3 text-left transition hover:bg-slate-50 dark:border-white/5 dark:hover:bg-white/5 {{ $isUnread ? 'bg-primary/5 dark:bg-primary/10' : '' }}">
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
                <div class="flex flex-col items-center gap-2 px-6 py-10 text-center text-slate-400">
                    <x-icon name="bell" class="h-8 w-8 opacity-40" />
                    <p class="text-sm">You’re all caught up.</p>
                </div>
            @endforelse
        </div>

        <a href="{{ route('notifications') }}" wire:navigate
           class="-mx-6 -mb-6 mt-0 block border-t border-slate-100 px-6 py-3 text-center text-xs font-medium text-primary hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5 sm:rounded-b-2xl">
            See all notifications
        </a>
    </x-ui.modal>
</div>
