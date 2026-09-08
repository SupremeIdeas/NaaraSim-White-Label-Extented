<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Social Hunt</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Manage NaaraSim's own follow handles and featured brand partners. Each follow grants a one-time surprise NaaraCredit reward (never shown to users before they follow).</p>
    </div>

    @if ($saved)
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-300">{{ $saved }}</div>
    @endif

    @php
        $input = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100';
    @endphp

    {{-- Platform handles --}}
    <section class="mb-8 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">NaaraSim handles</h2>
        <div class="space-y-2">
            @forelse ($handles as $h)
                <div wire:key="h-{{ $h->id }}" class="flex items-center gap-3 rounded-lg border border-slate-100 px-3 py-2 dark:border-white/5">
                    <x-service-icon :slug="$h->platform" class="h-5 w-5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $h->handle_label }} <span class="text-xs text-slate-400">· {{ $h->credit_reward }} cr · {{ $h->verification }}</span></p>
                        <p class="truncate text-xs text-slate-400">{{ $h->handle_url }}</p>
                    </div>
                    <button wire:click="toggleHandle({{ $h->id }})" class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $h->is_active ? 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300' : 'bg-slate-100 text-slate-500 dark:bg-white/5' }}">{{ $h->is_active ? 'Active' : 'Off' }}</button>
                    <button wire:click="deleteHandle({{ $h->id }})" wire:confirm="Remove this handle?" class="text-slate-400 hover:text-red-500"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
            @empty
                <p class="text-sm text-slate-400">No handles yet.</p>
            @endforelse
        </div>
        <div class="mt-4 grid gap-2 border-t border-slate-100 pt-4 sm:grid-cols-6 dark:border-white/5">
            <select wire:model="handle.platform" class="{{ $input }} sm:col-span-1">
                @foreach ($platforms as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
            </select>
            <input wire:model="handle.handle_label" placeholder="Label" class="{{ $input }} sm:col-span-1">
            <input wire:model="handle.handle_url" placeholder="https://…" class="{{ $input }} sm:col-span-2">
            <input wire:model="handle.credit_reward" type="number" step="0.5" placeholder="Reward" class="{{ $input }} sm:col-span-1">
            <select wire:model="handle.verification" class="{{ $input }} sm:col-span-1">
                <option value="self">Self-confirmed</option>
                <option value="api">API-verified</option>
            </select>
            <button wire:click="addHandle" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark sm:col-span-6">Add handle</button>
        </div>
        @error('handle.handle_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('handle.handle_label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </section>

    {{-- Brand partners --}}
    <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Featured brand partners</h2>
        <div class="space-y-4">
            @forelse ($brands as $b)
                <div wire:key="b-{{ $b->id }}" class="rounded-xl border border-slate-200 p-4 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <span class="h-8 w-8 shrink-0 rounded-lg" style="background: {{ $b->background_color }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $b->brand_name }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $b->short_description }}</p>
                        </div>
                        <button wire:click="toggleBrand({{ $b->id }})" class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $b->is_active ? 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300' : 'bg-slate-100 text-slate-500 dark:bg-white/5' }}">{{ $b->is_active ? 'Active' : 'Off' }}</button>
                        <button wire:click="deleteBrand({{ $b->id }})" wire:confirm="Remove this brand and its handles?" class="text-slate-400 hover:text-red-500"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                    {{-- Brand handles --}}
                    <div class="mt-3 space-y-1.5 pl-11">
                        @foreach ($b->handles as $bhh)
                            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-300">
                                <x-service-icon :slug="$bhh->platform" class="h-4 w-4" />
                                <span class="flex-1 truncate">{{ $bhh->handle_label }} · {{ $bhh->credit_reward }} cr</span>
                                <button wire:click="deleteBrandHandle({{ $bhh->id }})" class="text-slate-400 hover:text-red-500"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                            </div>
                        @endforeach
                        <div class="grid gap-2 pt-1 sm:grid-cols-6">
                            <select wire:model="bh.{{ $b->id }}.platform" class="{{ $input }} sm:col-span-1">
                                @foreach ($platforms as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
                            </select>
                            <input wire:model="bh.{{ $b->id }}.handle_label" placeholder="Label" class="{{ $input }} sm:col-span-1">
                            <input wire:model="bh.{{ $b->id }}.handle_url" placeholder="https://…" class="{{ $input }} sm:col-span-2">
                            <input wire:model="bh.{{ $b->id }}.credit_reward" type="number" step="0.5" placeholder="Reward" class="{{ $input }} sm:col-span-1">
                            <button wire:click="addBrandHandle({{ $b->id }})" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 sm:col-span-1 dark:border-[#2D4060] dark:text-slate-200">Add</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">No brand partners yet.</p>
            @endforelse
        </div>
        {{-- New brand --}}
        <div class="mt-4 grid gap-2 border-t border-slate-100 pt-4 sm:grid-cols-6 dark:border-white/5">
            <input wire:model="brand.brand_name" placeholder="Brand name" class="{{ $input }} sm:col-span-2">
            <input wire:model="brand.short_description" placeholder="Short description" class="{{ $input }} sm:col-span-2">
            <input wire:model="brand.background_color" type="color" class="h-9 w-full rounded-lg border border-slate-300 sm:col-span-1 dark:border-[#2D4060]">
            <input type="file" wire:model="brandImage" accept="image/*" class="text-xs sm:col-span-1">
            <button wire:click="addBrand" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark sm:col-span-6">Add brand partner</button>
        </div>
        @error('brand.brand_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('brand.background_color') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </section>
</div>
