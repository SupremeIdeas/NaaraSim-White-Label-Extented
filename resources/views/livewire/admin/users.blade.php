<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Users</h1>
        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-[#243352]">{{ number_format($totals['all']) }} total</span>
            <span class="rounded-full bg-green-50 px-2.5 py-1 text-green-700 dark:bg-green-950/40 dark:text-green-300">{{ number_format($totals['active']) }} active</span>
            <span class="rounded-full bg-red-50 px-2.5 py-1 text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ number_format($totals['deactivated']) }} paused</span>
        </div>
    </div>

    {{-- Search + filters --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[12rem]">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email…"
                   class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
        </div>
        <select wire:model.live="filter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-200">
            <option value="all">All users</option>
            <option value="active">Active</option>
            <option value="deactivated">Deactivated</option>
            <option value="staff">Staff & admins</option>
            <option value="merchants">Merchants</option>
            <option value="ready">Ready to promote</option>
        </select>
    </div>

    {{-- Skeleton while a search/filter round-trips (premium loading feel). --}}
    <div wire:loading.flex wire:target="search,filter" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        <x-ui.skeleton-rows :count="6" class="w-full" />
    </div>

    <div wire:loading.remove wire:target="search,filter" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400 dark:border-[#243352]">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Joined</th>
                    <th class="px-4 py-3">Engagement</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#243352]">
                @forelse ($users as $u)
                    <tr wire:key="user-{{ $u->id }}" class="hover:bg-slate-50 dark:hover:bg-[#243352]/50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-xs font-bold uppercase text-primary dark:bg-primary/20 dark:text-teal-300">{{ \Illuminate\Support\Str::of($u->name)->trim()->substr(0, 2) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ $u->name }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ $u->email }}</p>
                                </div>
                                @foreach ($u->getRoleNames()->take(2) as $role)
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-[#243352] dark:text-slate-300">{{ str_replace('_', ' ', $role) }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $u->created_at?->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            @php($sc = $scores[$u->id] ?? null)
                            @if ($sc)
                                <span title="{{ $sc['referrals'] }} referrals · {{ $sc['verified_referrals'] }} verified · ${{ number_format($sc['spend'], 0) }} spent{{ $sc['verified'] ? ' · ID verified' : '' }}"
                                      class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $sc['score'] >= 40 ? 'bg-primary/10 text-primary-dark dark:bg-primary/20 dark:text-teal-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400' }}">
                                    <x-icon name="zap" class="h-3 w-3" /> {{ $sc['score'] }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($u->isDeactivated())
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300"><x-icon name="pause" class="h-3 w-3" /> Paused</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950/40 dark:text-green-300"><x-icon name="badge-check" class="h-3 w-3" /> Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                {{-- One-click promote to Merchant V1/V2 (§4.2). --}}
                                <div x-data="{ open: false }" class="relative">
                                    <button type="button" @click="open = !open" @click.outside="open = false"
                                            class="inline-flex items-center gap-1 rounded-lg border border-primary/30 bg-primary/5 px-2.5 py-1.5 text-xs font-semibold text-primary hover:bg-primary/10 dark:border-primary/40 dark:text-teal-300">
                                        <x-icon name="zap" class="h-3.5 w-3.5" /> Promote
                                    </button>
                                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-8 z-10 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white text-left shadow-lg dark:border-[#2D4060] dark:bg-[#1A2840]">
                                        <button wire:click="promote({{ $u->id }}, 'v1')" @click="open = false" wire:confirm="Promote {{ $u->name }} to Merchant V1 (free, no application)?"
                                                class="block w-full px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-[#243352]">Merchant V1</button>
                                        <button wire:click="promote({{ $u->id }}, 'v2')" @click="open = false" wire:confirm="Promote {{ $u->name }} to Merchant V2 (client management)?"
                                                class="block w-full px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-[#243352]">Merchant V2</button>
                                    </div>
                                </div>
                                <button type="button" wire:click="view({{ $u->id }})" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                    {{ $viewing?->id === $u->id ? 'Hide' : 'View' }}
                                </button>
                                <button type="button" wire:click="toggleActive({{ $u->id }})"
                                        wire:confirm="{{ $u->isDeactivated() ? 'Reactivate' : 'Deactivate' }} {{ $u->name }}?"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-semibold {{ $u->isDeactivated() ? 'bg-primary text-white hover:bg-primary-dark' : 'border border-slate-200 text-slate-600 hover:border-red-300 hover:text-red-600 dark:border-[#2D4060] dark:text-slate-300' }}">
                                    {{ $u->isDeactivated() ? 'Reactivate' : 'Deactivate' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    @if ($viewing?->id === $u->id)
                        <tr wire:key="view-{{ $u->id }}" class="bg-slate-50 dark:bg-[#141F33]">
                            <td colspan="5" class="px-4 py-4">
                                <div class="grid gap-4 sm:grid-cols-4">
                                    <div><p class="text-xs text-slate-400">USD wallet</p><p class="font-semibold text-slate-800 dark:text-slate-100">${{ number_format((float) ($viewing->wallet->usd_balance ?? 0), 2) }}</p></div>
                                    {{-- Unified USD Wallet (Part B): ngn_balance no longer grows from new
                                         top-ups — a nonzero figure here is a pre-migration legacy balance. --}}
                                    <div><p class="text-xs text-slate-400">NGN wallet (legacy)</p><p class="font-semibold text-slate-800 dark:text-slate-100">₦{{ number_format((float) ($viewing->wallet->ngn_balance ?? 0), 0) }}</p></div>
                                    <div><p class="text-xs text-slate-400">eSIM orders</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->esimOrders()->count() }}</p></div>
                                    <div><p class="text-xs text-slate-400">Number orders</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->smsOrders()->count() }}</p></div>
                                    <div><p class="text-xs text-slate-400">Country</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->country_code ?: '—' }}</p></div>
                                    <div><p class="text-xs text-slate-400">Display currency</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->display_currency ?: 'USD' }}</p></div>
                                    <div><p class="text-xs text-slate-400">Verified</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->email_verified_at ? 'Yes' : 'No' }}</p></div>
                                    <div><p class="text-xs text-slate-400">Roles</p><p class="font-semibold capitalize text-slate-800 dark:text-slate-100">{{ str_replace('_', ' ', $viewing->getRoleNames()->implode(', ')) ?: 'user' }}</p></div>
                                </div>

                                {{-- Management (owner request — fix_admin Part 4) --}}
                                @if ($tempPassword)
                                    <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800/60 dark:bg-amber-950/30">
                                        <p class="font-semibold text-amber-800 dark:text-amber-200">Temporary password (shown once)</p>
                                        <p class="mt-1 font-mono text-amber-900 dark:text-amber-100">{{ $tempPassword }}</p>
                                        <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">Relay it securely; the user is signed out everywhere and should change it after signing in.</p>
                                    </div>
                                @endif

                                @if ($editing)
                                    <div class="mt-4 grid gap-3 rounded-lg border border-slate-200 p-3 dark:border-[#2D4060] sm:grid-cols-3">
                                        <div><label class="mb-1 block text-[11px] text-slate-400">Name</label><input type="text" wire:model="edit_name" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">@error('edit_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                                        <div><label class="mb-1 block text-[11px] text-slate-400">Email</label><input type="email" wire:model="edit_email" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">@error('edit_email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                                        <div><label class="mb-1 block text-[11px] text-slate-400">Phone</label><input type="text" wire:model="edit_phone" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></div>
                                        <div class="flex items-end gap-2 sm:col-span-3">
                                            <button wire:click="saveUser" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Save changes</button>
                                            <button wire:click="cancelEdit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-600 dark:border-[#2D4060] dark:text-slate-300">Cancel</button>
                                            <span class="text-[11px] text-slate-400">Changing email re-sends a verification link.</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button wire:click="editUser({{ $viewing->id }})" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Edit profile</button>
                                        <button wire:click="sendPasswordReset({{ $viewing->id }})" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Email reset link</button>
                                        <button wire:click="generateTempPassword({{ $viewing->id }})" wire:confirm="Set a temporary password and sign this user out everywhere?" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Temp password</button>
                                        <button wire:click="forceLogout({{ $viewing->id }})" wire:confirm="Sign {{ $viewing->name }} out of all devices?" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Force logout</button>
                                        @role('super_admin')
                                            @if ($viewing->id !== auth()->id())
                                                <button wire:click="toggleAdmin({{ $viewing->id }})" wire:confirm="Change admin role for {{ $viewing->name }}?" class="rounded-lg border border-primary/40 px-3 py-1.5 text-xs font-medium text-primary hover:bg-primary/10">{{ $viewing->hasRole('admin') ? 'Revoke admin' : 'Make admin' }}</button>
                                            @endif
                                        @endrole
                                    </div>
                                @endif

                                @if ($sessions->isNotEmpty())
                                    <div class="mt-4">
                                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Recent sessions</p>
                                        <div class="space-y-1">
                                            @foreach ($sessions as $s)
                                                <div class="flex items-center gap-3 text-[11px] text-slate-500 dark:text-slate-400">
                                                    <span class="font-mono">{{ $s['ip'] ?: '—' }}</span>
                                                    <span class="truncate">{{ $s['agent'] ?: 'unknown device' }}</span>
                                                    <span class="ml-auto shrink-0">{{ $s['when'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No users match your search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</div>
