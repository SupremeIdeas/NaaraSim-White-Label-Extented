<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Legal pages</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Edit the public legal documents. These live at stable URLs that Google/Facebook login reviews and app stores expect. Ships with best-practice defaults — edit only what you need, or reset to the original.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ($docs as $doc)
            <button type="button" wire:click="$set('slug', '{{ $doc['slug'] }}')"
                    @class([
                        'rounded-full px-4 py-1.5 text-sm font-medium transition',
                        'bg-primary text-white' => $slug === $doc['slug'],
                        'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#243352] dark:text-slate-300' => $slug !== $doc['slug'],
                    ])>{{ $doc['title'] }}</button>
        @endforeach
    </div>

    <form wire:submit="save" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="flex items-center justify-between">
            <a href="{{ route('legal.show', $slug) }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                <x-icon name="globe" class="h-3.5 w-3.5" /> View public page: /legal/{{ $slug }}
            </a>
            <button type="button" wire:click="resetToDefault" wire:confirm="Reset this document to the shipped best-practice copy? Your edits will be removed."
                    class="text-xs font-medium text-slate-400 underline hover:text-red-500">Reset to default</button>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Title</label>
            <input wire:model="title" type="text"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('title') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Body</label>
            <p class="mb-1 text-[11px] text-slate-400 dark:text-slate-500">Use <code class="font-mono">## Heading</code> for section headings, <code class="font-mono">- item</code> for bullets, and a blank line between paragraphs. <code class="font-mono">{brand}</code> is replaced with your brand name. HTML is not allowed (rendered as plain text for safety).</p>
            <textarea wire:model="body" rows="18"
                      class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-xs leading-relaxed text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
            @error('body') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Save page</span>
            <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
        </button>
    </form>
</div>
