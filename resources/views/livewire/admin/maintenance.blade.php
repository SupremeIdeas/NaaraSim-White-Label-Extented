<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Maintenance loop</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Claude reads a logged error and proposes a minimal fix. You review the diff; approving opens a <span class="font-medium">CI-gated pull request</span> — nothing reaches production automatically, and the loop never edits secrets.
    </p>

    {{-- Server cron / scheduler command — always available for reference. --}}
    <div class="mb-6">
        <x-cron-setup hosting="auto" />
    </div>

    @if ($status)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $status }}
        </div>
    @endif
    @if ($error)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
            <x-icon name="shield" class="h-4 w-4" /> {{ $error }}
        </div>
    @endif

    {{-- Configuration status --}}
    <div class="mb-8 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div @class(['flex items-center gap-2 rounded-xl border p-3 text-sm', 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300' => $proposerReady, 'border-slate-200 bg-slate-50 text-slate-500 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-400' => ! $proposerReady])>
            <x-icon :name="$proposerReady ? 'check' : 'x'" class="h-4 w-4" />
            Fix proposer (Claude): {{ $proposerReady ? 'ready' : 'not configured' }}
        </div>
        <div @class(['flex items-center gap-2 rounded-xl border p-3 text-sm', 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300' => $hostReady, 'border-slate-200 bg-slate-50 text-slate-500 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-400' => ! $hostReady])>
            <x-icon :name="$hostReady ? 'check' : 'x'" class="h-4 w-4" />
            Code host (GitHub): {{ $hostReady ? 'ready' : 'not configured' }}
        </div>
    </div>

    {{-- Recent errors --}}
    <section class="mb-8">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <x-icon name="file-text" class="h-4 w-4" /> Recent errors
        </h2>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
            @forelse ($errors as $e)
                <div wire:key="err-{{ $e->id }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 last:border-0 dark:border-[#243352]">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $e->message }}</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">{{ $e->code }} · {{ $e->severity }} · {{ $e->created_at?->diffForHumans() }}</p>
                    </div>
                    <button type="button" wire:click="propose({{ $e->id }})" @disabled(! $proposerReady) wire:loading.attr="disabled" wire:target="propose({{ $e->id }})"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark disabled:opacity-50">
                        <span wire:loading.remove wire:target="propose({{ $e->id }})">Propose fix</span>
                        <span wire:loading wire:target="propose({{ $e->id }})" class="inline-flex items-center gap-1"><x-ui.spinner class="h-3.5 w-3.5" /> Thinking…</span>
                    </button>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400 dark:text-slate-500">No errors logged — nothing to fix.</p>
            @endforelse
        </div>
    </section>

    {{-- Proposals --}}
    <section>
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <x-icon name="package" class="h-4 w-4" /> Proposals
        </h2>
        <div class="space-y-3">
            @forelse ($proposals as $p)
                <div wire:key="prop-{{ $p->id }}" class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $p->title }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $p->summary }}</p>
                        </div>
                        @php
                            $badge = [
                                'pending' => ['Pending review', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
                                'pr_opened' => ['PR opened', 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'],
                                'rejected' => ['Rejected', 'bg-slate-100 text-slate-500 dark:bg-[#243352] dark:text-slate-400'],
                                'rolled_back' => ['Rolled back', 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
                            ][$p->status] ?? [$p->status, 'bg-slate-100 text-slate-500'];
                        @endphp
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                    </div>

                    @if ($p->pr_url)
                        <a href="{{ $p->pr_url }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"><x-icon name="link" class="h-3.5 w-3.5" /> View pull request</a>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($p->diff)
                            <button type="button" wire:click="$set('viewing', {{ $viewing === $p->id ? 'null' : $p->id }})" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                <x-icon name="file-text" class="h-3.5 w-3.5" /> {{ $viewing === $p->id ? 'Hide' : 'View' }} diff
                            </button>
                        @endif
                        @if ($p->status === 'pending')
                            <button type="button" wire:click="approve({{ $p->id }})" @disabled(! $hostReady) wire:confirm="Approve and open a CI-gated pull request?"
                                    class="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark disabled:opacity-50">
                                <x-icon name="check" class="h-3.5 w-3.5" /> Approve &amp; open PR
                            </button>
                            <button type="button" wire:click="reject({{ $p->id }})" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                <x-icon name="x" class="h-3.5 w-3.5" /> Reject
                            </button>
                        @elseif ($p->status === 'pr_opened')
                            <button type="button" wire:click="rollback({{ $p->id }})" wire:confirm="Roll back — close the pull request?"
                                    class="inline-flex items-center gap-1 rounded-lg border border-red-300 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40">
                                <x-icon name="refresh" class="h-3.5 w-3.5" /> Roll back
                            </button>
                        @endif
                    </div>

                    @if ($viewing === $p->id && $p->diff)
                        <pre class="mt-3 max-h-80 overflow-auto rounded-xl bg-slate-900 p-4 text-xs leading-relaxed text-slate-100 dark:bg-black/40">{{ $p->diff }}</pre>
                    @endif
                </div>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 py-8 text-center text-sm text-slate-400 dark:border-[#2D4060] dark:text-slate-500">No proposals yet. Pick an error above and propose a fix.</p>
            @endforelse
        </div>
    </section>
</div>
