@props([
    'value' => 0,        // current rating (supports halves for display)
    'max' => 5,
    'readonly' => true,  // display-only vs interactive
    'wire' => null,      // Livewire property to bind an interactive rating to
    'size' => 'h-5 w-5',
    'label' => 'Rating',
])

@php $rounded = (int) round((float) $value); @endphp

@if ($readonly)
    {{-- Display: filled up to the value, gold on grey. --}}
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5']) }}
         role="img" aria-label="{{ $label }}: {{ $value }} out of {{ $max }}">
        @for ($i = 1; $i <= $max; $i++)
            <x-icon name="star" @class([
                $size,
                'fill-[#D4A017] text-[#D4A017]' => $i <= $rounded,
                'fill-transparent text-slate-300 dark:text-slate-600' => $i > $rounded,
            ]) />
        @endfor
    </div>
@else
    {{-- Interactive: radiogroup, click or arrow-keys, gold hover preview. --}}
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5']) }}
         x-data="{
            current: @if ($wire) @entangle($wire).live @else {{ (int) $value }} @endif,
            hover: 0,
            set(v) { this.current = v; },
         }"
         role="radiogroup" aria-label="{{ $label }}"
         @mouseleave="hover = 0">
        @for ($i = 1; $i <= $max; $i++)
            <button type="button"
                    role="radio"
                    :aria-checked="current === {{ $i }} ? 'true' : 'false'"
                    :tabindex="(current === {{ $i }} || (current === 0 && {{ $i }} === 1)) ? 0 : -1"
                    aria-label="{{ $i }} star{{ $i > 1 ? 's' : '' }}"
                    @click="set({{ $i }})"
                    @mouseenter="hover = {{ $i }}"
                    @keydown.arrow-right.prevent="set(Math.min({{ $max }}, current + 1)); $el.nextElementSibling?.focus()"
                    @keydown.arrow-left.prevent="set(Math.max(1, current - 1)); $el.previousElementSibling?.focus()"
                    class="rounded p-0.5 outline-none focus-visible:ring-2 focus-visible:ring-primary">
                <x-icon name="star" ::class="'{{ $size }} ' + (({{ $i }} <= (hover || current)) ? 'fill-[#D4A017] text-[#D4A017]' : 'fill-transparent text-slate-300 dark:text-slate-600')" />
            </button>
        @endfor
    </div>
@endif
