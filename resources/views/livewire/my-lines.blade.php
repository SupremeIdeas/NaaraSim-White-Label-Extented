<div class="mx-auto max-w-5xl">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div>
            <h1 class="ns-h1">My Lines</h1>
            <p class="ns-sub">Everything you own — eSIMs and numbers, active and archived.</p>
        </div>
        <div class="hidden items-center gap-2 sm:flex">
            <a href="{{ route('catalogue') }}" wire:navigate
               class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">
                <x-nx.icon name="package" /> Buy eSIM
            </a>
            <a href="{{ route('numbers') }}" wire:navigate
               class="ns-cta ns-cta--pill ns-cta--sm">
                <x-nx.icon name="hash" /> Get number
            </a>
        </div>
    </div>

    {{-- Port-in entry (Prompt 11): bring an existing US/Canada number to Naara. Honest: a multi-day carrier process, surfaced where numbers are managed. --}}
    <x-nx.feature-link :href="route('numbers.port-in')" icon="phone-forwarded"
        :kicker="__('numbers.portin_card.kicker')" :title="\App\Support\BrandSettings::rebrand(__('numbers.portin_card.title'))"
        :text="__('numbers.portin_card.text')" :cta="__('numbers.portin_card.cta')"
        :chips="[__('numbers.portin_card.chip_keep'), __('numbers.portin_card.chip_days'), __('numbers.portin_card.chip_voice')]" />

    @if ($hasAny)
        @include('partials.my-connectivity')
        @include('partials.my-lines-analytics')
    @else
        <div class="ns-card" style="padding:40px 24px;text-align:center">
            <span class="ns-tile" style="width:56px;height:56px;font-size:28px;margin:0 auto"><x-nx.icon name="signal" /></span>
            <h2 class="ns-h1" style="margin-top:16px;font-size:20px">Nothing here yet</h2>
            <p class="ns-sub" style="margin:4px auto 0;max-width:360px">
                Buy an eSIM for data abroad or get a virtual number — they’ll all show up here to manage.
            </p>
            <div class="mt-5 flex items-center justify-center gap-2">
                <a href="{{ route('catalogue') }}" wire:navigate
                   class="ns-cta ns-cta--pill ns-cta--ghost">
                    <x-nx.icon name="package" /> Browse eSIM plans
                </a>
                <a href="{{ route('numbers') }}" wire:navigate
                   class="ns-cta ns-cta--pill">
                    <x-nx.icon name="hash" /> Get a number
                </a>
            </div>
        </div>
    @endif

    {{-- Modal host so the per-line "Message" action can open the composer. --}}
    @livewire('send-message')
</div>
