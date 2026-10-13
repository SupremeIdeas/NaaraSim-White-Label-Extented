{{-- nx:converted --}}
{{-- "How did it go?" sheet (review moments). Mounted only for a signed-in person while the admin has review moments on and a Trustpilot profile is set; driven by resources/js/review-moments.js. Step 1: stars. Step 2: an invitation to Trustpilot offered to everyone, whatever they rated. Skin tokens only. --}}
@php
    $rmIdle = \App\Support\Reviews\ReviewMoments::idleMinutes();
    $rmSkip = json_encode(['/checkout', '/pay', '/topup', '/wallet/fund', '/login', '/register', '/install', '/two-factor']);
@endphp
<div class="ncr" data-review-moment hidden role="dialog" aria-modal="true" aria-labelledby="rm-title" data-idle="{{ $rmIdle }}" data-skip="{{ $rmSkip }}">
    <div class="ncr__scrim" data-rm-scrim></div>
    <div class="ncr__sheet">
        <div data-rm-step="rate">
            <div class="ncr__head"><button type="button" class="ncr__x" data-rm-close aria-label="Close"><x-nx.icon name="x" /></button><h2 id="rm-title" data-rm-title>How did it go?</h2></div>
            <p class="ncr__sub" data-rm-sub></p>
            <div class="ncr__stars" role="radiogroup" aria-label="Rate your experience from 1 to 5 stars">
                @foreach ([1, 2, 3, 4, 5] as $n)
                    <button type="button" class="ncr__star" role="radio" aria-checked="false" aria-label="{{ $n }} {{ $n === 1 ? 'star' : 'stars' }}" data-star="{{ $n }}"><x-nx.icon name="star" /></button>
                @endforeach
            </div>
            <div class="ncr__row"><button type="button" class="ncr__quiet" data-rm-close>Not now</button><button type="button" class="ncr__go" data-rm-submit disabled>Submit</button></div>
        </div>
        <div data-rm-step="thanks" hidden>
            <div class="ncr__head"><button type="button" class="ncr__x" data-rm-close aria-label="Close"><x-nx.icon name="x" /></button><h2>Thank you</h2></div>
            <p class="ncr__sub">Would you share your experience on Trustpilot? It takes a minute, it is public, and it helps other travellers decide.</p>
            <a class="ncr__tp" data-rm-link href="#" target="_blank" rel="noopener noreferrer"><x-nx.icon name="star" /> Write a review on Trustpilot</a>
            <a class="ncr__help" data-rm-support href="#" hidden wire:navigate>Something not right? Tell our team</a>
            <div class="ncr__row"><button type="button" class="ncr__quiet" data-rm-close>No thanks</button></div>
        </div>
    </div>
</div>
