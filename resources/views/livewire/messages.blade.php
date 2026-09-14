{{-- Conversation inbox (Numbers overhaul §1/§6). Two-pane on desktop; single-
     pane-with-navigation on mobile. Replies reuse the existing SendMessage modal. --}}
<div class="mx-auto max-w-5xl px-4 py-6" x-data="{ hasActive: @js((bool) $active) }"
     x-init="$wire.$watch('active', v => hasActive = !!v)">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Messages</h1>
        <button type="button" wire:click="$dispatch('open-send-message', { to: '', name: '' })"
                class="nx-btn nx-btn--primary !py-2 !text-sm">
            <x-icon name="send" class="h-4 w-4" /> New message
        </button>
    </div>

    <div class="grid gap-4 lg:grid-cols-[20rem_1fr]">
        {{-- Thread list --}}
        <div :class="hasActive ? 'hidden lg:block' : 'block'"
             class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-[#0f2030]">
            @forelse ($threads as $thread)
                <button type="button" wire:key="thread-{{ $thread->id }}" wire:click="openThread('{{ $thread->counterpart_number }}')"
                        @class([
                            'flex w-full items-center gap-3 border-b border-slate-100 px-4 py-3 text-left transition last:border-0 dark:border-white/5',
                            'bg-primary/5 dark:bg-primary/10' => $active === $thread->counterpart_number,
                            'hover:bg-slate-50 dark:hover:bg-white/5' => $active !== $thread->counterpart_number,
                        ])>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-teal-500/15 dark:text-teal-300">
                        <x-icon name="message-circle" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $thread->counterpart_number }}</span>
                            <span class="shrink-0 text-[11px] text-slate-400">{{ optional($thread->last_at)->diffForHumans(null, true) }}</span>
                        </span>
                        <span class="mt-0.5 flex items-center justify-between gap-2">
                            <span class="truncate text-xs text-slate-500 dark:text-slate-400">
                                @if ($thread->last_direction === 'out')<span class="text-slate-400">You: </span>@endif{{ $thread->last_body }}
                            </span>
                            @if ($thread->unread_count > 0)
                                <span class="shrink-0 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $thread->unread_count }}</span>
                            @endif
                        </span>
                    </span>
                </button>
            @empty
                <div class="px-4 py-16 text-center text-sm text-slate-400 dark:text-slate-500">
                    <x-icon name="inbox" class="mx-auto mb-2 h-6 w-6" />
                    No conversations yet. Messages you send and receive on your Naara Lines appear here.
                </div>
            @endforelse
        </div>

        {{-- Active thread --}}
        <div :class="hasActive ? 'block' : 'hidden lg:block'"
             class="flex min-h-[24rem] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-[#0f2030]">
            @if ($active)
                <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 dark:border-white/5">
                    <button type="button" wire:click="$set('active', null)" class="lg:hidden text-slate-400 hover:text-primary">
                        <x-icon name="chevron-right" class="h-5 w-5 rotate-180" />
                    </button>
                    <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $active }}</span>
                    <a href="{{ route('numbers.dialer', ['to' => $active]) }}" wire:navigate class="ml-auto text-green-500" aria-label="Call">
                        <x-icon name="phone" class="h-4 w-4" />
                    </a>
                </div>

                <div class="flex-1 space-y-2 overflow-y-auto p-4">
                    @foreach ($timeline as $m)
                        <div class="flex {{ $m->direction === 'out' ? 'justify-end' : 'justify-start' }}">
                            <div @class([
                                'max-w-[75%] rounded-2xl px-3.5 py-2 text-sm',
                                'bg-primary text-white' => $m->direction === 'out',
                                'bg-slate-100 text-slate-800 dark:bg-white/10 dark:text-slate-100' => $m->direction === 'in',
                            ])>
                                @if ($m->attachment_url && $m->is_voicemail)
                                    <audio controls preload="none" class="mb-1 w-full max-w-[220px]" src="{{ $m->attachment_url }}"></audio>
                                @elseif ($m->attachment_url)
                                    <img src="{{ $m->attachment_url }}" alt="attachment" class="mb-1 max-h-40 rounded-lg">
                                @endif
                                <p class="whitespace-pre-wrap break-words">{{ $m->body }}</p>
                                <p class="mt-0.5 text-[10px] {{ $m->direction === 'out' ? 'text-white/70' : 'text-slate-400' }}">{{ optional($m->at)->format('M j, g:i a') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 p-3 dark:border-white/5">
                    <button type="button" wire:click="$dispatch('open-send-message', { to: '{{ $active }}', name: '' })"
                            class="nx-btn nx-btn--primary w-full justify-center !py-2.5 !text-sm">
                        <x-icon name="send" class="h-4 w-4" /> Reply
                    </button>
                </div>
            @else
                <div class="m-auto px-4 py-16 text-center text-sm text-slate-400 dark:text-slate-500">
                    <x-icon name="message-circle" class="mx-auto mb-2 h-7 w-7" />
                    Select a conversation to read and reply.
                </div>
            @endif
        </div>
    </div>

    {{-- Reply composer (reuses the existing, "nice and perfect" send modal). --}}
    @livewire('send-message')
</div>
