{{-- Social sign-in buttons (owner request). Renders a button for every provider
     whose keys are configured (SocialAuth::enabled) — so a button never dangles.
     Real brand glyphs come from the bundled service-icon sprite. --}}
@php($providers = \App\Support\SocialAuth::enabledProviders())
@if (! empty($providers))
    <div class="space-y-2">
        @foreach ($providers as $key => $meta)
            <a href="{{ route('social.redirect', $key) }}" wire:navigate.ignore
               class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:bg-[#243352] dark:text-slate-100 dark:hover:bg-[#2A3A56]">
                <x-service-icon :slug="$meta['icon']" class="h-5 w-5" />
                Continue with {{ $meta['label'] }}
            </a>
        @endforeach
    </div>
    <div class="my-4 flex items-center gap-3 text-xs text-slate-400">
        <span class="h-px flex-1 bg-slate-200 dark:bg-[#2D4060]"></span> or <span class="h-px flex-1 bg-slate-200 dark:bg-[#2D4060]"></span>
    </div>
@endif
