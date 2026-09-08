@php($t = \App\Support\PreloaderSettings::loadingText($cfg ?? []))
<svg viewBox="0 0 86 86" class="circle-outer"><circle r="40" cy="43" cx="43" class="back"></circle><circle r="40" cy="43" cx="43" class="front"></circle></svg>
<svg viewBox="0 0 60 60" class="circle-middle"><circle r="27" cy="30" cx="30" class="back"></circle><circle r="27" cy="30" cx="30" class="front"></circle></svg>
<span class="nx-pl-text">{{ $t }}</span>
