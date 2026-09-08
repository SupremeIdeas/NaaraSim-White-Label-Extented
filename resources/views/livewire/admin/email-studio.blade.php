<div class="mx-auto max-w-6xl px-4 py-6" wire:key="email-studio">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Email Studio</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Edit your transactional emails and see exactly what a recipient gets. Blank fields keep the built-in default.</p>
        </div>
        <a href="{{ route('admin.email-broadcast') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-white/5">
            <x-icon name="send" class="h-4 w-4" /> Send a broadcast
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        {{-- Template list --}}
        <div class="space-y-1">
            <button type="button" wire:click="loadTemplate('global')"
                    class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-medium {{ $key === 'global' ? 'bg-primary text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' }}">
                <x-icon name="image" class="h-4 w-4" /> Global styling
            </button>
            @foreach ($templates as $tk => $tpl)
                <button type="button" wire:click="loadTemplate('{{ $tk }}')"
                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-medium {{ $key === $tk ? 'bg-primary text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5' }}">
                    <x-icon name="mail" class="h-4 w-4" /> {{ $tpl['label'] }}
                </button>
            @endforeach
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Editor --}}
            <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#16233d]">
                @if ($key === 'global')
                    <p class="text-sm text-slate-500 dark:text-slate-400">Accent colour applies to every email's header and buttons, unless a specific template sets its own.</p>
                @else
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Subject line</span>
                        <input type="text" wire:model.live.debounce.400ms="form.subject" placeholder="{{ $templates[$key]['subject'] ?? '' }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                        @error('form.subject') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Intro line (added above the body)</span>
                        <textarea rows="3" wire:model.live.debounce.400ms="form.intro" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white"></textarea>
                        @error('form.intro') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Button text</span>
                        <input type="text" wire:model.live.debounce.400ms="form.button_text" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-[#0D1B2A] dark:text-white">
                    </label>
                @endif
                <label class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                    Accent colour
                    <input type="color" wire:model.live="form.accent_color" class="h-9 w-12 cursor-pointer rounded border border-slate-200 bg-transparent dark:border-white/10">
                    <button type="button" wire:click="$set('form.accent_color', '')" class="text-xs font-normal text-slate-400 underline">Reset</button>
                    @error('form.accent_color') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </label>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" wire:click="save" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving…</span>
                    </button>
                    @if ($saved === $key)<span class="text-sm font-medium text-green-600 dark:text-green-400">Saved.</span>@endif
                </div>
            </div>

            {{-- Live preview --}}
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Live preview (sample data)</p>
                <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10">
                    <iframe title="Email preview" class="h-[32rem] w-full bg-white" srcdoc="{{ $previewHtml }}"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
