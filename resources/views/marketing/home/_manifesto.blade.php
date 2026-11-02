{{-- Homepage manifesto — scroll word-reveal (owner request, 2026-09-21,
     explicitly re-requested: "that reveal text on scroll... show case in
     hero after the flags carousel"). Real Naara copy, ported verbatim from
     the owner-supplied reference. Fixed light surface regardless of the
     site's own dark/light mode, per the owner's "keep these in their light
     bg state for a premium feel" instruction. --}}
@php
    $manifestoWords = preg_split('/\s+/', trim((string) ($s['body'] ?? '')));
@endphp
<section data-manifesto class="nx-manifesto">
    <div class="nx-manifesto-inner">
        <div class="nx-manifesto-side">
            <p class="nx-manifesto-tag">{{ $s['tag'] ?? 'Our Manifesto' }}</p>
            <h2 class="nx-manifesto-title">{{ $s['title'] ?? 'What we' }} <em>{{ $s['title_accent'] ?? 'stand for.' }}</em></h2>
            <div class="nx-manifesto-line"></div>
        </div>
        <p class="nx-manifesto-text">
            @foreach ($manifestoWords as $word)
                <span class="nx-manifesto-word">{{ $word }}</span>{{ ! $loop->last ? ' ' : '' }}
            @endforeach
        </p>
    </div>
</section>
