<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Integrations</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Social links, tracking pixels, and sign-in providers — each lights up only once you've configured it.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    {{-- Social footer links --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Social media links</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">These appear as icons in the site footer. Leave one blank to hide it — the whole row hides until you add at least one.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($platforms as $key => [$label, $icon])
                <div>
                    <label class="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                        <x-service-icon :slug="$icon" class="h-3.5 w-3.5" /> {{ $label }}
                    </label>
                    <input type="url" wire:model="social.{{ $key }}" placeholder="https://…"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
            @endforeach
        </div>
        <button type="button" wire:click="saveSocial" class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
            <x-icon name="check" class="h-4 w-4" /> Save social links
        </button>
    </section>

    {{-- Tracking --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Tracking &amp; analytics</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Snippets load only when an ID is set. Google Analytics 4 and Meta (Facebook) Pixel.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Google Analytics (GA4) Measurement ID</label>
                <input type="text" wire:model="gaId" placeholder="G-XXXXXXXXXX"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('gaId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                <p class="mt-1 text-[11px] text-slate-400">analytics.google.com → Admin → Data streams → your stream.</p>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Facebook (Meta) Pixel ID</label>
                <input type="text" wire:model="pixelId" placeholder="123456789012345"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('pixelId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                <p class="mt-1 text-[11px] text-slate-400">business.facebook.com → Events Manager → your pixel.</p>
            </div>
        </div>
        <button type="button" wire:click="saveTracking" class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
            <x-icon name="check" class="h-4 w-4" /> Save tracking
        </button>
    </section>

    {{-- ElevenLabs Convai voice assistant (task #17) --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Voice assistant (ElevenLabs Convai)</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            A floating voice agent for visitors. Nothing external loads until you enable it — the strict content-security policy widens for ElevenLabs only while it's on. When off, the WhatsApp / live-chat launcher is the fallback.
        </p>

        <label class="flex items-center gap-3">
            <input type="checkbox" wire:model="convaiEnabled" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Enable the voice assistant</span>
        </label>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Convai agent id</label>
                <input type="text" wire:model="convaiAgentId" placeholder="e.g. agent_xxx or a public id"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                <p class="mt-1 text-[11px] text-slate-400">elevenlabs.io → your Convai agent → Widget → copy the agent id (public, not your API key).</p>
                @error('convaiAgentId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Show it</label>
                <select wire:model="convaiPlacement" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @foreach ($convaiPlacements as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="button" wire:click="saveConvai" class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
            <x-icon name="check" class="h-4 w-4" /> Save voice widget
        </button>
    </section>

    {{-- Social sign-in providers (status + baked setup guides) --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Social sign-in</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Add each provider's credentials on the <a href="{{ route('admin.api-keys') }}" wire:navigate class="font-medium text-primary hover:underline">API keys</a> page. Use the exact callback URL below in the provider's console.</p>
        <div class="space-y-3">
            @foreach ($providers as $p)
                <details class="rounded-xl border border-slate-200 p-4 dark:border-[#2D4060]">
                    <summary class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-100">
                        <x-service-icon :slug="$p['icon']" class="h-4 w-4" /> {{ $p['label'] }}
                        <span @class([
                            'ml-auto rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' => $p['enabled'],
                            'bg-slate-100 text-slate-500 dark:bg-[#243352] dark:text-slate-400' => ! $p['enabled'],
                        ])>{{ $p['enabled'] ? 'Active' : 'Not configured' }}</span>
                    </summary>
                    <div class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-300">
                        <p>{{ $p['steps'] }}</p>
                        <p><span class="font-semibold">Console:</span> <a href="{{ $p['console'] }}" target="_blank" rel="noopener" class="text-primary hover:underline">{{ $p['console'] }}</a></p>
                        <p class="flex flex-wrap items-center gap-1"><span class="font-semibold">Callback URL:</span> <code class="rounded bg-slate-100 px-1.5 py-0.5 dark:bg-[#243352]">{{ $p['callback'] }}</code></p>
                    </div>
                </details>
            @endforeach
        </div>
    </section>
</div>
