{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-24 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
    $mut = 'text-xs text-[rgb(var(--nx-text-2))]';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('admin.trustpilot') }}" class="{{ $mut }} underline">Trustpilot</a>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Review moments</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Ask people how it went right after something really worked: an eSIM delivered, a code received, a gift card delivered. The question appears when they have been idle for a while or come back to the app, never in the middle of a task.</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="{{ $card }} space-y-3" data-rm-master>
            <label class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model.live="enabled"> Ask for reviews after a successful experience</label>
            <p class="{{ $mut }}">{{ $live ? 'Live. Trustpilot form: '.$reviewUrl : ($enabled ? 'Switched on, but not live: a Trustpilot profile address is needed (Trustpilot page).' : 'Off.') }}</p>
            <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3 text-xs text-[rgb(var(--nx-text-2))]">
                <b class="text-[rgb(var(--nx-text))]">How this stays inside Trustpilot's rules.</b> Who is asked depends only on a completed experience (the product was delivered), never on how they felt. Everyone who answers, whatever stars they give, gets the same invitation to Trustpilot; a 1 to 3 star answer also gets a support link, never instead of the invitation. Nothing is offered or paid for a review. The stars are kept privately as a quality signal. Please confirm Trustpilot's current guidelines before changing this.
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-[rgb(var(--nx-text))]">
                <label>Quiet for (minutes)<input type="number" min="1" max="240" wire:model="idleMinutes" class="{{ $in }} mt-1 block"></label>
                <label>Ask one person at most every (days)<input type="number" min="0" max="365" wire:model="cooldownDays" class="{{ $in }} mt-1 block"></label>
                <label>Only ask about the last (days)<input type="number" min="1" max="90" wire:model="windowDays" class="{{ $in }} mt-1 block"></label>
            </div>
        </div>

        <div class="{{ $card }} space-y-3">
            <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Where it asks</h2>
            @foreach ($catalog as $key => $c)
                @php($s = $stats[$key] ?? null)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[rgb(var(--nx-line))] p-3" data-rm-row="{{ $key }}">
                    <label class="flex min-w-0 flex-1 items-start gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="on.{{ $key }}" class="mt-1"><span><b>{{ $c['label'] }}</b><span class="{{ $mut }} block">"{{ $c['title'] }}" {{ $c['first_only'] ? 'After the first one only.' : '' }}</span></span></label>
                    <label class="{{ $mut }}">Wait after success (min)<input type="number" min="0" max="10080" wire:model="delay.{{ $key }}" class="{{ $in }} mt-1 block"></label>
                    <span class="{{ $mut }} basis-full">Last 30 days: {{ (int) ($s->qualified ?? 0) }} successes, {{ (int) ($s->asked ?? 0) }} asked, {{ (int) ($s->rated ?? 0) }} rated{{ ($s && $s->avg_stars) ? ' (average '.number_format((float) $s->avg_stars, 1).' stars)' : '' }}, {{ (int) ($s->opened ?? 0) }} opened Trustpilot.</span>
                </div>
            @endforeach
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
    </form>
</div>
