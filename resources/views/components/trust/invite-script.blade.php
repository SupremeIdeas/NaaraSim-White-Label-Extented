{{-- Trustpilot review-invitation script (JavaScript integration). Renders nothing unless the Trustpilot layer is on and an integration key exists. --}}
@if (\App\Support\Trustpilot\TrustpilotSettings::inviteActive())
<script>
    (function(w,d,s,r,n){w.TrustpilotObject=n;w[n]=w[n]||function(){(w[n].q=w[n].q||[]).push(arguments)};
        var a=d.createElement(s);a.async=1;a.src=r;a.type='text/java'+s;var f=d.getElementsByTagName(s)[0];
        f.parentNode.insertBefore(a,f)})(window,document,'script',@json(\App\Support\Trustpilot\TrustpilotSettings::inviteScriptSrc(), JSON_UNESCAPED_SLASHES),'tp');
    tp('register', @json(\App\Support\Trustpilot\TrustpilotSettings::integrationKey()));
</script>
@endif
