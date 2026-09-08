{{--
    Section Builder renderer. Given $sections (an ordered array of
    {type, config} from App\Support\PageSections), render each with its type's
    blade partial. Unknown types are skipped defensively — a page can never break
    because a type was removed. This is the single seam both the public site and
    the admin preview render through, so they always look identical.
--}}
@php $sections = $sections ?? []; @endphp
@foreach ($sections as $__section)
    @if (is_array($__section) && \App\Support\SectionLibrary::has($__section['type'] ?? ''))
        @include(\App\Support\SectionLibrary::bladeFor($__section['type']), ['config' => (array) ($__section['config'] ?? [])])
    @endif
@endforeach
