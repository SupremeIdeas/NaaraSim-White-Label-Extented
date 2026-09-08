{{-- App Studio — the native-app configuration surface (NAARA-BUILD-21). Grouped
     to match the real Median appConfig structure this was grounded against.
     Every field auto-populates from real platform data, so "Generate a build"
     works with zero fields touched. --}}
@php
    $lbl = 'block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1';
    $help = 'mt-1 text-[11px] text-slate-400 dark:text-slate-500';
    $card = 'mb-6 rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60';
@endphp

<div class="{{ $card }}">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">App Studio — native configuration</h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                Offline page, link handling, permissions and security for the compiled app. Grounded on a real
                Median export of this platform.
            </p>
        </div>
        @if ($canGenerateWithDefaults)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <x-icon name="check" class="h-3.5 w-3.5" /> Ready to build with defaults
            </span>
        @endif
    </div>

    {{-- ---- General ---------------------------------------------------- --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="{{ $lbl }}">Initial URL</label>
            <input type="url" wire:model="studio.initial_url" placeholder="{{ \App\Support\AppStudio::initialUrl() }}" class="{{ $inp }}">
            <p class="{{ $help }}">Blank uses the platform default shown above.</p>
            @error('studio.initial_url') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="{{ $lbl }}">App display name</label>
            <input type="text" wire:model="studio.app_display_name" placeholder="{{ \App\Support\BrandSettings::name() }}" class="{{ $inp }}">
            <p class="{{ $help }}">Blank uses the brand name.</p>
        </div>
        <div>
            <label class="{{ $lbl }}">Android package name</label>
            <input type="text" wire:model="studio.android_package_name" class="{{ $inp }}">
            <p class="{{ $help }}">Set once — changing this after a store listing exists breaks the listing.</p>
            @error('studio.android_package_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="{{ $lbl }}">iOS bundle ID</label>
            <input type="text" wire:model="studio.ios_bundle_id" class="{{ $inp }}">
            <p class="{{ $help }}">Set once — treat as permanent once published.</p>
            @error('studio.ios_bundle_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- ---- Interface: offline page + live phone-frame preview --------- --}}
    <div class="mt-6 border-t border-slate-100 pt-5 dark:border-white/5">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Offline page</h3>
        <div class="grid gap-5 lg:grid-cols-2"
             x-data="{ html: @js($offlinePreview) }"
             x-init="$watch('$wire.studio.offline_html', v => { if ($wire.studio.offline_style === 'custom' && v) html = v })">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="radio" wire:model.live="studio.offline_style" value="default" class="text-primary"> Default (branded)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="radio" wire:model.live="studio.offline_style" value="custom" class="text-primary"> Custom HTML
                    </label>
                </div>

                <div>
                    <label class="{{ $lbl }}">Connection timeout (seconds)</label>
                    <input type="number" min="3" max="120" wire:model="studio.offline_timeout" class="{{ $inp }} max-w-[8rem]">
                    <p class="{{ $help }}">How long with no connectivity before the offline page shows.</p>
                    @error('studio.offline_timeout') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div x-show="$wire.studio.offline_style === 'custom'" x-cloak class="space-y-3">
                    <div>
                        <label class="{{ $lbl }}">Custom offline HTML</label>
                        <textarea wire:model.live.debounce.400ms="studio.offline_html" rows="8"
                                  class="{{ $inp }} font-mono text-xs" placeholder="<!doctype html>…"></textarea>
                        @error('studio.offline_html') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="nx-btn nx-btn--ghost !py-1.5 !text-xs cursor-pointer">
                            <x-icon name="upload" class="h-3.5 w-3.5" /> Upload file
                            <input type="file" wire:model="offlineFile" accept=".html,.htm,text/html" class="hidden">
                        </label>
                        <button type="button" wire:click="uploadOfflineFile" wire:loading.attr="disabled" wire:target="offlineFile,uploadOfflineFile"
                                x-show="$wire.offlineFile" class="nx-btn nx-btn--primary !py-1.5 !text-xs">Use uploaded file</button>
                        <button type="button" wire:click="resetOffline" class="nx-btn nx-btn--ghost !py-1.5 !text-xs">
                            <x-icon name="refresh" class="h-3.5 w-3.5" /> Reset to default
                        </button>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[12rem]">
                            <label class="{{ $lbl }}">…or update from a URL</label>
                            <input type="url" wire:model="offlineUrl" placeholder="https://…/offline.html" class="{{ $inp }}">
                            @error('offlineUrl') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="fetchOfflineUrl" wire:loading.attr="disabled" wire:target="fetchOfflineUrl" class="nx-btn nx-btn--ghost !py-2 !text-xs">Fetch</button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 pt-1">
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <x-ui.switch wire:model="studio.pull_to_refresh" /> Pull to refresh
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <x-ui.switch wire:model="studio.refresh_button" /> Refresh button
                    </label>
                </div>
            </div>

            {{-- Phone-frame live preview --}}
            <div class="flex justify-center">
                <div class="w-[240px] rounded-[2.2rem] border-[7px] border-slate-800 bg-slate-800 shadow-xl dark:border-slate-700">
                    <div class="mx-auto mt-1 h-1 w-16 rounded-full bg-slate-600"></div>
                    <div class="mt-1 overflow-hidden rounded-[1.6rem] bg-white">
                        <iframe title="Offline page preview" class="h-[420px] w-full border-0" sandbox="" x-bind:srcdoc="html"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ---- Native navigation: link handling --------------------------- --}}
    <div class="mt-6 border-t border-slate-100 pt-5 dark:border-white/5">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Link handling rules</h3>
            <button type="button" wire:click="addLinkRule" class="nx-btn nx-btn--ghost !py-1 !text-xs">
                <x-icon name="plus" class="h-3.5 w-3.5" /> Add rule
            </button>
        </div>
        <p class="mb-3 text-[11px] text-slate-400 dark:text-slate-500">
            Evaluated top to bottom. Auto-populated: your domain → Internal, social platforms → App browser, everything else → External.
        </p>
        <div class="space-y-2">
            @foreach ($linkRules as $i => $rule)
                <div class="flex items-center gap-2" wire:key="rule-{{ $i }}">
                    <span class="w-5 text-center text-[11px] text-slate-400">{{ $i + 1 }}</span>
                    <input type="text" wire:model="linkRules.{{ $i }}.pattern" placeholder="example.com or *" class="{{ $inp }} flex-1">
                    <select wire:model="linkRules.{{ $i }}.action" class="{{ $inp }} w-36">
                        @foreach ($linkActions as $a)
                            <option value="{{ $a }}">{{ ucfirst(str_replace('_', ' ', $a)) }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="moveLinkRule({{ $i }}, 'up')" class="text-slate-400 hover:text-primary" title="Move up"><x-icon name="chevron-right" class="h-4 w-4 -rotate-90" /></button>
                    <button type="button" wire:click="moveLinkRule({{ $i }}, 'down')" class="text-slate-400 hover:text-primary" title="Move down"><x-icon name="chevron-right" class="h-4 w-4 rotate-90" /></button>
                    <button type="button" wire:click="removeLinkRule({{ $i }})" class="text-slate-400 hover:text-red-500" title="Remove"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="{{ $lbl }}">Deep-link domain</label>
                <input type="text" wire:model="studio.deep_link_domain" placeholder="{{ \App\Support\AppStudio::deepLinkDomain() }}" class="{{ $inp }}">
                <p class="{{ $help }}">Blank uses the platform's own domain.</p>
            </div>
            <div>
                <label class="{{ $lbl }}">Sidebar menu (auto from platform links)</label>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-[11px] text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
                    @foreach ($sidebarMenu as $m)
                        <span class="mr-2 inline-block">{{ $m['label'] }}</span>
                    @endforeach
                </div>
                <p class="{{ $help }}">Pulled from your real legal/nav routes — no need to re-enter.</p>
            </div>
        </div>
    </div>

    {{-- ---- Permissions ------------------------------------------------ --}}
    <div class="mt-6 border-t border-slate-100 pt-5 dark:border-white/5">
        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Permissions</h3>
        <p class="mb-3 text-[11px] text-slate-400 dark:text-slate-500">
            Usage descriptions are pre-filled with real Naara reasons — a vague string is an App Store rejection risk.
        </p>
        <div class="space-y-3">
            @foreach ($permissions as $key => $perm)
                <div class="rounded-lg border border-slate-200 p-3 dark:border-white/10" wire:key="perm-{{ $key }}">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                        <x-ui.switch wire:model="permissions.{{ $key }}.enabled" /> {{ $perm['label'] }}
                    </label>
                    <input type="text" wire:model="permissions.{{ $key }}.description" class="{{ $inp }} mt-2 text-xs"
                           placeholder="Usage description shown at the OS prompt">
                    @error("permissions.$key.description") <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </div>

    {{-- ---- Push + Security -------------------------------------------- --}}
    <div class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 dark:border-white/5">
        <div class="rounded-lg border p-3 {{ $pushStatus['ready'] ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/5' : 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/5' }}">
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Push notifications</p>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ $pushStatus['note'] }}</p>
        </div>
        <div class="space-y-2">
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Security</p>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <x-ui.switch wire:model="studio.disallow_insecure_http" /> Disallow insecure http:// content
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <x-ui.switch wire:model="studio.bridge_restrict_own_domain" /> Restrict native bridge to our domain
            </label>
        </div>
    </div>

    <div class="mt-5 flex justify-end">
        <x-ui.btn variant="primary" type="button" wire:click="saveStudio" target="saveStudio" icon="check">Save App Studio</x-ui.btn>
    </div>
</div>
