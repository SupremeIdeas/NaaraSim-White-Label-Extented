{{-- Branded checkbox with a drawn check (Module 32). Real input inside. --}}
@props(['id' => null, 'label' => null])
@php($id = $id ?? 'cb-'.\Illuminate\Support\Str::random(6))
<span class="nx-check">
    <input type="checkbox" id="{{ $id }}" @if ($label) aria-label="{{ $label }}" @endif {{ $attributes }}>
    <span class="nx-check__box" aria-hidden="true"></span>
</span>
