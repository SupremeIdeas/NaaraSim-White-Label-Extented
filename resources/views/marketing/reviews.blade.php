{{-- Reviews page (Trustpilot display layer). Only the official Trustpilot list widget, or an honest link to the profile; nothing is curated or filtered here. --}}
@php use App\Support\Trustpilot\TrustpilotSettings as TP; @endphp
<x-layouts.marketing :title="TP::pageTitle().' — '.\App\Support\BrandSettings::name()">
    <section class="mx-auto max-w-3xl px-4 py-16 sm:py-24">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">{{ TP::pageTitle() }}</h1>
        @if (TP::pageIntro() !== '')
            <p class="mt-3 text-slate-600 dark:text-slate-300">{{ TP::pageIntro() }}</p>
        @endif
        <div class="mt-8">
            @if (TP::active() && TP::placementOn('page.list'))
                <x-trust.reviews placement="page.list" />
            @elseif (TP::profileUrl() !== '')
                <a href="{{ TP::profileUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
                    <x-icon name="star" class="h-5 w-5" /> Read our reviews on Trustpilot
                </a>
            @endif
        </div>
    </section>
</x-layouts.marketing>
