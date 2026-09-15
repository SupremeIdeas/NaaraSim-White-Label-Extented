@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">App Builder</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Configure and build the installable Android / iOS app. Publishing to the stores still needs the
            developer-account steps in the handoff doc — a compiled build is not a live listing.
        </p>
    </div>

    {{-- ===== Publish-readiness checklist ================================= --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Publish readiness</h2>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $score['done'] === $score['total'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' }}">
                {{ $score['done'] }} / {{ $score['total'] }} ready
            </span>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($checklist as $group => $items)
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $group }}</p>
                    <ul class="space-y-1.5">
                        @foreach ($items as $item)
                            <li class="flex items-start gap-2 text-xs" title="{{ $item['hint'] }}">
                                @if ($item['operator'] ?? false)
                                    <x-icon name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-sky-500" />
                                    <span class="text-slate-500 dark:text-slate-400">{{ $item['label'] }} <span class="text-slate-400">(you)</span></span>
                                @elseif ($item['ok'])
                                    <x-icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                    <span class="text-slate-600 dark:text-slate-300">{{ $item['label'] }}</span>
                                @else
                                    <x-icon name="x" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-red-500" />
                                    <span class="text-slate-600 dark:text-slate-300">{{ $item['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-slate-400">Blue items marked “(you)” are account/review steps only Frank can complete — see the App Export doc.</p>
    </div>

    {{-- ===== App identity ================================================= --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">App identity</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">App name</label>
                <input type="text" wire:model="form.app_name" class="{{ $inp }}">
                @error('form.app_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Short name</label>
                <input type="text" wire:model="form.short_name" class="{{ $inp }}">
                @error('form.short_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Theme colour</label>
                <input type="color" wire:model="form.theme_color" class="h-10 w-full rounded-lg border border-slate-200 dark:border-white/10">
                @error('form.theme_color') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Background colour</label>
                <input type="color" wire:model="form.background_color" class="h-10 w-full rounded-lg border border-slate-200 dark:border-white/10">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Version (x.y.z)</label>
                <input type="text" wire:model="form.version" placeholder="1.0.0" class="{{ $inp }}">
                @error('form.version') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Build number</label>
                <input type="number" min="1" wire:model="form.build_number" class="{{ $inp }}">
                @error('form.build_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Preloader</label>
                <select wire:model="form.preloader" class="{{ $inp }}">
                    @foreach ($preloaders as $p)<option value="{{ $p }}">{{ ucwords(str_replace('-', ' ', $p)) }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="mt-4">
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Release notes / changelog</label>
            <textarea wire:model="form.changelog" rows="2" class="{{ $inp }}" placeholder="What's new in this build"></textarea>
        </div>
    </div>

    {{-- ===== Assets ======================================================= --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">Icon &amp; splash</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400">App icon (square, ≥512px)</label>
                @if (! empty($form['icon_url']))<img src="{{ $form['icon_url'] }}" alt="" class="mb-2 h-16 w-16 rounded-2xl object-cover">@endif
                <input type="file" wire:model="icon" accept="image/webp,image/png,image/jpeg" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary dark:text-slate-400">
                <div wire:loading wire:target="icon" class="mt-1 text-xs text-slate-400">Uploading…</div>
                @error('icon') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400">Splash screen</label>
                @if (! empty($form['splash_url']))<img src="{{ $form['splash_url'] }}" alt="" class="mb-2 h-16 w-28 rounded-lg object-cover">@endif
                <input type="file" wire:model="splash" accept="image/webp,image/png,image/jpeg" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary dark:text-slate-400">
                <div wire:loading wire:target="splash" class="mt-1 text-xs text-slate-400">Uploading…</div>
                @error('splash') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- ===== Signing credentials ========================================== --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Android signing keystore</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Stored encrypted at rest. Never logged, never shown again.</p>

        @if ($hasKeystore)
            <div class="mb-3 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                <x-icon name="shield-check" class="h-4 w-4" />
                Keystore on file{{ ! empty($keystoreMeta['filename']) ? ' · '.$keystoreMeta['filename'] : '' }}
            </div>
            @unless ($form['keystore_backed_up'] ?? false)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-500/20 dark:bg-red-500/10">
                    <p class="flex items-center gap-1.5 text-sm font-semibold text-red-700 dark:text-red-300"><x-icon name="shield" class="h-4 w-4" /> Back up this keystore now</p>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-300/80">
                        If you lose this file, the app can <strong>never</strong> be updated again under the same package ID —
                        you'd have to publish a brand-new listing. Download it from your CI/keystore source and store it
                        somewhere safe and permanent before continuing.
                    </p>
                    <button type="button" wire:click="acknowledgeBackup"
                        class="mt-3 rounded-full bg-red-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                        I've backed it up safely
                    </button>
                </div>
            @endunless
        @endif

        <div class="mt-3 flex items-center gap-2">
            <input type="file" wire:model="keystore" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary dark:text-slate-400">
            <button type="button" wire:click="uploadKeystore" wire:loading.attr="disabled" wire:target="keystore,uploadKeystore"
                class="shrink-0 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/20 dark:text-teal-300">Upload</button>
        </div>
        @error('keystore') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- ===== iOS signing (App Export audit §1.5/§3.5) ====================== --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">iOS signing</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
            Stored encrypted at rest, same as the Android keystore above. Some CI providers (notably an App Store
            Connect API key) only accept these on their own dashboard rather than via API — if that's your provider,
            configure it there and check the box below instead of uploading here.
        </p>

        <div class="mb-3 grid gap-3 sm:grid-cols-2">
            <div>
                <div class="mb-2 flex items-center gap-2 text-xs {{ $hasIosCert ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-400' }}">
                    <x-icon name="{{ $hasIosCert ? 'shield-check' : 'x' }}" class="h-3.5 w-3.5" />
                    {{ $hasIosCert ? 'Certificate on file'.(! empty($iosCertMeta['filename']) ? ' · '.$iosCertMeta['filename'] : '') : 'No distribution certificate stored' }}
                </div>
                <div class="flex items-center gap-2">
                    <input type="file" wire:model="iosCert" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary dark:text-slate-400">
                    <button type="button" wire:click="uploadIosCert" wire:loading.attr="disabled" wire:target="iosCert,uploadIosCert"
                        class="shrink-0 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/20 dark:text-teal-300">Upload</button>
                </div>
                @error('iosCert') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <div class="mb-2 flex items-center gap-2 text-xs {{ $hasIosProvisioningProfile ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-400' }}">
                    <x-icon name="{{ $hasIosProvisioningProfile ? 'shield-check' : 'x' }}" class="h-3.5 w-3.5" />
                    {{ $hasIosProvisioningProfile ? 'Provisioning profile on file'.(! empty($iosProvisioningProfileMeta['filename']) ? ' · '.$iosProvisioningProfileMeta['filename'] : '') : 'No provisioning profile stored' }}
                </div>
                <div class="flex items-center gap-2">
                    <input type="file" wire:model="iosProvisioningProfile" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary dark:text-slate-400">
                    <button type="button" wire:click="uploadIosProvisioningProfile" wire:loading.attr="disabled" wire:target="iosProvisioningProfile,uploadIosProvisioningProfile"
                        class="shrink-0 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/20 dark:text-teal-300">Upload</button>
                </div>
                @error('iosProvisioningProfile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
            <input type="checkbox" wire:click="toggleIosSigningOnProvider" @checked($form['ios_signing_on_provider'] ?? false) class="rounded border-slate-300 text-primary focus:ring-primary">
            iOS signing is configured directly on my CI provider's dashboard instead
        </label>
    </div>

    {{-- ===== Store & download ============================================= --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">Store &amp; download page</h2>
        <label class="mb-3 flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
            <input type="checkbox" wire:model="form.download_enabled" class="rounded border-slate-300 text-primary focus:ring-primary">
            Publish the public <code>/download</code> page
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <input type="checkbox" wire:model="form.android_store_live" class="rounded border-slate-300 text-primary focus:ring-primary">
                    Google Play listing is live
                </label>
                <input type="url" wire:model="form.android_store_url" placeholder="https://play.google.com/…" class="{{ $inp }} mt-2">
                @error('form.android_store_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <input type="checkbox" wire:model="form.ios_store_live" class="rounded border-slate-300 text-primary focus:ring-primary">
                    App Store listing is live
                </label>
                <input type="url" wire:model="form.ios_store_url" placeholder="https://apps.apple.com/…" class="{{ $inp }} mt-2">
                @error('form.ios_store_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-white/5">
            <div class="mb-3 flex items-center justify-between">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400">CI / cloud-build provider</label>
                <span class="flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ciConfigured ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' }}">
                    <x-icon name="{{ $ciConfigured ? 'shield-check' : 'x' }}" class="h-3 w-3" />
                    {{ $ciConfigured ? 'Configured' : 'Not configured — builds will fail immediately' }}
                </span>
            </div>

            <select wire:model.live="form.ci_provider" class="{{ $inp }} mb-3">
                <option value="generic">Generic / custom webhook</option>
                <option value="codemagic">Codemagic</option>
            </select>

            @if (($form['ci_provider'] ?? 'generic') === 'codemagic')
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                    Set the Codemagic API token under Admin → API Keys → App Export. App id and workflow ids come
                    from your Codemagic app's settings page.
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">App ID</label>
                        <input type="text" wire:model="form.codemagic_app_id" placeholder="60xxxxxxxxxxxxxxxxxxxxxx" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Branch</label>
                        <input type="text" wire:model="form.codemagic_branch" placeholder="main" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Android workflow ID</label>
                        <input type="text" wire:model="form.codemagic_android_workflow_id" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">iOS workflow ID</label>
                        <input type="text" wire:model="form.codemagic_ios_workflow_id" class="{{ $inp }}">
                    </div>
                </div>
            @else
                <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">CI / cloud-build webhook URL</label>
                <input type="url" wire:model="form.ci_webhook_url" placeholder="https://your-ci-provider.example/webhook" class="{{ $inp }}">
            @endif
        </div>
    </div>

    {{-- ===== Download CTA placements ====================================== --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">"Download the app" placements</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Toggle where the Android download CTA appears. Each has its own label.</p>
        <div class="space-y-2">
            @foreach ($placementLabels as $key => $label)
                <div class="flex flex-wrap items-center gap-3 rounded-xl bg-slate-50 px-3 py-2 dark:bg-white/5">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" wire:model="placements.{{ $key }}.active" class="rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="font-medium">{{ $label }}</span>
                    </label>
                    <input type="text" wire:model="placements.{{ $key }}.label" placeholder="Get the app" class="{{ $inp }} ml-auto max-w-[200px]">
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===== Store listing & compliance ================================== --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">Store listing &amp; compliance</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Privacy policy URL <span class="text-red-500">*</span></label>
                <input type="url" wire:model="form.privacy_policy_url" class="{{ $inp }}">
                @error('form.privacy_policy_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Account deletion URL</label>
                <input type="text" wire:model="form.account_deletion_url" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Support email</label>
                <input type="email" wire:model="form.support_email" class="{{ $inp }}">
                @error('form.support_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Support URL</label>
                <input type="url" wire:model="form.support_url" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Category</label>
                <input type="text" wire:model="form.category" placeholder="e.g. Travel / Communication" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Content rating</label>
                <input type="text" wire:model="form.content_rating" placeholder="e.g. Everyone / 4+" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Keywords</label>
                <input type="text" wire:model="form.keywords" placeholder="comma,separated" class="{{ $inp }}"></div>
            <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Android target API</label>
                <input type="number" wire:model="form.min_android_target_api" class="{{ $inp }}"></div>
        </div>
        <div class="mt-4">
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Short description</label>
            <input type="text" wire:model="form.short_description" maxlength="200" class="{{ $inp }}"></div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Full description</label>
            <textarea wire:model="form.full_description" rows="4" class="{{ $inp }}"></textarea></div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Data safety / privacy summary</label>
            <textarea wire:model="form.data_safety" rows="3" placeholder="What data the app collects and why (Google Data Safety / Apple privacy labels)." class="{{ $inp }}"></textarea></div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Permissions justification</label>
            <textarea wire:model="form.permissions_note" rows="2" class="{{ $inp }}"></textarea></div>
    </div>

    {{-- ===== First-run onboarding slides ================================= --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">First-run onboarding</h2>
            <button type="button" wire:click="addSlide" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:text-teal-300">+ Slide</button>
        </div>
        <label class="mb-3 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
            <input type="checkbox" wire:model="form.onboarding_enabled" class="rounded border-slate-300 text-primary focus:ring-primary">
            Show onboarding on first app open (portrait slides → login)
        </label>
        <div class="space-y-3">
            @foreach (($form['onboarding_slides'] ?? []) as $i => $slide)
                <div class="flex gap-3 rounded-xl border border-slate-200/70 p-3 dark:border-white/10" wire:key="slide-{{ $i }}">
                    <div class="shrink-0">
                        @if (! empty($slide['image']))
                            <img src="{{ $slide['image'] }}" class="h-24 w-16 rounded-lg object-cover">
                        @else
                            <div class="flex h-24 w-16 items-center justify-center rounded-lg bg-slate-100 text-slate-300 dark:bg-white/5"><x-icon name="image" class="h-5 w-5" /></div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-400">Slide {{ $i + 1 }} (portrait)</span>
                            <button type="button" wire:click="removeSlide({{ $i }})" class="text-slate-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </div>
                        <input type="text" wire:model="form.onboarding_slides.{{ $i }}.title" placeholder="Title" class="{{ $inp }}">
                        <input type="text" wire:model="form.onboarding_slides.{{ $i }}.subtitle" placeholder="Subtitle" class="{{ $inp }} mt-1.5">
                        <div class="mt-1.5 flex items-center gap-2">
                            <input type="file" wire:model="slideImage" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-2 file:py-1 file:text-xs file:text-primary dark:text-slate-400">
                            <button type="button" wire:click="uploadSlideImage({{ $i }})" class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary dark:text-teal-300">Set</button>
                        </div>
                    </div>
                </div>
            @endforeach
            @if (empty($form['onboarding_slides']))
                <p class="text-sm text-slate-400">No slides yet — add 3–4 portrait slides users swipe through on first open.</p>
            @endif
        </div>
    </div>

    <button type="button" wire:click="save" wire:loading.attr="disabled"
        class="mb-8 w-full rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
        Save app settings
    </button>

    {{-- ===== App Studio (native config surface, NAARA-BUILD-21) =========== --}}
    @include('livewire.admin.partials.app-studio', ['inp' => $inp])

    {{-- ===== Generate build =============================================== --}}
    <div class="mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Generate a build</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Android compiles on a Linux CI runner. iOS requires a cloud macOS build service (set its webhook above).
        </p>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="generateBuild('android', 'apk')"
                class="rounded-full bg-primary/10 px-4 py-2 text-xs font-semibold text-primary hover:bg-primary/20 dark:text-teal-300">Android APK (direct install)</button>
            <button type="button" wire:click="generateBuild('android', 'aab')"
                class="rounded-full bg-primary/10 px-4 py-2 text-xs font-semibold text-primary hover:bg-primary/20 dark:text-teal-300">Android AAB (Play Store)</button>
            <button type="button" wire:click="generateBuild('ios', 'ipa')"
                class="rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-200">iOS IPA (macOS service)</button>
        </div>
    </div>

    {{-- ===== Build history ================================================ --}}
    {{-- Live status (App Export audit §3.3): polls ONLY while a build is
         queued/building, so Queued -> Building -> Ready/Failed updates without
         a manual refresh, and a genuinely stuck build (status never changes)
         reads visibly differently from one that's just slow. --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60"
         x-data
         x-on:appbuild-ready.window="
            $dispatch('nx-toast', { type: 'success', message: $event.detail.label + ' is ready — downloading…' });
            const a = document.createElement('a');
            a.href = $event.detail.url;
            // Force a download rather than an in-page navigation — without this,
            // a same-origin URL the browser can render inline (confirmed live:
            // an .ico/.png artifact URL) navigates the whole admin page away
            // instead of downloading, exactly the opposite of the intent here.
            a.download = $event.detail.label.replace(/\s+/g, '-') + '.' + $event.detail.url.split('.').pop().split(/[?#]/)[0];
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            a.remove();
         "
         @if ($hasActiveBuild) wire:poll.3s="pollBuilds" @endif>
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-200">Build history</h2>
        @forelse ($builds as $b)
            <div class="mb-2 rounded-xl border border-slate-200/70 p-3 dark:border-white/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="text-sm text-slate-700 dark:text-slate-200">
                        <span class="font-semibold uppercase">{{ $b->platform }}</span> · {{ strtoupper($b->artifact_type) }} · v{{ $b->version }} ({{ $b->build_number }})
                    </div>
                    @php $tone = match($b->status){
                        'ready' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                        'failed' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
                        'building' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                        default => 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300',
                    }; @endphp
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $tone }}">{{ $b->statusLabel() }}</span>
                </div>
                <div class="mt-1 flex items-center gap-3 text-xs text-slate-400">
                    <span>{{ $b->created_at->diffForHumans() }}</span>
                    @if ($b->isReady() && $b->artifact_url)
                        <a href="{{ $b->artifact_url }}" class="font-semibold text-primary hover:underline dark:text-teal-300">Download artifact</a>
                    @endif
                    @if ($b->log)
                        <details class="cursor-pointer"><summary class="text-slate-500">Build log</summary>
                            <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap rounded bg-slate-900 p-2 text-[11px] text-slate-200">{{ $b->log }}</pre>
                        </details>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-400">No builds yet. Configure the app above, then Generate a build.</p>
        @endforelse
        <div class="mt-3">{{ $builds->links() }}</div>
    </div>
</div>
