{{-- Guided tours hub: every tour the person can take, with Start / Resume / Replay and a quick replay. Skin tokens only. --}}
<div>
<x-nx.page class="ns-narrow">
    <div>
        <h1 class="ns-h1" style="margin-top:6px">Guided tours</h1>
        <p class="ns-sub">Short walk-throughs of the parts of the app you will use most. Replay any of them whenever you like.</p>
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($tours as $row)
            @php($t = $row['tour'])
            <div wire:key="tour-{{ $t->key }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-teal-ink))]"><x-nx.icon :name="$t->icon" /></span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $t->title }}</p>
                        @if ($row['status'] === 'completed')<span class="rounded-full bg-[rgb(var(--nx-ok)/0.14)] px-2 py-0.5 text-[11px] font-medium text-[rgb(var(--nx-ok))]">Completed{{ $row['at'] ? ' '.$row['at']->format('j M') : '' }}</span>
                        @elseif ($row['status'] === 'in_progress')<span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[11px] font-medium text-[rgb(var(--nx-text-2))]">In progress, step {{ $row['step'] + 1 }} of {{ $row['total'] }}</span>
                        @else<span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[11px] font-medium text-[rgb(var(--nx-text-2))]">{{ $row['status'] === 'not_started' ? 'Not started' : 'Skipped' }}</span>@endif
                    </div>
                    <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">{{ $t->summary }} {{ $row['total'] }} steps.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($row['status'] === 'in_progress')
                        <button type="button" wire:click="start('{{ $t->key }}', 'full', true)" class="ns-cta ns-cta--pill ns-cta--sm">Resume</button>
                    @else
                        <button type="button" wire:click="start('{{ $t->key }}', 'full')" class="ns-cta ns-cta--pill ns-cta--sm">{{ $row['status'] === 'completed' ? 'Replay' : 'Start' }}</button>
                    @endif
                    @if ($row['quick'] > 0)
                        <button type="button" wire:click="start('{{ $t->key }}', 'quick')" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Quick replay</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-[rgb(var(--nx-text-2))]">No tours are available right now.</p>
        @endforelse
    </div>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model.live="auto" @disabled(! $open)> Show tips automatically @if (! $open) <span class="text-xs text-[rgb(var(--nx-text-2))]">(ended after your first {{ $days }} days)</span>@endif</label>
        <button type="button" wire:click="resetAll" wire:confirm="Reset every tour back to not started?" class="text-sm text-[rgb(var(--nx-text-2))] underline">Reset all tours</button>
    </div>
</x-nx.page>
</div>
