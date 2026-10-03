@php
    $card = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]';
    $btn = 'rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 dark:border-white/15 dark:text-slate-200 dark:hover:bg-white/5';
    $toggle = fn (bool $on) => 'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition '.($on ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-600');
@endphp
<div class="mx-auto max-w-3xl space-y-6 pb-12">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">User appearance</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Governs what members can pick for their own dashboards. Presentation only: it never changes providers, pricing, wallet or orders. Every change is audit-logged.</p>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your own admin dashboard has its own appearance: <a href="{{ $myAppearance }}" wire:navigate class="font-semibold text-primary hover:underline">choose it here</a>. Platform themes, the logo and the marketing theme presets are not affected by any of this.</p>
    </div>

    @if ($message)<div class="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300" role="status"><x-icon name="check" class="h-4 w-4" /> {{ $message }}</div>@endif
    @if ($error)<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300" role="alert">{{ $error }}</div>@endif

    <section class="{{ $card }}" aria-labelledby="h-ctl">
        <h2 id="h-ctl" class="text-base font-bold text-slate-900 dark:text-slate-100">White-label control</h2>
        <div class="mt-3 space-y-3">
            <label class="flex items-center justify-between gap-4">
                <span class="text-sm text-slate-800 dark:text-slate-200">Allow custom accent colour<span class="block text-xs text-slate-500 dark:text-slate-400">Members can pick any colour; it is kept readable automatically.</span></span>
                <button type="button" wire:click="toggleCustom" wire:loading.attr="disabled" wire:target="toggleCustom" role="switch" aria-checked="{{ $custom ? 'true' : 'false' }}" class="{{ $toggle($custom) }}"><span class="inline-block h-5 w-5 transform rounded-full bg-white transition {{ $custom ? 'translate-x-5' : 'translate-x-0.5' }}"></span></button>
            </label>
            <label class="flex items-center justify-between gap-4">
                <span class="text-sm text-slate-800 dark:text-slate-200">Lock appearance for all members<span class="block text-xs text-slate-500 dark:text-slate-400">Everyone sees the platform default. Admin accounts are exempt so they can still test every skin.</span></span>
                <button type="button" wire:click="toggleLock" wire:loading.attr="disabled" wire:target="toggleLock" role="switch" aria-checked="{{ $locked ? 'true' : 'false' }}" class="{{ $toggle($locked) }}"><span class="inline-block h-5 w-5 transform rounded-full bg-white transition {{ $locked ? 'translate-x-5' : 'translate-x-0.5' }}"></span></button>
            </label>
        </div>
    </section>

    <section class="{{ $card }}" aria-labelledby="h-skins">
        <h2 id="h-skins" class="text-base font-bold text-slate-900 dark:text-slate-100">Skins</h2>
        @if ($licensed)
            <p class="text-xs text-slate-500 dark:text-slate-400">Your skins come from your licence and are chosen on the <a href="{{ $skinsRoute }}" wire:navigate class="font-semibold text-primary hover:underline">Your skins</a> screen. Only those skins exist for your members; this list is read-only.</p>
        @else
            <p class="text-xs text-slate-500 dark:text-slate-400">A skin can only be switched on once its stylesheet has shipped. The default skin must stay enabled.</p>
        @endif
        <ul class="mt-3 divide-y divide-slate-100 dark:divide-white/10">
            @foreach ($skins as $row)
                @php($ready = in_array($row->key, $built, true))
                <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="skin-{{ $row->key }}">
                    <div class="min-w-0">
                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $row->label }}</span>
                        <span class="ml-1 text-xs text-slate-500 dark:text-slate-400">{{ $catalog[$row->key]['group'] ?? '' }}</span>
                        @if ($row->is_default)<span class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">Default</span>@endif
                        <span class="ml-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $row->access === 'free' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ $row->access === 'free' ? 'Free' : 'Pro' }}</span>
                        @unless ($ready)<span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-300">Not built yet</span>@endunless
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $catalog[$row->key]['blurb'] ?? '' }}</span>
                    </div>
                    <div class="flex items-center gap-2" @if ($licensed) style="display:none" @endif>
                        @if ($ready && ! $row->is_default)<button type="button" wire:click="setDefaultSkin('{{ $row->key }}')" wire:loading.attr="disabled" wire:target="setDefaultSkin" class="{{ $btn }}">Make default</button>@endif
                        <button type="button" wire:click="toggle('skin', '{{ $row->key }}')" wire:loading.attr="disabled" wire:target="toggle" @disabled(! $ready) role="switch" aria-checked="{{ $row->enabled ? 'true' : 'false' }}" aria-label="{{ $row->label }}" class="{{ $toggle($row->enabled) }} disabled:opacity-40"><span class="inline-block h-5 w-5 transform rounded-full bg-white transition {{ $row->enabled ? 'translate-x-5' : 'translate-x-0.5' }}"></span></button>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="{{ $card }}" aria-labelledby="h-acc">
        <h2 id="h-acc" class="text-base font-bold text-slate-900 dark:text-slate-100">Accents</h2>
        <ul class="mt-3 divide-y divide-slate-100 dark:divide-white/10">
            @foreach ($accents as $row)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="acc-{{ $row->key }}">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $row->label }}</span>
                        @if ($row->is_default)<span class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">Default</span>@endif
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $row->access === 'free' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ $row->access === 'free' ? 'Free' : 'Pro' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        @unless ($row->is_default)<button type="button" wire:click="setDefaultAccent('{{ $row->key }}')" wire:loading.attr="disabled" wire:target="setDefaultAccent" class="{{ $btn }}">Make default</button>@endunless
                        <button type="button" wire:click="toggle('accent', '{{ $row->key }}')" wire:loading.attr="disabled" wire:target="toggle" role="switch" aria-checked="{{ $row->enabled ? 'true' : 'false' }}" aria-label="{{ $row->label }}" class="{{ $toggle($row->enabled) }}"><span class="inline-block h-5 w-5 transform rounded-full bg-white transition {{ $row->enabled ? 'translate-x-5' : 'translate-x-0.5' }}"></span></button>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Free / Pro is shown for planning only. The access rules (trials, plans, goals) arrive in a later batch; until then every enabled preset is available to everyone.</p>
    </section>
</div>
