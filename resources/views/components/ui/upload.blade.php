{{-- Drag-and-drop upload zone (Module 32). Pass wire:model etc. straight
     through; the invisible input covers the whole zone. --}}
@props(['label' => 'Click or drop a file here', 'hint' => null, 'accept' => null])
<label class="nx-upload">
    <x-icon name="upload" class="h-6 w-6 text-primary" />
    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}</span>
    @if ($hint)<span class="text-xs text-slate-400">{{ $hint }}</span>@endif
    <input type="file" @if ($accept) accept="{{ $accept }}" @endif {{ $attributes }}>
</label>
