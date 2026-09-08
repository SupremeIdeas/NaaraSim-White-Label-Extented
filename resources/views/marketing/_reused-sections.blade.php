{{-- Renders any PORTABLE section an admin has copied onto this page (reusable
     sections feature). These pages compose their own bespoke sections inline;
     portable sections are never among them, so there's no double-render. They
     append after the page's own content, in the order they were added. --}}
@foreach ($sections as $key => $s)
    @if (in_array($key, \App\Support\SiteContent::PORTABLE_SECTIONS, true))
        @includeIf('marketing.sections.'.$key, ['s' => $s])
    @endif
@endforeach
