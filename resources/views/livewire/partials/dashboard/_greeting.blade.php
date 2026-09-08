{{-- Greeting block (Theme Batch 2 §2, extracted verbatim). --}}
@php($gAvatar = \App\Support\SupportSettings::avatar())
@php($gName = \App\Support\SupportSettings::name())
@php($greetingOn = \App\Support\SupportSettings::greetingEnabled())
@php($greetingMode = \App\Support\SupportSettings::greetingMode())

@if ($greetingOn)
    @php($greetKey = 'nx_greet_'.now()->format('Ymd'))
    @if ($greetingMode === 'popup')
        <div x-data="{ show: false }"
             x-init="$nextTick(() => { show = localStorage.getItem('{{ $greetKey }}') !== '1'; })"
             x-show="show" x-cloak x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             class="fixed bottom-24 left-4 right-4 z-40 mx-auto max-w-sm sm:left-6 sm:right-auto lg:bottom-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl shadow-primary/10 dark:border-white/10 dark:bg-[#101d33]">
                @include('partials.greeting-bubble', ['onDismiss' => "@click=\"show = false; localStorage.setItem('{$greetKey}', '1')\""])
            </div>
        </div>
    @else
        <div x-data="{ show: true }"
             x-init="$nextTick(() => { show = localStorage.getItem('{{ $greetKey }}') !== '1'; })"
             x-show="show" x-cloak
             class="relative mb-6 overflow-hidden rounded-2xl border border-primary/15 bg-gradient-to-br from-primary/[0.07] via-transparent to-accent/[0.06] p-5 dark:border-primary/25 dark:from-primary/15 dark:to-accent/10">
            @include('partials.greeting-bubble', ['onDismiss' => "@click=\"show = false; localStorage.setItem('{$greetKey}', '1')\""])
        </div>
    @endif
@endif
