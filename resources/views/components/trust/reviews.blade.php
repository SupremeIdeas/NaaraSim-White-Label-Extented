{{-- One Trustpilot placement (Trustpilot blueprint Phase 5). Renders NOTHING when the layer or this placement is off, so there is zero layout impact.
     With a template id it is the official TrustBox; without one it is a plain, honest link to the profile. Never claims to show all reviews unless the list widget is used. --}}
@props(['placement'])
@php
    use App\Support\Trustpilot\TrustpilotSettings as TP;
    $cfg = TP::PLACEMENTS[$placement] ?? null;
    $show = $cfg && TP::active() && TP::placementOn($placement);
    $tpl = $show ? TP::templateId($cfg['type']) : '';
    $profile = $show ? TP::profileUrl() : '';
@endphp
@if ($show && ($tpl !== '' || $profile !== ''))
    <div {{ $attributes->merge(['class' => 'tp-slot']) }} data-tp-placement="{{ $placement }}" @if (TP::clickToLoad() && $tpl !== '') x-data="{ on: false }" @endif>
        @if ($tpl !== '')
            @if (TP::clickToLoad())
                <div x-show="!on" class="rounded-2xl border border-slate-200 p-4 text-center text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    <p>Reviews from Trustpilot load from their servers and may set cookies.</p>
                    <button type="button" @click="on = true; window.nTpLoad && window.nTpLoad()" class="mt-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium dark:border-slate-600">Show Trustpilot reviews</button>
                </div>
            @endif
            <div @if (TP::clickToLoad()) x-show="on" x-cloak @endif class="trustpilot-widget" data-locale="{{ TP::locale() }}" data-template-id="{{ $tpl }}" data-businessunit-id="{{ TP::businessUnitId() }}" data-style-height="{{ $cfg['height'] }}" data-style-width="100%" data-theme="light" @if ($cfg['type'] === 'list') data-stars="1,2,3,4,5" @endif style="min-height: {{ $cfg['height'] }}">
                @if ($profile !== '')<a href="{{ $profile }}" target="_blank" rel="noopener">Trustpilot</a>@endif
            </div>
            @once
                    <script>window.nTpLoad = window.nTpLoad || function () { if (document.getElementById('tp-bootstrap')) { if (window.Trustpilot) { document.querySelectorAll('.trustpilot-widget').forEach(function (e) { window.Trustpilot.loadFromElement(e, true); }); } return; } var s = document.createElement('script'); s.id = 'tp-bootstrap'; s.async = true; s.src = @json(TP::scriptSrc()); document.head.appendChild(s); };
                    @unless (TP::clickToLoad()) window.nTpLoad(); @endunless</script>
            @endonce
        @else
            <a href="{{ $profile }}" target="_blank" rel="noopener" class="text-sm font-medium underline">See our reviews on Trustpilot</a>
        @endif
    </div>
@endif
