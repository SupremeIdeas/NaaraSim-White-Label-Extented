<div class="mx-auto max-w-5xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Support tickets</h1>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Conversations the AI handed to a human. Assign one to yourself and reply.</p>

    @if ($flash)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $flash }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[300px_1fr]">
        {{-- Queue --}}
        <div class="space-y-2">
            @forelse ($tickets as $t)
                <button wire:click="select({{ $t->id }})"
                        class="w-full rounded-xl border p-3 text-left transition
                        {{ $selected && $selected->id === $t->id
                            ? 'border-primary bg-primary/5 dark:bg-primary/10'
                            : 'border-slate-200 bg-white hover:bg-slate-50 dark:border-[#2D4060] dark:bg-[#1A2840] dark:hover:bg-[#243352]' }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $t->user->name }}</span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium
                            {{ $t->status === 'assigned' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-slate-100 text-slate-500 dark:bg-[#243352] dark:text-slate-400' }}">
                            {{ $t->status }}
                        </span>
                    </div>
                    <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $t->escalation_reason ?: 'Escalated to a human' }}</p>
                </button>
            @empty
                <div class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400 dark:border-[#2D4060]">No open tickets. Nice.</div>
            @endforelse
        </div>

        {{-- Thread --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            @if (! $selected)
                <div class="flex h-64 items-center justify-center text-sm text-slate-400">Select a ticket to view the conversation.</div>
            @else
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 dark:border-[#2D4060]">
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $selected->user->name }} — {{ $selected->user->email }}</p>
                        <p class="text-xs text-slate-400">
                            {{ $selected->assignee ? 'Assigned to '.$selected->assignee->name : 'Unassigned' }} · {{ $selected->status }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        @if (is_null($selected->assigned_to))
                            <button wire:click="assignToMe({{ $selected->id }})" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Assign to me</button>
                        @endif
                        <button wire:click="resolve({{ $selected->id }})" wire:confirm="Mark this ticket resolved?" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Resolve</button>
                    </div>
                </div>

                @if (! empty($autopilotLog))
                    <div class="mb-3 rounded-lg border border-accent/30 bg-accent/5 p-3">
                        <p class="mb-1 flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-accent">
                            <x-icon name="shield-check" class="h-3.5 w-3.5" /> What the AI already did
                        </p>
                        <ul class="space-y-0.5 text-xs text-slate-600 dark:text-slate-300">
                            @foreach ($autopilotLog as $entry)
                                <li class="flex items-start gap-1">
                                    <x-icon name="check" class="mt-0.5 h-3 w-3 shrink-0 text-accent" />
                                    <span>{{ str_replace('_', ' ', $entry['action'] ?? 'action') }}@if(isset($entry['usd'])) — ${{ number_format((float) $entry['usd'], 2) }} goodwill @endif@if(isset($entry['summary'])): {{ $entry['summary'] }}@endif</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="max-h-[50vh] space-y-3 overflow-y-auto pr-1">
                    @foreach ($thread as $m)
                        <div class="flex {{ $m['role'] === 'user' ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[80%]">
                                <p class="mb-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">
                                    {{ $m['role'] === 'user' ? 'Customer' : ($m['role'] === 'staff' ? 'You / staff' : 'AI agent') }}
                                </p>
                                <div class="whitespace-pre-line rounded-2xl px-3 py-2 text-sm
                                    {{ $m['role'] === 'user'
                                        ? 'bg-slate-100 text-slate-800 dark:bg-[#243352] dark:text-slate-100'
                                        : ($m['role'] === 'staff' ? 'bg-primary text-white' : 'bg-accent/10 text-slate-800 dark:text-slate-100') }}">
                                    {{ $m['body'] }}
                                    @if ($m['voice'])
                                        <audio controls preload="none" src="{{ $m['voice'] }}" class="mt-2 w-full"></audio>
                                    @endif
                                    @if (! empty($m['attachment']))
                                        @if ($m['attachment_image'])
                                            <a href="{{ $m['attachment'] }}" target="_blank" rel="noopener">
                                                <img src="{{ $m['attachment'] }}" alt="{{ $m['attachment_name'] }}" class="mt-2 max-h-56 rounded-lg border border-slate-200 dark:border-[#2D4060]" />
                                            </a>
                                        @else
                                            <a href="{{ $m['attachment'] }}" target="_blank" rel="noopener"
                                               class="mt-2 inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs dark:border-[#2D4060]">
                                                <x-icon name="file-text" class="h-4 w-4" /> {{ $m['attachment_name'] ?: 'Attachment' }}
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <form wire:submit="sendReply" class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 dark:border-[#2D4060]">
                    <input type="text" wire:model="reply" placeholder="Type your reply…"
                           class="flex-1 rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <button type="submit" wire:loading.attr="disabled" wire:target="sendReply"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-white hover:bg-primary-dark disabled:opacity-60">
                        <x-icon name="send" class="h-5 w-5" />
                    </button>
                </form>
                <p class="mt-2 text-[11px] text-slate-400">Replies to paying customers are also delivered as voice if ElevenLabs is configured.</p>
            @endif
        </div>
    </div>
</div>
