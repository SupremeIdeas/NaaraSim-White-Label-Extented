<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Link previews</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">The image shown when someone pastes a Naara link into WhatsApp, Slack, iMessage, or any other chat app. Each context below is independent — change one without affecting the others.</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        @foreach ([
            ['field' => 'default_image', 'context' => 'default', 'label' => 'Site-wide default', 'help' => 'Used for any page that doesn\'t set its own preview image.'],
            ['field' => 'invoice_image', 'context' => 'invoice', 'label' => 'Invoice links', 'help' => 'Shown when a merchant\'s invoice link (/i/...) is shared with a client.'],
            ['field' => 'referral_image', 'context' => 'referral', 'label' => 'Referral links', 'help' => 'Shown when the homepage is opened via a referral link.'],
        ] as $slot)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A45]">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $slot['label'] }}</h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $slot['help'] }}</p>

                <div class="mt-3 flex items-center gap-4">
                    <img src="{{ $this->current[$slot['context']] }}" alt="" class="h-20 w-36 shrink-0 rounded-lg border border-slate-200 object-cover dark:border-[#2D4060]">
                    <div class="min-w-0 flex-1">
                        <input type="file" wire:model="{{ $slot['field'] }}" accept="image/webp,image/jpeg,image/png"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary dark:text-slate-300 dark:file:bg-teal-500/15 dark:file:text-teal-300">
                        @error($slot['field']) <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
                        @if ($this->{$slot['field']})
                            <img src="{{ $this->{$slot['field']}->temporaryUrl() }}" alt="" class="mt-2 h-16 w-28 rounded-lg border border-slate-200 object-cover dark:border-[#2D4060]">
                        @endif
                    </div>
                    <button type="button" wire:click="resetContext('{{ $slot['context'] }}')" wire:confirm="Revert to the default banner for this context?"
                        class="shrink-0 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                        Reset
                    </button>
                </div>
            </div>
        @endforeach

        <button type="submit" wire:loading.attr="disabled" wire:target="save"
            class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-60 dark:bg-teal-700 dark:hover:bg-teal-600">
            <span wire:loading.remove wire:target="save">Save changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </form>
</div>
