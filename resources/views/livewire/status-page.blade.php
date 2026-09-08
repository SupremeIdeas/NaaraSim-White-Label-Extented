@php
    $dot = fn ($state) => match ($state) {
        'operational' => 'bg-emerald-500',
        'degraded' => 'bg-amber-500',
        'partial_outage' => 'bg-orange-500',
        'major_outage' => 'bg-red-500',
        'maintenance' => 'bg-sky-500',
        default => 'bg-slate-300 dark:bg-white/20',
    };
    $bannerTone = match ($overall) {
        'operational' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
        'degraded', 'maintenance' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
        default => 'bg-red-50 text-red-800 dark:bg-red-500/10 dark:text-red-300',
    };
@endphp
<div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">System status</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Live health of NaaraSim's services and Developer API.</p>

    {{-- Overall banner --}}
    <div class="mt-6 flex items-center gap-3 rounded-2xl px-5 py-4 {{ $bannerTone }}">
        <span class="relative flex h-3 w-3">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $dot($overall) }} opacity-60"></span>
            <span class="relative inline-flex h-3 w-3 rounded-full {{ $dot($overall) }}"></span>
        </span>
        <span class="text-base font-semibold">{{ $overallLabel }}</span>
        <span class="ml-auto text-xs opacity-70">Updated {{ now()->format('M j, H:i') }} UTC</span>
    </div>

    {{-- Components --}}
    <div class="mt-8 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-slate-900/60">
        @foreach ($components as $c)
            <div class="flex items-center justify-between px-5 py-4">
                <span class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $c['label'] }}</span>
                <span class="flex items-center gap-2 text-xs font-semibold {{ $c['state'] === 'operational' ? 'text-emerald-600 dark:text-emerald-400' : ($c['state'] === 'not_live' ? 'text-slate-400' : 'text-amber-600 dark:text-amber-400') }}">
                    <span class="h-2.5 w-2.5 rounded-full {{ $dot($c['state']) }}"></span>
                    {{ \App\Support\StatusPage::stateLabel($c['state']) }}
                </span>
            </div>
        @endforeach
    </div>

    {{-- Incident timeline --}}
    <h2 class="mb-4 mt-10 text-lg font-semibold text-slate-900 dark:text-white">Incident history</h2>
    @forelse ($timeline as $incident)
        <div class="mb-4 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-slate-900 dark:text-white">{{ $incident->title }}</h3>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $incident->isResolved() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' }}">
                    {{ ucfirst($incident->status) }}
                </span>
            </div>
            <p class="mt-0.5 text-xs text-slate-400">Started {{ $incident->started_at->format('M j, Y H:i') }} UTC</p>
            <div class="mt-3 space-y-2 border-l-2 border-slate-100 pl-4 dark:border-white/10">
                @foreach ($incident->updates as $u)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary dark:text-teal-300">{{ ucfirst($u->status) }} · {{ $u->created_at->format('M j, H:i') }}</p>
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $u->body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
            No incidents reported in the last 90 days. All quiet.
        </p>
    @endforelse

    {{-- Subscribe --}}
    <div class="mt-10 rounded-2xl bg-slate-50 p-6 dark:bg-white/5">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Get status updates</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">We'll email you when an incident is opened or resolved.</p>
        @if ($subscribed)
            <p class="mt-3 flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400"><x-icon name="check" class="h-4 w-4" /> You're subscribed.</p>
        @else
            <form wire:submit="subscribe" class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input type="email" wire:model="email" placeholder="you@example.com"
                    class="w-full rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-slate-900 dark:text-slate-100">
                <button type="submit" class="shrink-0 rounded-full bg-primary px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">Subscribe</button>
            </form>
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        @endif
    </div>
</div>
