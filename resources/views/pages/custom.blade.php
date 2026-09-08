<x-layouts.marketing :title="$page->title . ' — ' . \App\Support\BrandSettings::name()" :description="$page->meta_description">
    {{-- Admin-authored custom HTML (trusted operator content; inline scripts are
         still blocked by the site CSP). Rendered inside the branded shell so the
         header + footer wrap it; full-width pages skip the container. --}}
    @if ($page->full_width)
        {!! $page->html !!}
    @else
        <div class="custom-page mx-auto max-w-5xl px-4 py-12 sm:py-16">
            {!! $page->html !!}
        </div>
    @endif
</x-layouts.marketing>
