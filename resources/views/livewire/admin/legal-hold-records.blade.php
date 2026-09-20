<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Legal hold records</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Read-only lookup of an <span class="font-semibold">anonymized</span> account's retained financial/order trail —
        for a legal hold, chargeback dispute, or AML/regulator request. This never restores the account's name or email;
        that's irreversible once erasure ran. Every lookup requires a case reference and reason and is written to the
        audit log.
    </p>

    @unless ($canView)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
            <x-icon name="shield" class="h-4 w-4" /> Only a super admin may view retained records under legal hold.
        </div>
    @else
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Account id</label>
                    <input type="text" wire:model="userId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#111C31] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Case reference</label>
                    <input type="text" wire:model="caseReference" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#111C31] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Reason</label>
                    <input type="text" wire:model="reason" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#111C31] dark:text-slate-100">
                </div>
            </div>
            <button type="button" wire:click="lookup" wire:loading.attr="disabled" wire:target="lookup"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#0A6E6E] px-4 py-2 text-sm font-medium text-white hover:bg-[#085757] disabled:opacity-60">
                <x-icon name="search" class="h-4 w-4" /> Look up retained records
            </button>
        </div>

        @if ($error)
            <div class="mb-6 flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="shield-alert" class="h-4 w-4" /> {{ $error }}
            </div>
        @endif

        @if ($result)
            <div class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        User #{{ $result['user_id'] }} · anonymized {{ $result['anonymized_at'] }} ·
                        purge due {{ $result['retention_purge_due_at'] }}
                    </p>
                </div>

                @foreach (['wallet_transactions' => 'Wallet transactions', 'esim_orders' => 'eSIM orders', 'sms_orders' => 'SMS orders', 'virtual_numbers' => 'Virtual numbers'] as $key => $label)
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
                        <div class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 dark:border-[#243352] dark:text-slate-200">
                            {{ $label }} ({{ count($result[$key]) }})
                        </div>
                        @forelse ($result[$key] as $row)
                            <div class="border-b border-slate-100 px-5 py-2 text-xs text-slate-500 last:border-0 dark:border-[#243352] dark:text-slate-400">
                                #{{ $row->id }} · {{ $row->created_at }}
                            </div>
                        @empty
                            <div class="px-5 py-4 text-xs text-slate-400 dark:text-slate-500">None retained.</div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endif
    @endunless
</div>
