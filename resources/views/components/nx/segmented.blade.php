{{-- Segmented control. options: value => label. Selection state is the caller's (`current`); each button fires `wire:click`
     with the value through the `action` method name. --}}
@props(['options', 'current', 'action', 'small' => false, 'wrap' => false, 'label' => null])
<div {{ $attributes->merge(['class' => 'ns-seg'.($small ? ' ns-small' : '').($wrap ? ' ns-seg--wrap' : '')]) }} role="group" @if ($label) aria-label="{{ $label }}" @endif>
    @foreach ($options as $value => $text)
        <button type="button" wire:click="{{ $action }}('{{ $value }}')" wire:loading.attr="disabled" class="{{ (string) $current === (string) $value ? 'is-on' : '' }}" aria-pressed="{{ (string) $current === (string) $value ? 'true' : 'false' }}">{{ $text }}</button>
    @endforeach
</div>
