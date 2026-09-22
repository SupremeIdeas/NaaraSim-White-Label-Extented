<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Account deletions</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        GDPR deletion requests awaiting approval. Approving <span class="font-semibold text-red-600 dark:text-red-400">anonymizes</span> the account immediately — the user is deactivated and every PII field is wiped — while wallet/eSIM/SMS/number records are retained under the account's id until the configured retention window elapses, then permanently purged. Only a super admin can approve. See <a href="{{ route('admin.legal-hold') }}" class="underline">Legal hold records</a> to look up a retained trail.
    </p>

    @if ($status)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $status }}
        </div>
    @endif

    @unless ($canApprove)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
            <x-icon name="shield" class="h-4 w-4" /> You can review this queue, but only a super admin may approve a deletion.
        </div>
    @endunless

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        @forelse ($pending as $u)
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-0 dark:border-[#243352]">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $u->name }} <span class="text-slate-400">·</span> <span class="text-slate-500 dark:text-slate-400">{{ $u->email }}</span></p>
                    <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Requested {{ $u->deletion_requested_at?->diffForHumans() }} · user #{{ $u->id }}</p>
                </div>
                @if ($canApprove)
                    <button type="button" wire:click="approve({{ $u->id }})" wire:loading.attr="disabled" wire:target="approve({{ $u->id }})"
                            wire:confirm="Anonymize {{ $u->email }}? Their PII is wiped immediately and cannot be recovered; financial/order records stay retained until the retention window purges them."
                            class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-60 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40">
                        <x-icon name="trash" class="h-4 w-4" /> Approve &amp; anonymize
                    </button>
                @endif
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                <x-icon name="badge-check" class="mx-auto mb-2 h-6 w-6" />
                No pending deletion requests.
            </div>
        @endforelse
    </div>
</div>
