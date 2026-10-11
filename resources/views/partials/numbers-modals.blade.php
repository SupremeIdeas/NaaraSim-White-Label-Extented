{{--
    Product flows (Numbers V6 §0): ONE sheet chrome (<x-nx.sheet>) for Verify / Rent / Line, in two modes. A bottom sheet on phones and a
    centred dialog on desktop, or, when the admin set the card to "dedicated page" ($pageMode, NumbersBento::isPageMode()), the same header
    and body laid out in the page. The body partial and its footer partial never fork between the two modes. Livewire-driven ($modal),
    URL-reflected (?modal=), wired to the shared Country/Service pickers.
--}}
@if ($modal !== '')
    @php
        $__icons = ['verify' => 'shield', 'rent' => 'hash', 'line' => 'phone'];
        $__title = __('numbers.modal_title')[$modal] ?? __('numbers.modal_title.default');
        // One header, never two: the tagline lives under the title here instead of in a second card that repeated the name and icon.
        $__subtitle = $modal === 'verify' ? __('numbers.verify.banner_text') : null;
    @endphp
    <x-nx.sheet :mode="($pageMode ?? false) ? 'page' : 'modal'" :title="$__title" :subtitle="$__subtitle" :icon="$__icons[$modal] ?? 'phone'" :label="ucfirst($modal)" close-action="closeModal">
        @include('partials.numbers-modal.'.$modal)
        <x-slot:foot>@includeIf('partials.numbers-modal.'.$modal.'-foot')</x-slot:foot>
    </x-nx.sheet>
@endif
