<div class="mx-auto max-w-4xl px-4 py-6" wire:key="email-broadcast">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Send a broadcast</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Email an audience segment. Sends are queued in batches and logged.</p>
        </div>
        <a href="{{ route('admin.email-studio') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5">
            <x-icon name="mail" class="h-4 w-4" /> Template editor
        </a>
    </div>

    @if ($sent)
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-medium text-green-700 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300">{{ $sent }}</div>
    @endif

    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
        <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Subject</span>
            <input type="text" wire:model="subject" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
            @error('subject') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
        </label>
        <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Body (basic HTML allowed; sanitized on send)</span>
            <textarea rows="8" wire:model="body" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white"></textarea>
            @error('body') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Audience</span>
                <select wire:model.live="audienceType" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                    @foreach ($types as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            @if ($audienceType !== 'all')
                <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Which</span>
                    <select wire:model.live="audienceValue" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                        <option value="">Choose…</option>
                        @foreach (($audienceType === 'role' ? $roles : ($audienceType === 'account' ? $accounts : $segments)) as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('audienceValue') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </label>
            @endif
        </div>

        @if (! $confirming)
            <button type="button" wire:click="review" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
                <x-icon name="send" class="h-4 w-4" /> Review &amp; send
            </button>
        @else
            <div class="rounded-xl border border-accent/30 bg-accent/10 p-4 dark:border-accent/25">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    This will email <span class="text-accent-dark dark:text-accent">{{ number_format($this->recipientCount()) }}</span> {{ \Illuminate\Support\Str::plural('recipient', $this->recipientCount()) }}
                    ({{ \App\Support\BroadcastAudience::label($audienceType, $audienceValue) }}).
                </p>
                <div class="mt-3 flex items-center gap-2">
                    <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="send">Confirm &amp; queue send</span>
                        <span wire:loading wire:target="send">Queuing…</span>
                    </button>
                    <button type="button" wire:click="$set('confirming', false)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 dark:border-white/10 dark:text-slate-300">Cancel</button>
                </div>
            </div>
        @endif
    </div>

    {{-- Send history --}}
    @if ($history->isNotEmpty())
        <div class="mt-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Recent broadcasts</h2>
            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400 dark:bg-white/5">
                        <tr><th class="px-4 py-2">Subject</th><th class="px-4 py-2">Audience</th><th class="px-4 py-2">Recipients</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Sent</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($history as $b)
                            <tr class="text-slate-700 dark:text-slate-300">
                                <td class="px-4 py-2 font-medium text-slate-900 dark:text-white">{{ \Illuminate\Support\Str::limit($b->subject, 40) }}</td>
                                <td class="px-4 py-2">{{ $b->audience_label }}</td>
                                <td class="px-4 py-2">{{ number_format($b->recipient_count) }}</td>
                                <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $b->status === 'sent' ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300' }}">{{ ucfirst($b->status) }}</span></td>
                                <td class="px-4 py-2 text-slate-400">{{ $b->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
