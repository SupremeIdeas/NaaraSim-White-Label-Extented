@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Status incidents</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Post incidents to the public <a href="{{ route('status') }}" target="_blank" class="text-primary hover:underline dark:text-teal-300">status page</a>.</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-white/5 dark:text-slate-300">{{ $subscriberCount }} subscriber{{ $subscriberCount === 1 ? '' : 's' }}</span>
    </div>

    {{-- New incident --}}
    <div class="mb-8 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Post a new incident</h2>
        <input type="text" wire:model="title" placeholder="Incident title" class="{{ $inp }}">
        @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <select wire:model="component" class="{{ $inp }}">
                <option value="">Platform-wide</option>
                @foreach ($components as $c)<option value="{{ $c['key'] }}">{{ $c['label'] }}</option>@endforeach
            </select>
            <select wire:model="impact" class="{{ $inp }}">
                <option value="minor">Minor (degraded)</option>
                <option value="major">Major (partial outage)</option>
                <option value="critical">Critical (major outage)</option>
                <option value="maintenance">Maintenance</option>
            </select>
        </div>
        <textarea wire:model="body" rows="3" placeholder="What's happening?" class="{{ $inp }} mt-3"></textarea>
        @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        <button type="button" wire:click="create" class="mt-3 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">Post incident</button>
    </div>

    {{-- Existing incidents --}}
    @foreach ($incidents as $incident)
        <div class="mb-4 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60" wire:key="inc-{{ $incident->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-slate-900 dark:text-white">{{ $incident->title }}</h3>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $incident->isResolved() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' }}">{{ ucfirst($incident->status) }}</span>
            </div>
            <div class="mt-3 space-y-1.5 border-l-2 border-slate-100 pl-3 text-sm dark:border-white/10">
                @foreach ($incident->updates as $u)
                    <div><span class="text-xs font-semibold uppercase text-primary dark:text-teal-300">{{ ucfirst($u->status) }}</span> <span class="text-slate-600 dark:text-slate-300">— {{ $u->body }}</span></div>
                @endforeach
            </div>
            @unless ($incident->isResolved())
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <select wire:model="update.{{ $incident->id }}.status" class="{{ $inp }} sm:max-w-[160px]">
                        <option value="identified">Identified</option>
                        <option value="monitoring">Monitoring</option>
                        <option value="resolved">Resolved</option>
                    </select>
                    <input type="text" wire:model="update.{{ $incident->id }}.body" placeholder="Update message" class="{{ $inp }}">
                    <button type="button" wire:click="postUpdate({{ $incident->id }})" class="shrink-0 rounded-full bg-primary/10 px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/20 dark:text-teal-300">Post</button>
                </div>
                @error('update.'.$incident->id.'.body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            @endunless
        </div>
    @endforeach
</div>
