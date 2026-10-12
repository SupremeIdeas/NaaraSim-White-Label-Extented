{{-- Analytics/marketing tracking. Rendered only when the admin has saved a valid ID; the IDs are shape-validated in App\Support\Tracking, so nothing arbitrary reaches the page.
     CONSENT: while the cookie banner is on (the default), NOTHING here runs and nothing is requested from Google or Meta until the visitor allows that category: the snippets are
     inert `text/plain` blocks that resources/js/cookie-consent.js turns into real scripts after a yes. Google Consent Mode v2 starts as denied and is updated on the choice. --}}
@php
    $__ga = \App\Support\Tracking::gaId();
    $__pixel = \App\Support\Tracking::pixelId();
    $__gate = \App\Support\CookieConsent\CookieConsent::enabled();
@endphp

@if ($__ga)
    @if ($__gate)
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('consent', 'default', {ad_storage: 'denied', analytics_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500});
        </script>
        <script type="text/plain" data-consent="analytics" data-src="https://www.googletagmanager.com/gtag/js?id={{ $__ga }}" async></script>
        <script type="text/plain" data-consent="analytics">gtag('js', new Date()); gtag('config', '{{ $__ga }}');</script>
    @else
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $__ga }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $__ga }}');
        </script>
    @endif
@endif

@if ($__pixel)
    <script @if ($__gate) type="text/plain" data-consent="marketing" @endif>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
        document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{{ $__pixel }}');
        fbq('track', 'PageView');
    </script>
    @unless ($__gate)
        <noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ $__pixel }}&ev=PageView&noscript=1"/></noscript>
    @endunless
@endif
