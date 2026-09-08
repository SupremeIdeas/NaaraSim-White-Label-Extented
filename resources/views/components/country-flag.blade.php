{{-- Country flag (Module 27.5): self-hosted flag-icons SVG by ISO2 / provider
     slug; globe icon fallback so an unknown country never shows broken. --}}
@props(['country', 'class' => 'h-4 w-6'])
@php($flag = \App\Support\CountryFlags::flagClass($country))
@if ($flag)
    <span {{ $attributes->merge(['class' => $flag.' '.$class.' inline-block rounded-[3px] bg-cover shadow-sm ring-1 ring-black/10']) }}
          role="img" aria-label="{{ \App\Support\CountryFlags::label($country) }}"></span>
@else
    <x-icon name="globe" class="{{ $class }} text-slate-400" />
@endif
