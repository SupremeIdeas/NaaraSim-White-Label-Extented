<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">White-label oversight</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Registered brand instances, their tier and status, and every call their deployed copy makes to the distribution API.</p>
        </div>
        <button type="button" wire:click="toggleApi"
            class="rounded-lg border px-3 py-1.5 text-sm font-medium {{ $this->apiEnabled ? 'border-green-300 bg-green-50 text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300' : 'border-slate-200 text-slate-600 dark:border-[#2D4060] dark:text-slate-300' }}">
            Distribution API: {{ $this->apiEnabled ? 'On' : 'Off' }}
        </button>
    </div>

    {{-- One-time credential reveal --}}
    @if ($revealedKey || $revealedToken)
        <div class="mb-6 rounded-2xl border border-teal-300 bg-teal-50 p-5 dark:border-teal-800/60 dark:bg-teal-950/30">
            <div class="flex items-start justify-between gap-4">
                <div>
                    @if ($revealedKey)
                        <h3 class="text-sm font-semibold text-teal-900 dark:text-teal-200">License key issued</h3>
                        <p class="mt-1 text-xs text-teal-700 dark:text-teal-300/80">Hand this to the brand owner. They enter it in their deployment to activate and receive an API token. Keep it safe — regenerating it invalidates the old one.</p>
                        <code class="mt-2 inline-block rounded-lg bg-white px-3 py-1.5 font-mono text-sm text-teal-900 dark:bg-[#0D1B2A] dark:text-teal-200">{{ $revealedKey }}</code>
                    @endif
                    @if ($revealedToken)
                        <h3 class="{{ $revealedKey ? 'mt-3 ' : '' }}text-sm font-semibold text-teal-900 dark:text-teal-200">API token (shown once)</h3>
                        <p class="mt-1 text-xs text-teal-700 dark:text-teal-300/80">This is a bearer credential — it will not be shown again. Copy it now and hand it over securely for a fork that cannot reach the activate endpoint.</p>
                        <code class="mt-2 block break-all rounded-lg bg-white px-3 py-1.5 font-mono text-xs text-teal-900 dark:bg-[#0D1B2A] dark:text-teal-200">{{ $revealedToken }}</code>
                    @endif
                </div>
                <button type="button" wire:click="clearReveal" class="shrink-0 rounded-lg border border-teal-300 px-2.5 py-1 text-xs font-medium text-teal-700 hover:bg-teal-100 dark:border-teal-800/60 dark:text-teal-300 dark:hover:bg-teal-900/30">Dismiss</button>
            </div>
        </div>
    @endif

    {{-- Issue a new license --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
        <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Issue a license</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Registers a brand instance and mints its license key at the chosen tier. The tier decides which distributable packages the brand is entitled to.</p>
        <form wire:submit="issueNewLicense" class="grid gap-3 sm:grid-cols-4">
            <div class="sm:col-span-1">
                <input type="text" wire:model="newBrand" placeholder="Brand name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
                @error('newBrand') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-1">
                <input type="email" wire:model="newEmail" placeholder="Contact email" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
                @error('newEmail') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-1">
                <select wire:model="newTier" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @foreach ($this->tierOptions as $t)
                        <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-1">
                <button type="submit" wire:loading.attr="disabled" wire:target="issueNewLicense"
                    class="w-full rounded-lg bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-60 dark:bg-teal-700 dark:hover:bg-teal-600">
                    <span wire:loading.remove wire:target="issueNewLicense">Issue license</span>
                    <span wire:loading wire:target="issueNewLicense">Issuing…</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Registry --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
        <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Registered instances</h2>

        @if ($this->instances->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">No white-label instances are registered yet. Issue a license above, or a brand can file a self-serve registration request.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-400 dark:text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Brand</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Tier</th>
                            <th class="py-2 pr-4">License</th>
                            <th class="py-2 pr-4">Version</th>
                            <th class="py-2 pr-4">Last check-in</th>
                            <th class="py-2 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-[#2D4060]">
                        @foreach ($this->instances as $i)
                            <tr class="text-slate-700 dark:text-slate-200">
                                <td class="py-2 pr-4">
                                    <span class="font-medium">{{ $i->brand_name }}</span>
                                    <span class="block text-xs text-slate-400">{{ $i->slug }}</span>
                                </td>
                                <td class="py-2 pr-4">
                                    @php
                                        $tone = match ($i->status) {
                                            'active' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                                            'suspended' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                            'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                            default => 'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-300',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tone }}">{{ $i->status }}</span>
                                </td>
                                <td class="py-2 pr-4">
                                    <span>{{ $i->tier ? ucfirst($i->tier) : '—' }}</span>
                                    {{-- Batch 8: feature-entitlement level. Live-editable for an active
                                         instance (raise a paid Normal fork basic→standard). --}}
                                    @if ($i->status === 'active' && $i->entitlement_level)
                                        <select wire:change="setLevel({{ $i->id }}, $event.target.value)"
                                            class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-[11px] text-slate-600 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300">
                                            @foreach ($this->featureLevels as $lvl)
                                                <option value="{{ $lvl }}" @selected($i->entitlement_level === $lvl)>{{ ucfirst($lvl) }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($i->entitlement_level)
                                        <span class="block text-[11px] text-slate-400">{{ ucfirst($i->entitlement_level) }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($i->license_revoked_at)
                                        <span class="text-xs font-medium text-red-600 dark:text-red-400">Revoked</span>
                                    @elseif ($i->license_key)
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Issued</span>
                                        @if ($i->api_token_last_four)
                                            <span class="block text-[11px] text-slate-400">token …{{ $i->api_token_last_four }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $i->current_platform_version ?? '—' }}</td>
                                <td class="py-2 pr-4 text-slate-500 dark:text-slate-400">{{ $i->last_checked_in_at?->diffForHumans() ?? 'never' }}</td>
                                <td class="py-2 pr-4">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        @if ($i->status === 'pending')
                                            <button type="button" wire:click="approveInstance({{ $i->id }}, 'normal')" class="rounded-lg border border-green-300 px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-50 dark:border-green-900/50 dark:text-green-300 dark:hover:bg-green-950/30">Approve · Normal</button>
                                            <button type="button" wire:click="approveInstance({{ $i->id }}, 'extended')" class="rounded-lg border border-green-300 px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-50 dark:border-green-900/50 dark:text-green-300 dark:hover:bg-green-950/30">Approve · Extended</button>
                                            <button type="button" wire:click="rejectInstance({{ $i->id }})" wire:confirm="Reject this registration request?" class="rounded-lg border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:text-red-300 dark:hover:bg-red-950/30">Reject</button>
                                        @elseif ($i->status === 'active')
                                            <button type="button" wire:click="issueTokenFor({{ $i->id }})" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Issue token</button>
                                            <button type="button" wire:click="regenerateKey({{ $i->id }})" wire:confirm="Generate a new license key? The current key stops working immediately." class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Regenerate key</button>
                                            <button type="button" wire:click="suspendInstance({{ $i->id }})" wire:confirm="Suspend this instance? Its token is revoked until restored." class="rounded-lg border border-amber-300 px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50 dark:border-amber-900/50 dark:text-amber-300 dark:hover:bg-amber-950/30">Suspend</button>
                                            <button type="button" wire:click="revokeLicense({{ $i->id }})" wire:confirm="Permanently revoke this license? The key can never be used again." class="rounded-lg border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:text-red-300 dark:hover:bg-red-950/30">Revoke</button>
                                        @elseif ($i->status === 'suspended')
                                            @unless ($i->license_revoked_at)
                                                <button type="button" wire:click="restoreInstance({{ $i->id }})" class="rounded-lg border border-green-300 px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-50 dark:border-green-900/50 dark:text-green-300 dark:hover:bg-green-950/30">Restore</button>
                                                <button type="button" wire:click="revokeLicense({{ $i->id }})" wire:confirm="Permanently revoke this license? The key can never be used again." class="rounded-lg border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:text-red-300 dark:hover:bg-red-950/30">Revoke</button>
                                            @endunless
                                        @endif
                                        <button type="button" wire:click="selectInstance({{ $i->id }})"
                                            class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                            {{ $selectedInstanceId === $i->id ? 'Hide log' : 'View log' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @if ($selectedInstanceId === $i->id)
                                <tr>
                                    <td colspan="7" class="bg-slate-50 p-3 dark:bg-[#243352]">
                                        @if ($this->selectedLogs->isEmpty())
                                            <p class="text-xs text-slate-500 dark:text-slate-400">No API calls recorded for this instance yet.</p>
                                        @else
                                            <table class="w-full text-left text-xs">
                                                <thead class="uppercase text-slate-400">
                                                    <tr><th class="py-1 pr-3">When</th><th class="py-1 pr-3">Endpoint</th><th class="py-1 pr-3">Status</th><th class="py-1 pr-3">IP</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($this->selectedLogs as $log)
                                                        <tr class="text-slate-600 dark:text-slate-300">
                                                            <td class="py-1 pr-3">{{ $log->created_at?->diffForHumans() }}</td>
                                                            <td class="py-1 pr-3">{{ $log->method }} {{ $log->endpoint }}</td>
                                                            <td class="py-1 pr-3">{{ $log->response_status }}</td>
                                                            <td class="py-1 pr-3">{{ $log->ip_address ?? '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Feature locks by level (Batch 8) --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
        <h2 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Feature locks by level</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Which features each entitlement level locks for white-label forks. A ticked box means the feature is <strong>locked</strong> at that level. Basic is a Normal fork before it pays up; Standard after; Full is Extended (everything open). Forks pick up changes on their next check-in.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-400 dark:text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Feature</th>
                        @foreach ($this->featureLevels as $lvl)
                            <th class="py-2 pr-4 text-center">{{ ucfirst($lvl) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#2D4060]">
                    @foreach ($this->featureCatalog as $key => $label)
                        <tr class="text-slate-700 dark:text-slate-200">
                            <td class="py-2 pr-4 font-medium">{{ $label }}</td>
                            @foreach ($this->featureLevels as $lvl)
                                <td class="py-2 pr-4 text-center">
                                    <input type="checkbox" wire:click="toggleFeatureLock('{{ $lvl }}', '{{ $key }}')"
                                        @checked(in_array($key, $this->featureLocks[$lvl] ?? [], true))
                                        class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352]">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Published packages --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
        <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Distributable packages</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Building a package and publishing it to instances are two deliberate steps. Only published packages are ever offered to white-label instances.</p>

        @if ($this->packages->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">No packages registered for distribution yet. Build one with <code class="rounded bg-slate-100 px-1 dark:bg-[#243352]">php artisan update:package --publish</code>.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-400 dark:text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Version</th>
                            <th class="py-2 pr-4">Product</th>
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Tier</th>
                            <th class="py-2 pr-4">Published</th>
                            <th class="py-2 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-[#2D4060]">
                        @foreach ($this->packages as $p)
                            <tr class="text-slate-700 dark:text-slate-200">
                                <td class="py-2 pr-4 font-medium">{{ $p->version }}</td>
                                <td class="py-2 pr-4">{{ $p->product }}</td>
                                <td class="py-2 pr-4">{{ $p->package_type }}</td>
                                <td class="py-2 pr-4">{{ $p->tier_requirement ?? '—' }}</td>
                                <td class="py-2 pr-4">
                                    @if ($p->is_published)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">Published</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-[#243352] dark:text-slate-300">Staged</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="togglePublish({{ $p->id }})"
                                            class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                            {{ $p->is_published ? 'Unpublish' : 'Publish' }}
                                        </button>
                                        <button type="button" wire:click="withdrawPackage({{ $p->id }})" wire:confirm="Withdraw {{ $p->version }} from distribution and delete its file?"
                                            class="rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:text-red-300 dark:hover:bg-red-950/30">
                                            Withdraw
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
