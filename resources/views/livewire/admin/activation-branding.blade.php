<div class="mx-auto max-w-5xl" data-tour="activation-branding">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Setup</p>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Activation and branding</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">Make this platform yours. What you set here replaces the shipped name on every page, email and notification. Logos, colours and fonts are on the Branding page.</p>
        </div>
        <a href="{{ route('admin.branding') }}" wire:navigate class="nx-btn nx-btn--ghost !px-4 !py-2 !text-sm">Logos, colours and fonts</a>
    </div>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-xl bg-primary/10 p-3 text-sm text-primary-dark dark:bg-white/10 dark:text-slate-100" role="status">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <form wire:submit="save" class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Identity</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Brand name</span>
                        <input type="text" wire:model.live.debounce.300ms="brand_name" maxlength="40" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-slate-100" placeholder="Acme Mobile">
                        <span class="mt-1 block text-xs text-slate-500">The product name: titles, header, emails, app name.</span>
                        @error('brand_name') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Brand word</span>
                        <input type="text" wire:model.live.debounce.300ms="brand_word" maxlength="24" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-slate-100" placeholder="Acme">
                        <span class="mt-1 block text-xs text-slate-500">Put in front of product names: "{{ $previewWord }} Data", "{{ $previewWord }} Rent", "{{ $previewWord }} Gift".</span>
                        @error('brand_word') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm sm:col-span-2">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Tagline</span>
                        <input type="text" wire:model="tagline" maxlength="90" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-slate-100" placeholder="Data and numbers for people on the move">
                        @error('tagline') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Company</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([['legal_name', 'Legal company name', 'Acme Mobile Ltd'], ['agency_name', 'Credited in footers and emails', 'Acme Mobile Ltd'], ['website', 'Website', 'https://acme.example'], ['agency_url', 'Credit link', 'https://acme.example']] as [$f, $l, $ph])
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $l }}</span>
                            <input type="text" wire:model="{{ $f }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-slate-100" placeholder="{{ $ph }}">
                            @error($f) <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                    <label class="block text-sm sm:col-span-2">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Address</span>
                        <input type="text" wire:model="address" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-slate-100" placeholder="12 Market Road, Lagos">
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Support and social</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([['support_email', 'Support email', 'help@acme.example'], ['support_phone', 'Support phone', '+234 800 000 0000'], ['social_x', 'X (Twitter) link', 'https://x.com/acme'], ['social_instagram', 'Instagram link', 'https://instagram.com/acme'], ['social_facebook', 'Facebook link', 'https://facebook.com/acme'], ['social_linkedin', 'LinkedIn link', 'https://linkedin.com/company/acme'], ['social_youtube', 'YouTube link', 'https://youtube.com/@acme'], ['social_tiktok', 'TikTok link', 'https://tiktok.com/@acme']] as [$f, $l, $ph])
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $l }}</span>
                            <input type="text" wire:model="{{ $f }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-slate-100" placeholder="{{ $ph }}">
                            @error($f) <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="nx-btn nx-btn--primary !px-6 !py-2.5">
                    <span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Setup checklist</h2>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $checklist['done'] }} / {{ $checklist['total'] }}</span>
                </div>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full bg-primary dark:bg-white/70" style="width: {{ (int) round($checklist['done'] / max(1, $checklist['total']) * 100) }}%"></div></div>
                <ul class="mt-4 space-y-3">
                    @foreach ($checklist['items'] as $i)
                        <li class="flex gap-3">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $i['done'] ? 'bg-emerald-600 text-white' : 'border border-slate-300 text-transparent dark:border-white/25' }}"><x-icon name="check" class="h-3 w-3" /></span>
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $i['label'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $i['hint'] }}@if ($i['route'] && ! $i['done']) <a href="{{ route($i['route']) }}" wire:navigate class="font-semibold text-slate-900 underline dark:text-white">Open</a>@endif</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">This installation</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Edition</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ $edition }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Instance</dt><dd class="truncate font-mono text-xs text-slate-800 dark:text-slate-100">{{ $instanceId ?: 'not registered' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Database</dt><dd class="font-medium {{ $dbState === 'ours' ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">{{ $dbState === 'ours' ? 'belongs to this install' : $dbState }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-dashed border-slate-300 p-5 text-sm dark:border-white/20">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500 dark:text-slate-400">Preview</p>
                <p class="mt-2 text-lg font-bold text-slate-900 dark:text-white">{{ $previewName }}</p>
                <p class="text-slate-600 dark:text-slate-300">{{ $previewWord }} Data · {{ $previewWord }} Rent · {{ $previewWord }} Gift</p>
            </section>
        </aside>
    </div>
</div>
