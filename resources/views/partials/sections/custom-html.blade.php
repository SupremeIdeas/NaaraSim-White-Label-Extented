@php
    /** Custom HTML section (Section Builder §2). The stored HTML is ALREADY
     *  sanitized on save (App\Support\HtmlSanitizer, allowlist). We sanitize
     *  again on render as belt-and-braces so a row written before the sanitizer
     *  existed, or altered out-of-band, still can't inject script. */
    $c = $config ?? [];
    $clean = \App\Support\HtmlSanitizer::clean($c['html'] ?? '');
    $full = ($c['max_width'] ?? 'container') === 'full';
@endphp

@if ($clean !== '')
    <section class="nx-sec-html py-10 sm:py-14">
        <div class="{{ $full ? 'w-full' : 'mx-auto max-w-4xl px-4 sm:px-6' }}">
            <div class="nx-prose text-slate-700 dark:text-slate-200">
                {!! $clean !!}
            </div>
        </div>
    </section>
@endif
