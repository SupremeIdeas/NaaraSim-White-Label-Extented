{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Guided tours</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Edit what each tour says, reorder or switch off steps, publish a new version to show a tour again, and see where people drop off. Numbers cover the last 30 days and identify nobody.</p>
    </div>

    <div class="{{ $card }}">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Tours</h2>
            <button type="button" wire:click="checkAll" wire:loading.attr="disabled" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-sm font-medium text-[rgb(var(--nx-text))]">Check all tours</button>
        </div>
        @if ($check !== null)
            <div class="mt-3 rounded-xl p-3 text-sm {{ $check === [] ? 'bg-[rgb(var(--nx-ok)/0.12)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-bad)/0.1)] text-[rgb(var(--nx-bad))]' }}">
                @if ($check === [])All anchors, routes and copy are present.@else<ul class="list-disc pl-5">@foreach ($check as $p)<li>{{ $p }}</li>@endforeach</ul>@endif
            </div>
        @endif
        <div class="mt-3 space-y-2">
            @foreach ($tours as $t)
                <div wire:key="tr-{{ $t->id }}" class="flex flex-wrap items-center gap-3 rounded-xl border border-[rgb(var(--nx-line))] p-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-[rgb(var(--nx-text))]">{{ $t->title }} <span class="text-xs text-[rgb(var(--nx-text-2))]">v{{ $t->version }} · {{ $t->steps_count }} steps{{ $t->is_active ? '' : ' · off' }}{{ $t->is_customized ? ' · edited' : '' }}</span></p>
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">Started {{ $t->stats['started'] }} · Finished {{ $t->stats['completed'] }}{{ $t->stats['rate'] === null ? '' : ' ('.$t->stats['rate'].'%)' }} · Dismissed {{ $t->stats['dismissed'] }}</p>
                    </div>
                    <button type="button" wire:click="edit({{ $t->id }})" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-1.5 text-sm font-medium text-[rgb(var(--nx-text))]">Edit</button>
                </div>
            @endforeach
        </div>
    </div>

    @if ($editingTour)
        <form wire:submit="save" class="{{ $card }} space-y-4">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">{{ $editingTour->key }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="{{ $lb }}">Title</label><input type="text" wire:model="title" class="{{ $in }}">@error('title')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}">Summary</label><input type="text" wire:model="summary" class="{{ $in }}"></div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-[rgb(var(--nx-text))]">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="is_active"> Tour is on</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="auto_start"> Offer automatically</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="quick_enabled"> Allow quick replay</label>
            </div>
            <div class="space-y-3">
                @foreach ($editingTour->steps as $s)
                    @php($st = $editingTour->stepStats ?? null)
                    <div wire:key="st-{{ $s->id }}" class="rounded-xl border border-[rgb(var(--nx-line))] p-3">
                        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-[rgb(var(--nx-text-2))]">
                            <span class="font-mono">{{ $s->anchor }}</span>
                            @php($ss = ($tours->firstWhere('id', $editingTour->id)->stepStats[$s->anchor] ?? null))
                            @if ($ss)<span>seen {{ $ss['viewed'] }}@if ($ss['missing']) · <b class="text-[rgb(var(--nx-bad))]">element missing {{ $ss['missing'] }}</b>@endif</span>@endif
                            <span class="flex-1"></span>
                            <button type="button" wire:click="move({{ $s->id }}, 'up')" class="rounded border border-[rgb(var(--nx-line))] px-2 py-0.5">Up</button>
                            <button type="button" wire:click="move({{ $s->id }}, 'down')" class="rounded border border-[rgb(var(--nx-line))] px-2 py-0.5">Down</button>
                        </div>
                        <input type="text" wire:model="steps.{{ $s->id }}.title" class="{{ $in }}" aria-label="Step title">
                        <textarea wire:model="steps.{{ $s->id }}.body" rows="2" class="{{ $in }} mt-2" aria-label="Step text"></textarea>
                        @error('steps.'.$s->id.'.body')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
                        <div class="mt-2 flex gap-4 text-sm text-[rgb(var(--nx-text))]">
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="steps.{{ $s->id }}.is_active"> Show this step</label>
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="steps.{{ $s->id }}.quick"> In quick replay</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" wire:click="resetDefaults" wire:confirm="Throw away your edits to this tour?" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm">Reset to default</button>
                <button type="button" wire:click="publish" wire:confirm="Show this tour again to everyone who already finished or dismissed it?" class="rounded-lg border border-[rgb(var(--nx-line))] px-3 py-2 text-sm">Publish new version</button>
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">Save</button>
            </div>
        </form>
    @endif

    <form wire:submit="saveWindow" class="{{ $card }}">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">New-user window</h2>
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">Automatic tips are offered, and the Guided tours page is shown, for this many days from sign-up (existing accounts count from the day tours launched). 0 = never ends.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div><label class="{{ $lb }}">Days</label><input type="number" min="0" max="3650" wire:model="window_days" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">After the window</label>
                <select wire:model="hub_after" class="{{ $in }}"><option value="hide">Hide the Guided tours page</option><option value="keep">Keep the page, stop automatic tips</option></select></div>
        </div>
        <div class="mt-3 flex justify-end"><button type="submit" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2 text-sm font-semibold text-white">Save window</button></div>
    </form>
</div>
