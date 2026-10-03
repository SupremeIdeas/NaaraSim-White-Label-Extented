{{-- Numbered step heading: icon tile + title + one-line hint. --}}
@props(['icon' => 'pin', 'title' => null, 'hint' => null])
<div {{ $attributes->merge(['class' => 'ns-step']) }}>
    <span class="ns-tile"><x-nx.icon :name="$icon" /></span>
    <div><b>{{ $title }}</b>@if ($hint)<small>{{ $hint }}</small>@endif</div>
</div>
