{{-- Cloudflare Turnstile widget (Module 33). Renders only when Turnstile is
     active (admin-enabled AND keys configured); otherwise nothing is output and
     the form works exactly as before. The script + challenge iframe come from
     the Cloudflare origin, which is added to the CSP only while active. --}}
@if (\App\Support\Turnstile::active())
    <div class="cf-turnstile" data-sitekey="{{ \App\Support\Turnstile::siteKey() }}" data-theme="auto"></div>
    <script src="{{ \App\Support\Turnstile::ORIGIN }}/turnstile/v0/api.js" async defer></script>
@endif
