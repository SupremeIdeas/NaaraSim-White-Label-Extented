@php
    // Floating navigation pill (Homepage floating-nav prompt). Admin-assignable
    // NavSlots, a glowing Wizard centerpiece, real desktop treatment (a centered
    // floating pill — not a stretched mobile bar), and a spring "swell" on tap.
    $authed = auth()->check();
    $bar = \App\Support\NavSlots::bar($authed);
    $center = $bar['center'];

    $href = function ($slot) {
        // A guest can't open the in-page wizard — send them to sign up instead.
        if ($slot->opensWizard()) {
            return auth()->check() ? null : \App\Support\PageSections::target('register');
        }
        return \App\Support\PageSections::target($slot->target) ?: '#';
    };
    $active = function ($slot) {
        if ($slot->opensWizard()) {
            return false;
        }
        return \Illuminate\Support\Facades\Route::has($slot->target) && request()->routeIs($slot->target);
    };
@endphp

@if ($center || ! empty($bar['left']) || ! empty($bar['right']))
    <div class="nx-floatnav fixed inset-x-0 bottom-4 z-40 flex justify-center px-3 print:hidden">
        <nav class="pointer-events-auto flex items-center gap-0.5 rounded-full border border-slate-200/70 bg-white px-1.5 py-1.5 shadow-[0_12px_40px_rgba(13,27,42,0.18)] dark:border-white/10 dark:bg-[#0D1B2A] sm:gap-1"
             aria-label="Primary">
            @foreach ($bar['left'] as $slot)
                @include('partials.floatnav-item', ['slot' => $slot, 'href' => $href($slot), 'isActive' => $active($slot)])
            @endforeach

            {{-- Glowing centerpiece --}}
            @if ($center)
                @php $centerHref = $href($center); @endphp
                <div class="relative mx-0.5">
                    <span class="nx-wiz-glow pointer-events-none absolute -inset-1 -z-10 rounded-full bg-gradient-to-r from-primary to-primary-dark blur-[6px]" aria-hidden="true"></span>
                    @if ($center->opensWizard() && $authed)
                        <button type="button" onclick="window.Livewire && Livewire.dispatch('open-wizard')"
                                class="nx-floatnav__center flex h-14 items-center gap-2 rounded-full bg-gradient-to-br from-primary to-primary-dark px-5 text-white shadow-lg shadow-primary/30 transition active:scale-95">
                            <x-icon name="{{ $center->icon }}" class="h-5 w-5" />
                            <span class="text-sm font-semibold">{{ $center->label }}</span>
                        </button>
                    @else
                        <a href="{{ $centerHref ?: '#' }}"
                           class="nx-floatnav__center flex h-14 items-center gap-2 rounded-full bg-gradient-to-br from-primary to-primary-dark px-5 text-white shadow-lg shadow-primary/30 transition active:scale-95">
                            <x-icon name="{{ $center->icon }}" class="h-5 w-5" />
                            <span class="text-sm font-semibold">{{ $center->label }}</span>
                        </a>
                    @endif
                </div>
            @endif

            @foreach ($bar['right'] as $slot)
                @include('partials.floatnav-item', ['slot' => $slot, 'href' => $href($slot), 'isActive' => $active($slot)])
            @endforeach
        </nav>
    </div>
@endif
