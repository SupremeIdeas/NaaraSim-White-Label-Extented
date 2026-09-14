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
                                    @if ($i->acquisition_method === \App\Models\WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE)
                                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                            Self-service{{ $i->licensePlan ? ' · '.$i->licensePlan->name : '' }}
                                        </span>
                                        @if ($i->price_usd)
                                            <span class="block text-[11px] text-slate-400">Priced ${{ number_format((float) $i->price_usd, 2) }} · paid ${{ number_format($i->amountPaidTotal(), 2) }}</span>
                                        @endif
                                    @endif
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
                                        @if ($i->status === 'pending' && $i->acquisition_method === \App\Models\WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE)
                                            {{-- Prompt 21-EXT §3.1 — a self-service request is PRICED, not directly
                                                 issued: the merchant's own "pay to activate" charges the wallet and
                                                 issues the license (payAndActivate). Admin only confirms the price. --}}
                                            <div class="flex items-center gap-1">
                                                <span class="text-slate-400">$</span>
                                                <input type="number" step="0.01" min="0.01"
                                                    wire:model="priceInputs.{{ $i->id }}"
                                                    value="{{ $priceInputs[$i->id] ?? $i->price_usd ?? $i->licensePlan?->price_usd }}"
                                                    class="w-24 rounded-lg border border-slate-200 px-2 py-1 text-xs dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"
                                                    placeholder="Price">
                                                <button type="button" wire:click="priceInstance({{ $i->id }})" class="rounded-lg border border-green-300 px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-50 dark:border-green-900/50 dark:text-green-300 dark:hover:bg-green-950/30">{{ $i->price_usd ? 'Update price' : 'Set price' }}</button>
                                            </div>
                                            <button type="button" wire:click="rejectInstance({{ $i->id }})" wire:confirm="Reject this registration request?" class="rounded-lg border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:text-red-300 dark:hover:bg-red-950/30">Reject</button>
                                        @elseif ($i->status === 'pending')
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
                                        @if ($i->intake)
                                            <button type="button" wire:click="toggleIntakeDetail({{ $i->id }})"
                                                class="rounded-lg border px-2 py-1 text-xs font-medium {{ $i->intake->status === 'pending' ? 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-900/50 dark:text-amber-300 dark:hover:bg-amber-950/30' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]' }}">
                                                {{ $expandedIntakeInstanceId === $i->id ? 'Hide project' : 'Project ('.ucfirst(str_replace('_', ' ', $i->intake->status)).')' }}
                                            </button>
                                        @endif
                                        <button type="button" wire:click="selectInstance({{ $i->id }})"
                                            class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                            {{ $selectedInstanceId === $i->id ? 'Hide log' : 'View log' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @if ($expandedIntakeInstanceId === $i->id && $i->intake)
                                @php $intake = $i->intake; $progress = $this->intakeProgress; $dayOf = $this->intakeDayOf; @endphp
                                <tr>
                                    <td colspan="7" class="bg-slate-50 p-4 dark:bg-[#243352]">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div class="space-y-1 text-xs text-slate-600 dark:text-slate-300">
                                                <p><span class="font-medium text-slate-800 dark:text-slate-100">Desired name:</span> {{ $intake->desired_brand_name }}</p>
                                                <p><span class="font-medium text-slate-800 dark:text-slate-100">WhatsApp:</span> {{ $intake->whatsapp_number }}</p>
                                                <p class="flex items-center gap-2">
                                                    <span class="font-medium text-slate-800 dark:text-slate-100">Brand colours:</span>
                                                    <span class="inline-block h-4 w-4 rounded-full border border-slate-300" style="background:{{ $intake->brand_primary_color }}"></span>
                                                    <span class="inline-block h-4 w-4 rounded-full border border-slate-300" style="background:{{ $intake->brand_accent_color }}"></span>
                                                    {{ $intake->brand_primary_color }} / {{ $intake->brand_accent_color }}
                                                </p>
                                                @if ($intake->logo_url)
                                                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Logo:</span> <a href="{{ $intake->logo_url }}" target="_blank" class="text-primary underline">View uploaded logo</a></p>
                                                @elseif ($intake->logo_design_reference)
                                                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Logo design reference:</span> {{ $intake->logo_design_reference }}</p>
                                                @endif
                                                @if ($intake->banner_reference_url)
                                                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Banner reference:</span> <a href="{{ $intake->banner_reference_url }}" target="_blank" class="text-primary underline">View uploaded reference</a></p>
                                                @endif
                                                @if ($intake->banner_design_request)
                                                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Banner request:</span> {{ $intake->banner_design_request }}</p>
                                                @endif
                                                <p><span class="font-medium text-slate-800 dark:text-slate-100">Hosting:</span> {{ str_replace('_', ' ', ucfirst($intake->hosting_choice)) }}</p>
                                                @if ($intake->isSelfHosted())
                                                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Host:</span> {{ $intake->hosting_host }}</p>
                                                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Username:</span> {{ $intake->hosting_username }}</p>
                                                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Password:</span> <span class="font-mono">{{ $intake->hosting_password }}</span></p>
                                                    @if ($intake->hosting_notes)
                                                        <p><span class="font-medium text-slate-800 dark:text-slate-100">Access notes:</span> {{ $intake->hosting_notes }}</p>
                                                    @endif
                                                @endif
                                                @if ($intake->additional_notes)
                                                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Additional notes:</span> {{ $intake->additional_notes }}</p>
                                                @endif
                                            </div>
                                            <div class="space-y-3">
                                                @if ($intake->status === 'pending')
                                                    <button type="button" wire:click="markIntakeSeen({{ $intake->id }})" class="rounded-lg border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50 dark:border-green-900/50 dark:text-green-300 dark:hover:bg-green-950/30">Mark seen</button>
                                                @elseif ($intake->status === 'seen')
                                                    <div class="flex items-center gap-2">
                                                        <input type="number" min="1" wire:model="deployDaysInputs.{{ $intake->id }}" placeholder="Days" class="w-24 rounded-lg border border-slate-200 px-2 py-1.5 text-xs dark:border-[#2D4060] dark:bg-[#1B2A45] dark:text-slate-100">
                                                        <button type="button" wire:click="setIntakeDeployTimeline({{ $intake->id }})" class="rounded-lg border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50 dark:border-green-900/50 dark:text-green-300 dark:hover:bg-green-950/30">Set deploy timeline</button>
                                                    </div>
                                                @elseif ($intake->status === 'in_progress')
                                                    <p class="text-xs text-slate-500 dark:text-slate-400">Day {{ $dayOf['day'] }} of {{ $dayOf['of'] }} — {{ $progress }}%</p>
                                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-[#1B2A45]">
                                                        <div class="h-full rounded-full bg-primary" style="width: {{ $progress }}%"></div>
                                                    </div>
                                                @elseif ($intake->status === 'completed')
                                                    <p class="text-xs font-medium text-green-700 dark:text-green-300">Deployment complete.</p>
                                                @endif
                                                <a href="{{ route('admin.white-label.intake.pdf', $intake->id) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                                    <x-icon name="download" class="h-3.5 w-3.5" /> Export PDF
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
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

    {{-- Prompt 21-EXT §1.4/§6.5 — license plan catalog + resell-status gating --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
        <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-100">License plans &amp; resell status</h2>

        {{-- Resell-status toggles with live threshold counts --}}
        <div class="mb-5 grid gap-3 sm:grid-cols-2">
            @foreach ($this->resellStatus as $tier => $status)
                <div class="rounded-xl border border-slate-200 p-3 dark:border-[#2D4060]">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ ucfirst($tier) }} resell</p>
                        <button type="button" wire:click="toggleResell('{{ $tier }}')"
                            class="rounded-full px-2.5 py-1 text-xs font-medium {{ $status['open'] ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">
                            {{ $status['open'] ? 'Open' : 'Closed' }}
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ $status['count'] }} / {{ $status['threshold'] }} self-service sales (auto-closes at threshold)</p>
                </div>
            @endforeach
        </div>

        {{-- Plan create/edit form --}}
        <form wire:submit="savePlan" class="mb-5 grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-[#2D4060] sm:grid-cols-2">
            <div class="sm:col-span-2 flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $editingPlanId ? 'Edit plan' : 'New plan' }}</p>
                @if ($editingPlanId)
                    <button type="button" wire:click="newPlanForm" class="text-xs font-medium text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">Cancel edit</button>
                @endif
            </div>
            <div>
                <input type="text" wire:model="planName" placeholder="Name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
                @error('planName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <input type="text" wire:model="planTagline" placeholder="Tagline" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
                @error('planTagline') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-2">
                <textarea wire:model="planDescription" rows="2" placeholder="Description" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                @error('planDescription') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <input type="number" step="0.01" min="0.01" wire:model="planPrice" placeholder="Price (USD)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
                @error('planPrice') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <input type="number" min="0" wire:model="planSortOrder" placeholder="Sort order" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
            </div>
            <div>
                <select wire:model="planTier" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @foreach ($this->tierOptions as $t)
                        <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select wire:model="planSupportLevel" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="standard">Standard support</option>
                    <option value="priority">Priority support</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <textarea wire:model="planFeaturesText" rows="3" placeholder="One feature per line" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
            </div>
            <div class="sm:col-span-2">
                <input type="file" wire:model="planCoverUpload" accept="image/webp,image/jpeg,image/png" class="block w-full text-xs text-slate-500 dark:text-slate-400" />
                @error('planCoverUpload') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                <div wire:loading wire:target="planCoverUpload" class="mt-1 text-xs text-slate-400">Uploading…</div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" wire:model="planIsActive" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352]"> Active
            </label>
            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="savePlan"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    {{ $editingPlanId ? 'Update plan' : 'Create plan' }}
                </button>
            </div>
        </form>

        {{-- Plan list --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-400 dark:text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Cover</th>
                        <th class="py-2 pr-4">Plan</th>
                        <th class="py-2 pr-4">Price</th>
                        <th class="py-2 pr-4">Tier</th>
                        <th class="py-2 pr-4">Support</th>
                        <th class="py-2 pr-4">Active</th>
                        <th class="py-2 pr-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#2D4060]">
                    @foreach ($this->licensePlans as $plan)
                        <tr class="text-slate-700 dark:text-slate-200">
                            <td class="py-2 pr-4">
                                @if ($plan->cover_image_url)
                                    <img src="{{ $plan->cover_image_url }}" alt="" class="h-10 w-16 rounded-md object-cover">
                                @else
                                    <div class="flex h-10 w-16 items-center justify-center rounded-md bg-slate-100 text-slate-300 dark:bg-[#243352]"><x-icon name="image" class="h-4 w-4" /></div>
                                @endif
                            </td>
                            <td class="py-2 pr-4 font-medium">{{ $plan->name }}<span class="block text-xs font-normal text-slate-400">{{ $plan->tagline }}</span></td>
                            <td class="py-2 pr-4">${{ number_format((float) $plan->price_usd, 2) }}</td>
                            <td class="py-2 pr-4">{{ ucfirst($plan->tier) }}</td>
                            <td class="py-2 pr-4">{{ ucfirst($plan->support_level) }}</td>
                            <td class="py-2 pr-4">
                                <button type="button" wire:click="togglePlanActive({{ $plan->id }})" class="rounded-full px-2 py-0.5 text-xs font-medium {{ $plan->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-300' }}">
                                    {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="py-2 pr-4">
                                <button type="button" wire:click="editPlan({{ $plan->id }})" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Edit</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Prompt 21-EXT §5.3/§5.5 — platform earnings wallet + withdrawal.
         super_admin only: this bucket is deliberately kept separate from
         general platform-profit reporting, so cashing it out is a stricter
         action than the day-to-day oversight this whole screen otherwise
         allows for "admin" too. --}}
    @if ($this->isSuperAdmin)
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
            <h2 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Platform earnings</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">White-label license sale proceeds — kept separate from general platform profit. Withdraw to your own verified payout account, same as any other cash-out.</p>

            <div class="mb-4 rounded-xl border border-slate-200 p-3 dark:border-[#2D4060]">
                <p class="text-xs text-slate-400">Available balance</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white">${{ number_format($this->platformBalance, 2) }}</p>
            </div>

            @if ($platformWithdrawError)
                <div class="mb-3 rounded-lg border border-red-200 bg-red-50 p-2 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">{{ $platformWithdrawError }}</div>
            @endif

            @if ($this->platformAccounts->isEmpty())
                <p class="text-xs text-slate-400">Add a verified payout account to your own profile before withdrawing.</p>
            @else
                <form wire:submit="withdrawPlatformEarnings" class="grid gap-3 sm:grid-cols-3">
                    <select wire:model="platformAccountId" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @foreach ($this->platformAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->bank_name ?? $account->bank_code }} · {{ $account->account_name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0.01" wire:model="platformAmountUsd" placeholder="Amount (USD)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100" />
                    @error('platformAmountUsd') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    <button type="submit" wire:loading.attr="disabled" wire:target="withdrawPlatformEarnings"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                        Withdraw
                    </button>
                </form>
            @endif
        </div>
    @endif

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
                                    @if ($p->isMasterOnly())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-800 px-2 py-0.5 text-xs font-medium text-white dark:bg-black">
                                            <x-icon name="lock" class="h-3 w-3" /> Master-only
                                        </span>
                                    @elseif ($p->is_published)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">Published</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-[#243352] dark:text-slate-300">Staged</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    <div class="flex gap-2">
                                        @if ($p->isMasterOnly())
                                            {{-- Master-Only Distribution Lock: this package can NEVER be
                                                 published to white label — enforced server-side in
                                                 PackagePublisher, not just hidden here. No Publish
                                                 button at all, so an admin never wonders why nothing
                                                 happened. --}}
                                            <span class="flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-400 dark:border-[#2D4060] dark:text-slate-500" title="Master-only packages can never be distributed to white label.">
                                                <x-icon name="lock" class="h-3 w-3" /> Cannot be distributed
                                            </span>
                                        @else
                                            <button type="button" wire:click="togglePublish({{ $p->id }})"
                                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                                {{ $p->is_published ? 'Unpublish' : 'Publish' }}
                                            </button>
                                        @endif
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
