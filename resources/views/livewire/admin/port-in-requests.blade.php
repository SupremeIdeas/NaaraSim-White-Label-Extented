<div class="mx-auto max-w-5xl">
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Port-in requests</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Customers bringing a US/Canada number to Naara. Review the carrier details, advance the
            status as the port progresses, or reject with a reason. Account number and PIN are cleared
            automatically once a request closes.
        </p>
    </div>

    @if ($flash)
        <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $flash }}</div>
    @endif

    <div class="mb-4 inline-flex flex-wrap gap-1 rounded-full border border-slate-200 bg-slate-100 p-1 dark:border-[#2D4060] dark:bg-[#152238]">
        <button wire:click="$set('filter', '')" class="rounded-full px-3 py-1.5 text-sm font-semibold transition {{ $filter === '' ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-primary' : 'text-slate-500 dark:text-slate-400' }}">All</button>
        @foreach ($statuses as $status)
            <button wire:click="$set('filter', '{{ $status }}')" class="rounded-full px-3 py-1.5 text-sm font-semibold transition {{ $filter === $status ? 'bg-white text-primary shadow-sm dark:bg-[#243352] dark:text-primary' : 'text-slate-500 dark:text-slate-400' }}">
                {{ \App\Models\PortInRequest::statusLabel($status) }}
            </button>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($requests as $request)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1B2A44]">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $request->phone_number }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300">
                                {{ \App\Models\PortInRequest::statusLabel($request->status) }}
                            </span>
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $request->user?->email ?? 'user #'.$request->user_id }} · requested {{ $request->created_at->format('M j, Y') }}
                            @if ($request->reviewer) · last touched by {{ $request->reviewer->name }} @endif
                        </div>
                    </div>
                </div>

                {{-- Carrier details (admin-only). Cleared once the request closes. --}}
                <div class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 text-xs text-slate-600 sm:grid-cols-2 dark:text-slate-300">
                    <div><span class="text-slate-400">Account holder:</span> {{ $request->billing_name }}</div>
                    <div><span class="text-slate-400">Billing address:</span> {{ $request->billing_address }}</div>
                    <div><span class="text-slate-400">Account #:</span> {{ $request->account_number ?: '— cleared —' }}</div>
                    <div><span class="text-slate-400">Transfer PIN:</span> {{ $request->pin ?: '— cleared —' }}</div>
                    @if ($request->notes)<div class="sm:col-span-2"><span class="text-slate-400">Customer notes:</span> {{ $request->notes }}</div>@endif
                    @if ($request->provider)<div><span class="text-slate-400">Target provider:</span> {{ $request->provider }}</div>@endif
                    @if ($request->admin_notes)<div class="sm:col-span-2"><span class="text-slate-400">Ops notes:</span> {{ $request->admin_notes }}</div>@endif
                    @if ($request->rejection_reason)<div class="sm:col-span-2 text-red-600 dark:text-red-400"><span class="text-slate-400">Rejected:</span> {{ $request->rejection_reason }}</div>@endif
                </div>

                {{-- Actions (open requests only). --}}
                @if ($request->isOpen())
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-white/10">
                        @if ($request->status !== \App\Models\PortInRequest::STATUS_IN_REVIEW)
                            <button wire:click="setStatus({{ $request->id }}, 'in_review')" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:border-primary/40 hover:text-primary dark:border-[#2D4060] dark:text-slate-300">Mark in review</button>
                        @endif
                        @if ($request->status !== \App\Models\PortInRequest::STATUS_SUBMITTED_TO_CARRIER)
                            <button wire:click="setStatus({{ $request->id }}, 'submitted_to_carrier')" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:border-primary/40 hover:text-primary dark:border-[#2D4060] dark:text-slate-300">Submitted to carrier</button>
                        @endif
                        <button wire:click="setStatus({{ $request->id }}, 'completed')" wire:confirm="Mark this port-in complete? The account number and PIN will be cleared." class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Mark completed</button>
                        <button wire:click="editDetails({{ $request->id }})" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:border-primary/40 hover:text-primary dark:border-[#2D4060] dark:text-slate-300">Notes / provider</button>
                        <button wire:click="startReject({{ $request->id }})" class="rounded-lg border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:text-red-400">Reject</button>
                    </div>

                    @if ($rejectingId === $request->id)
                        <div class="mt-2 rounded-xl bg-slate-50 p-3 dark:bg-[#152238]">
                            <input type="text" wire:model="rejectReason" placeholder="Reason (shown to the customer)…"
                                   class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @error('rejectReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="mt-2 flex gap-2">
                                <button wire:click="reject({{ $request->id }})" class="rounded-lg bg-red-600 px-2.5 py-1 text-xs font-semibold text-white">Confirm reject</button>
                                <button wire:click="cancelReject" class="text-xs text-slate-400">Cancel</button>
                            </div>
                        </div>
                    @endif

                    @if ($editingId === $request->id)
                        <div class="mt-2 space-y-2 rounded-xl bg-slate-50 p-3 dark:bg-[#152238]">
                            <input type="text" wire:model="providerValue" placeholder="Target provider (internal, e.g. twilio)…"
                                   class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            <textarea wire:model="adminNotesValue" rows="2" placeholder="Ops notes…"
                                      class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                            <div class="flex gap-2">
                                <button wire:click="saveDetails({{ $request->id }})" class="rounded-lg bg-primary px-2.5 py-1 text-xs font-semibold text-white">Save</button>
                                <button wire:click="cancelEdit" class="text-xs text-slate-400">Cancel</button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-400 dark:border-[#2D4060] dark:text-slate-500">
                No port-in requests{{ $filter ? ' with this status' : '' }} yet.
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
</div>
