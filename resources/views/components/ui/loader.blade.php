{{-- Branded orbit loader (Module 32). Size via the --size CSS var. --}}
@props(['size' => '2.5rem', 'label' => 'Loading'])
<span {{ $attributes->merge(['class' => 'nx-loader']) }} style="--size: {{ $size }}" role="status" aria-label="{{ $label }}"></span>
