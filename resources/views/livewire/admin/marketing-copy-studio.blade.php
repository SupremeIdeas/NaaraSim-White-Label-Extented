<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Marketing Copy Studio</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Fill the brand brief once, then generate on-brand copy variations for any marketing section — powered by Claude.
        </p>
    </div>

    @unless ($aiEnabled)
        <div class="mb-5 flex items-start gap-2 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
            <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>Add an <strong>Anthropic API key</strong> under <a href="{{ route('admin.integrations') }}" wire:navigate class="underline">Integrations</a> to enable copy generation. You can still edit the brand brief now.</span>
        </div>
    @endunless

    {{-- Brand brief — the AI "trains" from this for every generation. --}}
    <form wire:submit="saveBrief" class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Brand brief</h2>
        <p class="mb-4 mt-0.5 text-xs text-slate-500 dark:text-slate-400">Every generation writes in this voice. The brand name defaults to your white-label word.</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Brand name</label>
                <input type="text" wire:model="brand" placeholder="Naara"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('brand') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">One-liner — what it does</label>
                <input type="text" wire:model="one_liner" placeholder="eSIM data + real phone numbers for travellers across Africa"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Audience</label>
                <input type="text" wire:model="audience" placeholder="Frequent travellers, remote workers, diaspora"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Voice / tone</label>
                <input type="text" wire:model="tone" placeholder="Confident, warm, plain-spoken — no jargon"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Themes / keywords to lean on</label>
                <input type="text" wire:model="keywords" placeholder="No borders, no SIM swaps, instant, one app for data + numbers"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark">Save brief</button>
            <span wire:loading wire:target="saveBrief" class="text-xs text-slate-400"><x-ui.spinner class="mr-1 inline h-3 w-3" /> Saving…</span>
        </div>
    </form>

    {{-- Page picker --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Page</span>
        @foreach ($pages as $key => $label)
            <button type="button" wire:click="selectPage('{{ $key }}')"
                    class="rounded-full px-3 py-1.5 text-sm font-medium transition {{ $page === $key ? 'bg-primary text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($error)
        <div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $error }}</div>
    @endif

    {{-- Sections on this page --}}
    <div class="space-y-4">
        @forelse ($sections as $s)
            <div wire:key="sec-{{ $s['id'] }}" class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary dark:bg-primary/20 dark:text-teal-300">{{ $s['label'] }}</span>
                        <span class="text-xs text-slate-400">{{ count($s['copy']) }} copy field{{ count($s['copy']) === 1 ? '' : 's' }}</span>
                    </div>
                    @if (count($s['copy']))
                        <button type="button" wire:click="generate({{ $s['id'] }})" @disabled(! $aiEnabled)
                                wire:loading.attr="disabled" wire:target="generate({{ $s['id'] }})"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-50">
                            <x-icon name="zap" class="h-4 w-4" wire:loading.remove wire:target="generate({{ $s['id'] }})" />
                            <x-ui.spinner class="h-4 w-4" wire:loading wire:target="generate({{ $s['id'] }})" />
                            <span wire:loading.remove wire:target="generate({{ $s['id'] }})">Generate</span>
                            <span wire:loading wire:target="generate({{ $s['id'] }})">Writing…</span>
                        </button>
                    @endif
                </div>

                {{-- Current copy --}}
                @if (count($s['copy']))
                    <dl class="mt-3 space-y-1.5">
                        @foreach ($s['copy'] as $path => $text)
                            <div class="flex gap-2 text-xs">
                                <dt class="w-32 shrink-0 truncate font-mono text-slate-400" title="{{ $path }}">{{ $path }}</dt>
                                <dd class="min-w-0 flex-1 text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($text, 160) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-2 text-xs text-slate-400">No editable copy in this section.</p>
                @endif

                {{-- Generated variations --}}
                @if (! empty($variations[$s['id']]))
                    <div class="mt-4 space-y-3 border-t border-slate-100 pt-4 dark:border-[#243352]">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Variations</p>
                        @foreach ($variations[$s['id']] as $i => $variation)
                            <div wire:key="var-{{ $s['id'] }}-{{ $i }}" class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-[#2D4060] dark:bg-[#243352]/50">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Option {{ $i + 1 }}</span>
                                    <button type="button" wire:click="apply({{ $s['id'] }}, {{ $i }})"
                                            class="inline-flex items-center gap-1 rounded-lg bg-primary px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-primary-dark">
                                        <x-icon name="check" class="h-3.5 w-3.5" /> Apply
                                    </button>
                                </div>
                                <dl class="space-y-1">
                                    @foreach ($variation['preview'] as $path => $text)
                                        <div class="flex gap-2 text-xs">
                                            <dt class="w-32 shrink-0 truncate font-mono text-slate-400">{{ $path }}</dt>
                                            <dd class="min-w-0 flex-1 text-slate-700 dark:text-slate-200">{{ $text }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endforeach
                        <button type="button" wire:click="discard({{ $s['id'] }})" class="text-xs font-medium text-slate-400 underline hover:text-slate-600 dark:hover:text-slate-200">Discard these</button>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400 dark:border-[#2D4060]">
                This page has no builder sections yet. Add some in <a href="{{ route('admin.builder') }}" wire:navigate class="text-primary hover:underline">Page Builder</a>, then come back to write their copy.
            </div>
        @endforelse
    </div>

    @if ($sections->isNotEmpty())
        <p class="mt-5 text-center text-xs text-slate-400">
            Applied copy lands in the page’s <strong>draft</strong>. Publish it in <a href="{{ route('admin.builder') }}" wire:navigate class="text-primary hover:underline">Page Builder</a> to go live.
        </p>
    @endif
</div>
