{{-- Service logo (Module 27.5): admin override → API icon → bundled brand
     sprite → letter avatar. Always renders SOMETHING meaningful. --}}
@props(['slug', 'apiUrl' => null, 'class' => 'h-6 w-6'])
@php($icon = \App\Support\ServiceIcons::resolve($slug, $apiUrl))
@if ($icon['type'] === 'img')
    <img src="{{ $icon['url'] }}" alt="{{ ucfirst($slug) }}" loading="lazy"
         {{ $attributes->merge(['class' => $class.' rounded-md object-contain']) }}>
@elseif ($icon['type'] === 'sprite')
    <svg {{ $attributes->merge(['class' => $class]) }} aria-label="{{ ucfirst($slug) }}" role="img">
        <use href="#{{ $icon['id'] }}"></use>
    </svg>
@else
    <span {{ $attributes->merge(['class' => $class.' inline-flex items-center justify-center rounded-md bg-primary/10 text-[0.6em] font-bold text-primary dark:bg-primary/25 dark:text-teal-300']) }}
          aria-label="{{ ucfirst($slug) }}" role="img">{{ $icon['letter'] }}</span>
@endif
