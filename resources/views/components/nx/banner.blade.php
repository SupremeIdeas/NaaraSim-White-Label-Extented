{{-- Dismissible info banner. The dismissal is remembered per browser under nx.dismiss.{key}; pass no key for a permanent one. --}}
@props(['icon' => 'shield', 'title' => null, 'text' => null, 'dismissKey' => null])
<div {{ $attributes->merge(['class' => 'ns-banner ns-ring']) }}
     @if ($dismissKey)
        x-data="{ gone: (() => { try { return localStorage.getItem('nx.dismiss.{{ $dismissKey }}') === '1'; } catch (e) { return false; } })() }" x-show="! gone"
     @endif>
    <span class="ns-tile"><x-nx.icon :name="$icon" /></span>
    <div class="ns-text" style="flex:1;min-width:0"><b>{{ $title }}</b>@if ($text)<small>{{ $text }}</small>@endif</div>
    @if ($dismissKey)
        <button type="button" class="ns-banner__dismiss" aria-label="{{ __('numbers.close') }}"
                @click="gone = true; try { localStorage.setItem('nx.dismiss.{{ $dismissKey }}', '1'); } catch (e) {}"><x-nx.icon name="x" /></button>
    @endif
</div>
