<div class="mx-auto max-w-2xl px-4 py-6">
    @php($input = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100')
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">My brand listing</h1>
        <p class="text-sm text-slate-500 dark:text-slate-300">{{ $brand->plan?->name ?? 'No plan' }} ·
            @if ($subscription)
                Next billing {{ $subscription->next_billing_at?->format('d M Y') }} · Status:
                <span class="font-semibold {{ $brand->listing_status === 'active' ? 'text-green-600 dark:text-green-400' : ($brand->listing_status === 'paused_billing' ? 'text-amber-600' : 'text-slate-500') }}">{{ ucwords(str_replace('_',' ',$brand->listing_status)) }}</span>
            @endif
        </p>
    </div>

    @if ($flash)<div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-300">{{ $flash }}</div>@endif
    @if ($error)<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">{{ $error }}</div>@endif

    @unless ($setupComplete)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200">
            <strong>Finish your setup</strong> to go live: add a brand name, category, a hero image and at least one handle. Your listing appears in the directory once these are complete.
        </div>
    @endunless

    {{-- Delivery vs guarantee (transparency) --}}
    @if ($delivery->isNotEmpty())
        <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
            <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Follows delivered this cycle</h2>
            @foreach ($delivery as $row)
                <div class="mb-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-600 dark:text-slate-300">{{ $row['handle']->handle_label }}</span>
                        <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $row['actual'] }} / {{ $row['guaranteed'] }} guaranteed</span>
                    </div>
                    @php($pct = $row['guaranteed'] > 0 ? min(100, (int) round($row['actual'] / $row['guaranteed'] * 100)) : 0)
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"><div class="h-full rounded-full {{ $pct >= 100 ? 'bg-green-500' : 'bg-primary' }}" style="width: {{ $pct }}%"></div></div>
                </div>
            @endforeach
            <p class="mt-1 text-[11px] text-slate-400">Under-delivered handles boost your placement next cycle automatically.</p>
        </div>
    @endif

    {{-- Profile --}}
    <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
        <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Profile</h2>
        <div class="space-y-3">
            <input wire:model="profile.brand_name" placeholder="Brand name" class="{{ $input }}">
            @error('profile.brand_name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <select wire:model="profile.category" class="{{ $input }}">
                <option value="">Choose a category…</option>
                @foreach ($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
            @error('profile.category')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <textarea wire:model="profile.short_description" rows="2" placeholder="Short description" class="{{ $input }}"></textarea>
            <div class="flex items-center gap-3">
                <label class="text-xs text-slate-500 dark:text-slate-400">Card colour</label>
                <input type="color" wire:model="profile.background_color" class="h-9 w-14 rounded border border-slate-300 dark:border-[var(--brand-card-border-dark)]">
                <label class="text-xs text-slate-500 dark:text-slate-400">Hero image (1280×720)</label>
                <input type="file" wire:model="heroImage" accept="image/*" class="text-xs">
            </div>
            @error('heroImage')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @if ($brand->hero_image_path)<img src="{{ $brand->hero_image_path }}" class="mt-1 h-24 w-full rounded-lg object-cover">@endif
            <button wire:click="saveProfile" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">Save profile</button>
        </div>
    </div>

    {{-- Handles --}}
    <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
        <h2 class="mb-1 text-sm font-bold text-slate-900 dark:text-white">Social handles</h2>
        <p class="mb-3 text-xs text-slate-400">{{ $brand->handles->count() }} / {{ $brand->plan?->handles_included ?? 1 }} used</p>
        @foreach ($brand->handles as $h)
            <div class="mb-1.5 flex items-center gap-2 text-sm">
                <x-service-icon :slug="$h->platform" class="h-4 w-4" />
                <span class="flex-1 truncate text-slate-700 dark:text-slate-200">{{ $h->handle_label }} <span class="text-xs text-slate-400">· {{ $h->handle_url }}</span></span>
                <button wire:click="removeHandle({{ $h->id }})" class="text-slate-400 hover:text-red-500"><x-icon name="x" class="h-4 w-4" /></button>
            </div>
        @endforeach
        <div x-data="{ p: @entangle('newHandle.platform') }" class="mt-3 grid gap-2 sm:grid-cols-4">
            <select wire:model="newHandle.platform" x-model="p" class="{{ $input }}">@foreach ($platforms as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select>
            <input wire:model="newHandle.handle_label" placeholder="Label" class="{{ $input }}">
            <input wire:model="newHandle.handle_url" placeholder="Profile URL" class="{{ $input }} sm:col-span-2">
            <div class="sm:col-span-4">
                @error('newHandle.handle_url')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button wire:click="addHandle" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200">Add handle</button>
            </div>
        </div>
    </div>

    {{-- Videos (only if the plan allows) --}}
    @if (($brand->plan?->video_previews_allowed ?? 0) > 0)
        <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
            <h2 class="mb-1 text-sm font-bold text-slate-900 dark:text-white">Video previews</h2>
            <p class="mb-3 text-xs text-slate-400">{{ $brand->videos->count() }} / {{ $brand->plan->video_previews_allowed }} used</p>
            @foreach ($brand->videos as $v)
                <div class="mb-1.5 flex items-center gap-2 text-sm">
                    <x-icon name="play" class="h-4 w-4 text-slate-400" />
                    <span class="flex-1 truncate text-slate-700 dark:text-slate-200">{{ $v->platform }} · {{ $v->video_url }}</span>
                    <button wire:click="removeVideo({{ $v->id }})" class="text-slate-400 hover:text-red-500"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
            @endforeach
            <div class="mt-3 grid gap-2 sm:grid-cols-4">
                <select wire:model="newVideo.platform" class="{{ $input }}"><option value="youtube">YouTube</option><option value="vimeo">Vimeo</option></select>
                <input wire:model="newVideo.video_url" placeholder="Video URL" class="{{ $input }} sm:col-span-2">
                <button wire:click="addVideo" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:text-slate-200">Add video</button>
            </div>
        </div>
    @endif

    {{-- Plan + cancel --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
        <h2 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Plan</h2>
        <div class="flex flex-wrap gap-2">
            @foreach ($plans as $plan)
                <button wire:click="changePlan({{ $plan->id }})"
                        class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition {{ $brand->current_plan_id === $plan->id ? 'border-primary bg-primary/10 text-primary dark:text-teal-300' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:text-slate-300' }}">
                    {{ $plan->name }} · ${{ number_format($plan->price_usd_per_month, 0) }}/mo
                </button>
            @endforeach
        </div>
        <button wire:click="cancel" wire:confirm="Cancel your listing? It will be removed from the directory. You can resubscribe later."
                class="mt-4 text-xs font-semibold text-red-500 hover:underline">Cancel listing</button>
    </div>
</div>
