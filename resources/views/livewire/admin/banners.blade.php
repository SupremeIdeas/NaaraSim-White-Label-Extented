<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Banners</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Promo artwork shown to users. Pick a zone, upload JPG or WebP at the recommended size (plus an optional MP4/WebM video that loops over it), and optionally attach a link or a coupon. Dashboard-home banners rotate in a carousel; the other zones show the top banner.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    {{-- Create --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
            <x-icon name="image" class="h-4 w-4 text-primary" /> New banner
        </h2>

        <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Title <span class="font-normal text-slate-400">(also the image alt text)</span></label>
                <input wire:model="title" type="text" placeholder="July data sale — 10% off"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('title') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Zone</label>
                <select wire:model.live="placement" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @foreach ($placements as $key => [$label, $desktop, $mobile])
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Recommended: {{ $placements[$placement][1] }} px (desktop), {{ $placements[$placement][2] }} px (mobile).
                </p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Artwork (JPG/WebP, ≤ 2 MB)</label>
                <input wire:model="image" type="file" accept="image/jpeg,image/webp"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300">
                <div wire:loading wire:target="image" class="mt-1 text-[11px] text-slate-400">Uploading…</div>
                @error('image') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Mobile artwork <span class="font-normal text-slate-400">(optional — tighter crop for phones)</span></label>
                <input wire:model="image_mobile" type="file" accept="image/jpeg,image/webp"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300">
                <div wire:loading wire:target="image_mobile" class="mt-1 text-[11px] text-slate-400">Uploading…</div>
                @error('image_mobile') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Motion video <span class="font-normal text-slate-400">(optional — MP4/WebM, ≤ 10 MB)</span></label>
                <input wire:model="video" type="file" accept="video/mp4,video/webm"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300">
                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Plays muted &amp; looping over the artwork above (great for the “More” menu). The artwork stays as the poster and the reduced-motion fallback.</p>
                <div wire:loading wire:target="video" class="mt-1 text-[11px] text-slate-400">Uploading video…</div>
                @error('video') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Link <span class="font-normal text-slate-400">(optional — /catalogue or https://…)</span></label>
                <input wire:model="link_url" type="text" placeholder="/catalogue"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('link_url') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Coupon <span class="font-normal text-slate-400">(optional — shows a copyable code chip)</span></label>
                <select wire:model="coupon_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="">No coupon</option>
                    @foreach ($couponOptions as $c)
                        <option value="{{ $c->id }}">{{ $c->code }} — {{ rtrim(rtrim(number_format((float) $c->percent_off, 2), '0'), '.') }}%</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Manage codes on the <a href="{{ route('admin.coupons') }}" class="text-primary hover:underline" wire:navigate>Coupons page</a>.</p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Order</label>
                    <input wire:model="sort_order" type="number" min="0" max="999"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Starts <span class="font-normal text-slate-400">(opt.)</span></label>
                    <input wire:model="starts_at" type="datetime-local"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Ends <span class="font-normal text-slate-400">(opt.)</span></label>
                    <input wire:model="ends_at" type="datetime-local"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('ends_at') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="flex items-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="save,image,image_mobile,video"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Publish banner</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Publishing…</span>
                </button>
            </div>
        </form>
    </section>

    {{-- List --}}
    <div class="space-y-3">
        @forelse ($banners as $banner)
            <div wire:key="banner-{{ $banner->id }}" class="flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="h-16 w-40 shrink-0 rounded-lg border border-slate-200 object-cover dark:border-[#2D4060]">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ $banner->title }}</div>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 dark:bg-[#243352]">{{ \App\Models\Banner::PLACEMENTS[$banner->placement][0] ?? $banner->placement }}</span>
                        @if ($banner->coupon)
                            <span class="rounded-full bg-accent/15 px-2 py-0.5 font-mono font-semibold text-accent">{{ $banner->coupon->code }}</span>
                        @endif
                        @if ($banner->link_url)
                            <span class="inline-flex items-center gap-1 truncate"><x-icon name="globe" class="h-3 w-3" /> {{ $banner->link_url }}</span>
                        @endif
                        @if ($banner->starts_at || $banner->ends_at)
                            <span>{{ $banner->starts_at?->format('M j') ?? '…' }} → {{ $banner->ends_at?->format('M j') ?? '…' }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <x-ui.tag :variant="$banner->is_active ? 'live' : 'soon'">{{ $banner->is_active ? 'Live' : 'Paused' }}</x-ui.tag>
                    <button type="button" wire:click="toggle({{ $banner->id }})" wire:loading.attr="disabled"
                            class="text-xs font-medium text-slate-500 underline hover:text-primary dark:text-slate-400">
                        {{ $banner->is_active ? 'Pause' : 'Resume' }}
                    </button>
                    <button type="button" wire:click="delete({{ $banner->id }})" wire:loading.attr="disabled"
                            wire:confirm="Delete this banner? Users stop seeing it immediately."
                            class="text-xs font-medium text-red-500 underline hover:text-red-700 dark:text-red-400">
                        Delete
                    </button>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-400 dark:border-[#2D4060] dark:text-slate-500">
                No banners yet. Publish one above — it appears to users instantly.
            </div>
        @endforelse
    </div>
</div>
