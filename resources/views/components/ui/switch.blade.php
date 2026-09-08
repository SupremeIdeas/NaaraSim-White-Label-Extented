{{-- Accessible branded toggle (Module 32): a REAL checkbox drives the pill, so
     keyboard, screen readers and wire:model all work. Use in settings rows. --}}
@props(['id' => null, 'label' => null])
@php($id = $id ?? 'sw-'.\Illuminate\Support\Str::random(6))
<span class="nx-switch">
    <input type="checkbox" id="{{ $id }}" @if ($label) aria-label="{{ $label }}" @endif {{ $attributes }}>
    <span class="nx-switch__track" aria-hidden="true"></span>
</span>
