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

    {{-- Registry --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
        <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Registered instances</h2>

        @if ($this->instances->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">No white-label instances are registered yet. Registration + activation arrives with the license authority (Batch 6).</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-400 dark:text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Brand</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Tier</th>
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
                                <td class="py-2 pr-4">{{ $i->tier ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $i->current_platform_version ?? '—' }}</td>
                                <td class="py-2 pr-4 text-slate-500 dark:text-slate-400">{{ $i->last_checked_in_at?->diffForHumans() ?? 'never' }}</td>
                                <td class="py-2 pr-4">
                                    <button type="button" wire:click="selectInstance({{ $i->id }})"
                                        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                        {{ $selectedInstanceId === $i->id ? 'Hide log' : 'View log' }}
                                    </button>
                                </td>
                            </tr>
                            @if ($selectedInstanceId === $i->id)
                                <tr>
                                    <td colspan="6" class="bg-slate-50 p-3 dark:bg-[#243352]">
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
