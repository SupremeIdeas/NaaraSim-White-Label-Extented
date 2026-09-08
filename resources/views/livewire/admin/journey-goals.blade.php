<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Journey Goals</h1>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Achievements shown on every user's "My Journey" page. Each metric is computed live from real activity — reaching the target pays out NaaraCredits automatically, once per user per period.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    {{-- Create / edit --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
            <x-icon name="star" class="h-4 w-4 text-primary" /> {{ $editingId ? 'Edit goal' : 'New goal' }}
        </h2>

        <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Title (shown to users)</label>
                <input wire:model="title" type="text" placeholder="World Traveler"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('title') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Description</label>
                <input wire:model="description" type="text" placeholder="Buy eSIMs for 5 different countries"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('description') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Goal image (optional)</label>
                <div class="flex items-center gap-3">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-[#2D4060] dark:bg-[#243352]">
                        @if ($image)
                            <img src="{{ $image->temporaryUrl() }}" class="h-full w-full object-cover">
                        @elseif ($currentImagePath)
                            <img src="{{ $currentImagePath }}" class="h-full w-full object-cover">
                        @else
                            <x-icon name="star" class="h-6 w-6 text-slate-300" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <input type="file" wire:model="image" accept="image/webp,image/png,image/jpeg"
                               class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-primary dark:text-slate-400">
                        <p class="mt-1 text-[11px] text-slate-400">WebP, PNG or JPG, up to 2&nbsp;MB. Replaces the star icon badge on My Journey when set.</p>
                        @error('image') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    @if ($currentImagePath)
                        <button type="button" wire:click="removeImage" wire:confirm="Remove this goal's image and fall back to the icon badge?"
                                class="shrink-0 text-xs font-medium text-red-600 hover:underline">Remove</button>
                    @endif
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Metric</label>
                <select wire:model="metric" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @foreach ($metrics as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Target</label>
                <input wire:model="target" type="number" min="0.01" step="0.01"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('target') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Period</label>
                <select wire:model.live="period_type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="lifetime">Lifetime (one-time)</option>
                    <option value="monthly">Monthly (repeats)</option>
                    <option value="quarterly">Quarterly (repeats)</option>
                    <option value="yearly">Yearly (repeats)</option>
                    <option value="campaign">Campaign (fixed dates)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Audience</label>
                <select wire:model="audience" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="all">Everyone</option>
                    <option value="merchant">Merchants</option>
                    <option value="merchant_v2">Merchant V2 only</option>
                </select>
            </div>
            @if ($period_type === 'campaign')
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Starts</label>
                    <input wire:model="starts_at" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('starts_at') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Ends</label>
                    <input wire:model="ends_at" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('ends_at') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            @endif
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Reward (NaaraCredits)</label>
                <input wire:model="reward_credits" type="number" min="0.01" step="0.01"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('reward_credits') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> {{ $editingId ? 'Save changes' : 'Create goal' }}</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                </button>
                @if ($editingId)
                    <button type="button" wire:click="newGoal" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">Cancel</button>
                @endif
            </div>
        </form>
    </section>

    {{-- List --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-[#2D4060]">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Title</th>
                    <th class="px-4 py-2 font-medium">Metric</th>
                    <th class="px-4 py-2 font-medium">Target</th>
                    <th class="px-4 py-2 font-medium">Period</th>
                    <th class="px-4 py-2 font-medium">Reward</th>
                    <th class="px-4 py-2 font-medium">Claimed</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white dark:divide-[#243352] dark:bg-[#1A2840]">
                @forelse ($goals as $goal)
                    <tr wire:key="goal-{{ $goal->id }}" class="text-slate-700 dark:text-slate-200">
                        <td class="px-4 py-2.5 font-semibold">{{ $goal->title }}</td>
                        <td class="px-4 py-2.5">{{ $metrics[$goal->metric] ?? $goal->metric }}</td>
                        <td class="px-4 py-2.5">{{ rtrim(rtrim(number_format((float) $goal->target, 2), '0'), '.') }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ $goal->period_type }}</td>
                        <td class="px-4 py-2.5">{{ rtrim(rtrim(number_format((float) $goal->reward_credits, 2), '0'), '.') }} credits</td>
                        <td class="px-4 py-2.5">{{ $goal->claims_count }}</td>
                        <td class="px-4 py-2.5">
                            <x-ui.tag :variant="$goal->is_active ? 'live' : 'soon'">{{ $goal->is_active ? 'Live' : 'Paused' }}</x-ui.tag>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="edit({{ $goal->id }})" class="text-xs font-medium text-slate-500 underline hover:text-primary dark:text-slate-400">Edit</button>
                            <button type="button" wire:click="toggle({{ $goal->id }})" wire:loading.attr="disabled"
                                    class="ml-2 text-xs font-medium text-slate-500 underline hover:text-primary dark:text-slate-400">
                                {{ $goal->is_active ? 'Pause' : 'Resume' }}
                            </button>
                            <button type="button" wire:click="delete({{ $goal->id }})" wire:loading.attr="disabled"
                                    wire:confirm="Delete goal {{ $goal->title }}? A goal with claims is paused instead, to keep the audit trail."
                                    class="ml-2 text-xs font-medium text-red-500 underline hover:text-red-700 dark:text-red-400">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400 dark:text-slate-500">No goals yet — create one above to give users something to work toward.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
