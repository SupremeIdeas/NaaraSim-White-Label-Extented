{{-- Analytics/marketing tracking (owner request). Rendered only when the admin
     has saved a valid ID; the IDs are shape-validated in App\Support\Tracking, so
     nothing arbitrary reaches the page. Standard GA4 + Meta Pixel snippets. --}}
@php($__ga = \App\Support\Tracking::gaId())
@php($__pixel = \App\Support\Tracking::pixelId())

@if ($__ga)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $__ga }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $__ga }}');
    </script>
@endif

@if ($__pixel)
    <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
        document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{{ $__pixel }}');
        fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" alt=""
        src="https://www.facebook.com/tr?id={{ $__pixel }}&ev=PageView&noscript=1"/></noscript>
@endif
