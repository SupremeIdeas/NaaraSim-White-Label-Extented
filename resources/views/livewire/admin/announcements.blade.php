<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Announcements &amp; offers</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Push an update or offer to every active user’s notification bell. Attach a coupon and they can claim it in one tap.</p>
    </div>

    @if ($sent)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $sent }}
        </div>
    @endif

    {{-- Composer --}}
    <form wire:submit="send" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Title</label>
            <input type="text" wire:model="title" maxlength="120" placeholder="Weekend data sale — 20% off"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Message</label>
            <textarea wire:model="body" rows="3" maxlength="500" placeholder="For this weekend only, get 20% off any eSIM plan. Tap to claim."
                      class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
            @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Icon</label>
            <div class="flex flex-wrap gap-2">
                @foreach ($icons as $ic)
                    <button type="button" wire:click="$set('icon', '{{ $ic }}')"
                            class="flex h-10 w-10 items-center justify-center rounded-lg border transition {{ $icon === $ic ? 'border-primary bg-primary/10 text-primary' : 'border-slate-300 text-slate-400 hover:bg-slate-50 dark:border-[#2D4060] dark:hover:bg-[#243352]' }}">
                        <x-icon :name="$ic" class="h-5 w-5" />
                    </button>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Button label (optional)</label>
                <input type="text" wire:model="cta_label" maxlength="40" placeholder="Claim offer"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('cta_label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Coupon code (optional)</label>
                <input type="text" wire:model="coupon_code" maxlength="40" placeholder="WEEKEND20"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm uppercase dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('coupon_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Custom link (optional — ignored if a coupon is set)</label>
            <input type="url" wire:model="cta_url" maxlength="300" placeholder="https://…"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            <p class="mt-1 text-[11px] text-slate-400">With a coupon set, the button sends users to the catalogue with the code ready to claim.</p>
            @error('cta_url') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="send"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="bell" class="h-4 w-4" />
                <span wire:loading.remove wire:target="send">Send to everyone</span>
                <span wire:loading wire:target="send">Sending…</span>
            </button>
        </div>
    </form>

    {{-- History --}}
    <h2 class="mb-3 mt-8 text-sm font-semibold text-slate-700 dark:text-slate-200">Sent announcements</h2>
    <div class="space-y-2">
        @forelse ($announcements as $a)
            <div wire:key="a-{{ $a->id }}" class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1B2A44]">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                    <x-icon :name="$a->icon ?: 'gift'" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $a->title }}</p>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $a->body }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400">
                        <span>{{ $a->created_at->diffForHumans() }}</span>
                        @if ($a->coupon_code)<span class="rounded bg-accent/10 px-1.5 py-0.5 font-semibold text-accent">{{ $a->coupon_code }}</span>@endif
                        <span class="inline-flex items-center gap-1">
                            @if ($a->status === 'sent')
                                <x-icon name="badge-check" class="h-3 w-3 text-green-500" /> {{ number_format($a->recipients) }} delivered
                            @else
                                Sending…
                            @endif
                        </span>
                        @if ($a->author)<span>by {{ $a->author->name }}</span>@endif
                    </div>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400 dark:border-[#2D4060]">No announcements yet.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $announcements->links() }}</div>
</div>
