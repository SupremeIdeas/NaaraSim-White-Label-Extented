{{-- The admin's own animation (GIF / WebP / Lottie JSON), one file per light/dark mode (a mode with no file uses the other mode's).
     Runtime: the overlay is rendered once; a tiny inline script picks the file for the CURRENT mode (the .dark class is already set before
     the body paints) so only ONE file is ever downloaded. Preview (`$previewMode`): the chosen mode is rendered directly on the server, since
     inline scripts do not run when Livewire morphs the Studio. --}}
@php
    $res = \App\Support\CustomPreloader::resolved($cfg['custom'] ?? []);
    $pick = fn (?array $m) => $m ? ['kind' => $m['kind'], 'url' => \App\Support\CustomPreloader::url($m)] : null;
    $light = $pick($res['light']);
    $dark = $pick($res['dark']);
    $forced = $previewMode ?? null;
@endphp
@if ($forced)
    @php($one = $forced === 'dark' ? $dark : $light)
    <span class="nx-pl-custom">
        @if ($one && $one['kind'] === 'lottie')
            <span data-lottie="custom" data-lottie-src="{{ $one['url'] }}" style="display:block"></span>
        @elseif ($one)
            <img src="{{ $one['url'] }}" alt="" decoding="async" draggable="false">
        @endif
    </span>
@else
    <span class="nx-pl-custom"
          data-light-kind="{{ $light['kind'] ?? '' }}" data-light-url="{{ $light['url'] ?? '' }}"
          data-dark-kind="{{ $dark['kind'] ?? '' }}" data-dark-url="{{ $dark['url'] ?? '' }}"></span>
    <script>
        (function () {
            var el = document.currentScript.previousElementSibling;
            if (!el) return;
            var mode = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            var kind = el.getAttribute('data-' + mode + '-kind');
            var url = el.getAttribute('data-' + mode + '-url');
            if (!url) return;
            if (kind === 'lottie') {
                el.setAttribute('data-lottie', 'custom');
                el.setAttribute('data-lottie-src', url);
                document.dispatchEvent(new Event('nx:lottie-init'));
            } else {
                var img = new Image();
                img.alt = ''; img.decoding = 'async'; img.draggable = false;
                img.src = url;
                el.appendChild(img);
            }
        })();
    </script>
@endif
