<div class="mx-auto max-w-4xl" wire:poll.5s>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Platform updater</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Install signed <code class="rounded bg-slate-100 px-1 dark:bg-[#243352]">.naaraupdate</code> packages — code
            updates or themes. Current version:
            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $this->currentVersion ?? 'not yet recorded' }}</span>
        </p>
    </div>

    {{-- Tabs --}}
    <div class="mb-6 flex gap-1 border-b border-slate-200 dark:border-[#2D4060]">
        <button type="button" wire:click="$set('tab', 'code')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'code' ? 'border-[#0A6E6E] text-[#0A6E6E] dark:text-teal-300' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
            Code updates
        </button>
        <button type="button" wire:click="$set('tab', 'themes')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'themes' ? 'border-[#0A6E6E] text-[#0A6E6E] dark:text-teal-300' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
            Themes
        </button>
    </div>

    @if ($tab === 'code')
        @if ($status)
            <div class="mb-5 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800 dark:border-teal-900/50 dark:bg-teal-950/30 dark:text-teal-200">
                <x-icon name="info" class="mr-1 inline h-4 w-4" /> {{ $status }}
            </div>
        @endif

        {{-- Upload + verify --}}
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
            <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Upload a package</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Every apply takes a full backup first and rolls itself back automatically if anything fails.</p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-sm font-medium text-slate-600 dark:text-slate-300">Package file (.naaraupdate)</label>
                    <input type="file" wire:model="package" accept=".naaraupdate"
                        class="block w-full rounded-lg border border-slate-200 text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300 dark:file:bg-[#2D4060]" />
                </div>
                <button type="button" wire:click="verifyPackage"
                    wire:loading.attr="disabled" wire:target="verifyPackage,package"
                    class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50 dark:bg-[#0A6E6E] dark:hover:bg-[#0A5E5E]">
                    <span wire:loading.remove wire:target="verifyPackage,package">Verify</span>
                    <span wire:loading wire:target="verifyPackage,package">Verifying…</span>
                </button>
            </div>

            @error('package')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @if ($verifyError)
                <div class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                    <span class="font-semibold">Rejected:</span> {{ $verifyError }}
                </div>
            @endif
        </div>

        {{-- Verified manifest → confirm apply --}}
        @if ($verified)
            <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-5 dark:border-green-900/50 dark:bg-green-950/30">
                <p class="mb-3 flex items-center gap-2 font-semibold text-green-700 dark:text-green-300">
                    <x-icon name="check" class="h-5 w-5" /> Verified — genuine and untampered
                </p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm text-slate-700 dark:text-slate-200 sm:grid-cols-3">
                    <div><dt class="text-slate-500 dark:text-slate-400">Version</dt><dd class="font-medium">{{ $verified['version'] }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Product</dt><dd class="font-medium">{{ $verified['product'] }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Type</dt><dd class="font-medium">{{ $verified['package_type'] }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Min compatible</dt><dd class="font-medium">{{ $verified['min_compatible'] }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Files</dt><dd class="font-medium">{{ $verified['files'] }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Migrations</dt><dd class="font-medium">{{ $verified['migrations'] }}</dd></div>
                </dl>
                @if ($verified['changelog'])
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300"><span class="font-semibold">Changelog:</span> {{ $verified['changelog'] }}</p>
                @endif

                <button type="button" wire:click="applyPackage"
                    wire:confirm="Apply {{ $verified['version'] }}? The platform enters maintenance mode, takes a full backup, applies files and migrations, and rolls back automatically if the health check fails."
                    wire:loading.attr="disabled" wire:target="applyPackage"
                    class="mt-4 inline-flex items-center justify-center rounded-lg bg-[#0A6E6E] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0A5E5E] disabled:opacity-50">
                    <span wire:loading.remove wire:target="applyPackage">Apply this update</span>
                    <span wire:loading wire:target="applyPackage">Queueing…</span>
                </button>
            </div>
        @endif

        {{-- History --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
            <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Apply history</h2>

            @if ($this->attempts->isEmpty())
                <p class="text-sm text-slate-500 dark:text-slate-400">No updates have been applied yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-slate-400 dark:text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">When</th>
                                <th class="py-2 pr-4">Version</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Files</th>
                                <th class="py-2 pr-4">Migrations</th>
                                <th class="py-2 pr-4">Downtime</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-[#2D4060]">
                            @foreach ($this->attempts as $a)
                                <tr class="text-slate-700 dark:text-slate-200">
                                    <td class="py-2 pr-4 text-slate-500 dark:text-slate-400">{{ $a->created_at->diffForHumans() }}</td>
                                    <td class="py-2 pr-4">{{ $a->from_version ?? '—' }} → {{ $a->to_version }}</td>
                                    <td class="py-2 pr-4">
                                        @php
                                            $tone = match ($a->status) {
                                                'succeeded' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                                                'rolled_back' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                                'failed_unrecoverable' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                                default => 'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-300',
                                            };
                                        @endphp
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tone }}">{{ str_replace('_', ' ', $a->status) }}</span>
                                    </td>
                                    <td class="py-2 pr-4">{{ $a->files_changed_count ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $a->migrations_run_count ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $a->downtime_seconds !== null ? $a->downtime_seconds.'s' : '—' }}</td>
                                </tr>
                                @if ($a->failure_reason)
                                    <tr><td colspan="6" class="pb-2 text-xs text-red-600 dark:text-red-400">{{ $a->failure_reason }}</td></tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        {{-- Themes tab --}}
        @if ($themeStatus)
            <div class="mb-5 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800 dark:border-teal-900/50 dark:bg-teal-950/30 dark:text-teal-200">
                <x-icon name="info" class="mr-1 inline h-4 w-4" /> {{ $themeStatus }}
            </div>
        @endif

        {{-- Upload + preview --}}
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
            <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Install a theme package</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">A theme is paint, not plumbing — installing one writes a single row and copies a few images; no maintenance mode, no backup needed.</p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-sm font-medium text-slate-600 dark:text-slate-300">Theme package file (.naaraupdate)</label>
                    <input type="file" wire:model="themePackage" accept=".naaraupdate"
                        class="block w-full rounded-lg border border-slate-200 text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300 dark:file:bg-[#2D4060]" />
                </div>
                <button type="button" wire:click="verifyTheme"
                    wire:loading.attr="disabled" wire:target="verifyTheme,themePackage"
                    class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50 dark:bg-[#0A6E6E] dark:hover:bg-[#0A5E5E]">
                    <span wire:loading.remove wire:target="verifyTheme,themePackage">Verify</span>
                    <span wire:loading wire:target="verifyTheme,themePackage">Verifying…</span>
                </button>
            </div>

            @error('themePackage')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @if ($themeVerifyError)
                <div class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                    <span class="font-semibold">Rejected:</span> {{ $themeVerifyError }}
                </div>
            @endif
        </div>

        {{-- Verified theme preview → confirm install --}}
        @if ($themePreview)
            <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-5 dark:border-green-900/50 dark:bg-green-950/30">
                <p class="mb-3 flex items-center gap-2 font-semibold text-green-700 dark:text-green-300">
                    <x-icon name="check" class="h-5 w-5" /> Verified — genuine and untampered
                </p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm text-slate-700 dark:text-slate-200 sm:grid-cols-3">
                    <div><dt class="text-slate-500 dark:text-slate-400">Name</dt><dd class="font-medium">{{ $themePreview['name'] }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Slug</dt><dd class="font-medium">{{ $themePreview['slug'] }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Icon set</dt><dd class="font-medium">{{ $themePreview['icon_family']['style'] ?? '—' }} / {{ $themePreview['icon_family']['set'] ?? '—' }}</dd></div>
                </dl>
                @if ($themePreview['persona'])
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $themePreview['persona'] }}</p>
                @endif

                @if (! empty($themePreview['swatches']))
                    <div class="mt-3">
                        <p class="mb-1 text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Palette preview</p>
                        <div class="flex overflow-hidden rounded-lg border border-slate-200 dark:border-[#2D4060]" style="height:2.5rem">
                            @foreach ($themePreview['swatches'] as $key => $rgb)
                                <span class="flex-1" style="background: rgb({{ $rgb }})" title="{{ $key }}"></span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($themePreview['font_warnings']))
                    <div class="mt-3 space-y-2">
                        @foreach ($themePreview['font_warnings'] as $warning)
                            <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                                {{ $warning }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <button type="button" wire:click="installTheme"
                    wire:loading.attr="disabled" wire:target="installTheme"
                    class="mt-4 inline-flex items-center justify-center rounded-lg bg-[#0A6E6E] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0A5E5E] disabled:opacity-50">
                    <span wire:loading.remove wire:target="installTheme">Install this theme</span>
                    <span wire:loading wire:target="installTheme">Installing…</span>
                </button>
            </div>
        @endif

        {{-- Installed themes --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
            <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Installed themes</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($this->installedThemes as $t)
                    <div class="rounded-xl border border-slate-200 p-3 dark:border-[#2D4060]">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $t['name'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t['slug'] }}</p>
                            </div>
                            @if ($t['slug'] === $this->activeThemeSlug)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">Active</span>
                            @endif
                        </div>
                        <div class="mt-2 flex gap-2">
                            @if ($t['slug'] !== $this->activeThemeSlug)
                                <button type="button" wire:click="activateTheme('{{ $t['slug'] }}')" wire:confirm="Activate {{ $t['name'] }} platform-wide?"
                                    class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                    Activate
                                </button>
                            @endif
                            @if (! $t['is_built_in'])
                                <button type="button" wire:click="removeTheme('{{ $t['slug'] }}')" wire:confirm="Remove {{ $t['name'] }}? This deletes its stored images too."
                                    class="rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:text-red-300 dark:hover:bg-red-950/30">
                                    Remove
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
