<div class="mx-auto max-w-2xl px-4 py-6">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Notifications</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Everything that concerns your account, in one place.</p>
        </div>
        @if ($unread > 0)
            <button wire:click="markAllRead"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:text-slate-300 dark:hover:bg-[#243352]">
                Mark all read
            </button>
        @endif
    </div>

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'All', 'unread' => 'Unread', 'offer' => 'Offers', 'order' => 'Orders', 'wallet' => 'Wallet', 'support' => 'Support', 'security' => 'Security'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $filter === $key ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#243352] dark:text-slate-300 dark:hover:bg-[#2D4060]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse ($notifications as $note)
            @php $d = $note->data; $isUnread = is_null($note->read_at); @endphp
            <div wire:key="n-{{ $note->id }}"
                 class="flex items-start gap-3 rounded-xl border p-4 {{ $isUnread ? 'border-primary/30 bg-primary/5 dark:bg-primary/10' : 'border-slate-200 bg-white dark:border-[var(--brand-card-border-dark)] dark:bg-[#1B2A44]' }}">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                    <x-icon :name="$d['icon'] ?? 'bell'" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $d['title'] ?? 'Notification' }}</p>
                    @if (! empty($d['body']))
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $d['body'] }}</p>
                    @endif
                    <div class="mt-2 flex items-center gap-3">
                        <span class="text-xs text-slate-400">{{ $note->created_at->diffForHumans() }}</span>
                        @if (! empty($d['action_url']))
                            <button wire:click="go('{{ $note->id }}')" class="text-xs font-semibold text-primary hover:underline">
                                {{ $d['action_label'] ?? 'View' }} →
                            </button>
                        @endif
                        @if ($isUnread)
                            <button wire:click="markAsRead('{{ $note->id }}')" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">Mark read</button>
                        @endif
                    </div>
                </div>
                @if ($isUnread)
                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 py-16 text-center text-slate-400 dark:border-[var(--brand-card-border-dark)]">
                <x-icon name="bell" class="h-10 w-10 opacity-40" />
                <p class="text-sm">Nothing here yet.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
