{{-- "Diagnosis / Architecture / Promise" — homepage bento-grid block (owner
     request, 2026-09-21). Ported from an owner-supplied reference file that
     was already fully Naara-branded (real copy, real Naara-mechanic SVG
     illustrations) — see resources/css/sections.css's .nx-bento-* rules and
     resources/js/bento-diagnosis.js for the preserved technique (glass
     cards, mouse-tracking glow, scroll reveal). Sits between the hero's
     story band and the "how it works" sticky-stack section — see the
     include order in home.blade.php. Fixed light glass surface regardless
     of site theme, per the owner's "premium feel" instruction. --}}
<div data-bento-diagnosis>
<section class="nx-bento-wrap">
    <div class="nx-bento-inner">
        <p class="nx-bento-eyebrow">{{ $s['eyebrow'] ?? 'The Diagnosis' }}</p>
        <h2 class="nx-bento-headline">{{ $s['headline'] ?? "The problem isn't signal." }}<br><span class="grad">{{ $s['headline_accent'] ?? "It's what sits behind it." }}</span></h2>
        <div class="nx-bento-intro">
            <p>{{ $s['intro_1'] ?? '' }}</p>
            <p>{{ $s['intro_2'] ?? '' }}</p>
        </div>
        <div class="nx-bento-grid">
            <div class="nx-bento-cell is-span-7" data-cell="s2-a" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['f1_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['f1_title'] ?? '' }}</h3>
                    <p class="nx-bento-cell-text">{{ $s['f1_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="Checkout summary showing data, validity and no monthly fee, with the total shown before payment">
<rect class="sf" x="34" y="12" width="252" height="176" rx="16"/>
<circle class="fT" cx="58" cy="38" r="12"/>
<g transform="translate(58 38)" fill="none"><circle class="ico sW" r="6"/><path class="ico sW" d="M-6 0h12M0 -6c3 3 3 9 0 12M0 -6c-3 3 -3 9 0 12"/></g>
<text class="b" x="78" y="43">Europe, 5 GB</text>
<rect class="fTs" x="220" y="28" width="54" height="20" rx="10"/>
<text class="tt b" x="247" y="42" text-anchor="middle" style="font-size:10.5px">30 days</text>
<line class="ln" x1="50" y1="60" x2="270" y2="60"/>
<text class="m" x="50" y="82">Data</text><text x="270" y="82" text-anchor="end">5 GB</text>
<text class="m" x="50" y="104">Validity</text><text x="270" y="104" text-anchor="end">30 days</text>
<text class="m" x="50" y="126">Monthly fee</text><text x="270" y="126" text-anchor="end">None</text>
<rect class="fTs" x="46" y="140" width="228" height="36" rx="11"/>
<text class="tt b" x="60" y="162" style="font-size:11px">Total shown before you pay</text>
<circle class="fG" cx="254" cy="158" r="10"/>
<path class="ico sW" fill="none" d="M249 158l3.5 3.5 6-7"/>
</svg></div>
            </div>
            <div class="nx-bento-cell is-span-5" data-cell="s2-b" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['f2_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['f2_title'] ?? '' }}</h3>
                    <p class="nx-bento-cell-text">{{ $s['f2_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="One phone showing an eSIM data plan, a phone number and an incoming verification code">
<rect class="sf" x="98" y="6" width="124" height="188" rx="20"/>
<rect class="fN" x="146" y="13" width="28" height="5" rx="2.5" opacity=".45"/>
<rect class="sf2" x="110" y="28" width="100" height="46" rx="10"/>
<g class="ico sT" fill="none" transform="translate(127 52)"><path d="M-6 6v-3M-2 6v-7M2 6v-11M6 6v-15"/></g>
<text class="b" x="143" y="47" style="font-size:11.5px">eSIM data</text>
<text class="m" x="143" y="62">5 GB active</text>
<rect class="sf2" x="110" y="82" width="100" height="46" rx="10"/>
<g class="ico sT" fill="none" transform="translate(127 106)"><path d="M-5 -3h11M-6 3h11M-2 -7l-2 14M4 -7l-2 14"/></g>
<text class="b" x="143" y="101" style="font-size:11.5px">Number</text>
<text class="m" x="143" y="116">SMS + voice</text>
<rect class="fTs" x="110" y="136" width="100" height="46" rx="10"/>
<text class="m" x="122" y="153" style="font-size:10px">Code received</text>
<text class="tt b" x="122" y="172" style="font-size:15px;letter-spacing:2px">482 913</text>
<circle class="fG" cx="197" cy="149" r="7"/>
<path class="ico sW" fill="none" style="stroke-width:1.8" d="M193.5 149l2.5 2.5 4-5"/>
<rect class="fTs" x="8" y="40" width="80" height="22" rx="11"/>
<text class="tt b" x="48" y="55" text-anchor="middle" style="font-size:10.5px">Naara Data</text>
<path class="ico sT" style="stroke-width:1.4" fill="none" d="M88 51H110"/>
<rect class="fTs" x="8" y="94" width="80" height="22" rx="11"/>
<text class="tt b" x="48" y="109" text-anchor="middle" style="font-size:10.5px">Naara Line</text>
<path class="ico sT" style="stroke-width:1.4" fill="none" d="M88 105H110"/>
<rect class="fTs" x="232" y="148" width="82" height="22" rx="11"/>
<text class="tt b" x="273" y="163" text-anchor="middle" style="font-size:10.5px">Naara Verify</text>
<path class="ico sT" style="stroke-width:1.4" fill="none" d="M210 159H232"/>
</svg></div>
            </div>
            <div class="nx-bento-cell is-span-6" data-cell="s2-c" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['f3_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['f3_title'] ?? '' }}</h3>
                    <p class="nx-bento-cell-text">{{ $s['f3_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="An order tries partner A, then B, then C, and refunds the wallet in full if every partner fails">
<path class="ln" d="M80 62H136M184 62H240"/>
<path class="ln" d="M131 56l7 6-7 6M235 56l7 6-7 6"/>
<circle class="fCs sC" cx="56" cy="62" r="24" stroke-width="1.6"/>
<path class="ico sC" fill="none" d="M48 54l16 16M64 54l-16 16"/>
<circle class="fCs sC" cx="160" cy="62" r="24" stroke-width="1.6"/>
<path class="ico sC" fill="none" d="M152 54l16 16M168 54l-16 16"/>
<circle class="fT" cx="264" cy="62" r="24"/>
<path class="ico sW" style="stroke-width:2.6" fill="none" d="M253 63l8 8 14-16"/>
<text x="56" y="106" text-anchor="middle">Partner A</text>
<text class="ct" x="56" y="122" text-anchor="middle" style="font-size:10.5px">Out of stock</text>
<text x="160" y="106" text-anchor="middle">Partner B</text>
<text class="ct" x="160" y="122" text-anchor="middle" style="font-size:10.5px">Timed out</text>
<text class="b" x="264" y="106" text-anchor="middle">Partner C</text>
<text class="tt b" x="264" y="122" text-anchor="middle" style="font-size:10.5px">Delivered</text>
<rect class="sf2 dash" x="26" y="142" width="268" height="44" rx="14"/>
<g transform="translate(46 156) scale(.72)" fill="none" class="ico sT"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></g>
<text class="b" x="72" y="162" style="font-size:11.5px">If every partner fails</text>
<text class="m" x="72" y="177">Exact refund and an admin alert</text>
</svg></div>
            </div>
            <div class="nx-bento-cell is-span-6" data-cell="s2-d" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['f4_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['f4_title'] ?? '' }}</h3>
                    <p class="nx-bento-cell-text">{{ $s['f4_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 300 190" role="img" aria-label="Card, bank and crypto payments all top up one USD wallet that pays for eSIMs, numbers and gift cards">
<rect class="sf2" x="4" y="30" width="72" height="30" rx="15"/><text x="40" y="50" text-anchor="middle" style="font-size:12.5px">Card</text>
<rect class="sf2" x="4" y="72" width="72" height="30" rx="15"/><text x="40" y="92" text-anchor="middle" style="font-size:12.5px">Bank</text>
<rect class="sf2" x="4" y="114" width="72" height="30" rx="15"/><text x="40" y="134" text-anchor="middle" style="font-size:12.5px">Crypto</text>
<path class="ln" d="M76 45H96M76 87H96M76 129H96"/>
<rect class="fT" x="96" y="20" width="108" height="130" rx="16"/>
<rect class="fT2" x="96" y="20" width="108" height="9" rx="4.5" opacity=".55"/>
<text class="w h" x="150" y="86" text-anchor="middle" style="font-size:27px">USD</text>
<text class="w" x="150" y="108" text-anchor="middle" style="font-size:11.5px;opacity:.92">one balance</text>
<path class="ln" d="M204 45H224M204 87H224M204 129H224"/>
<path class="ln" d="M219 40l5 5-5 5M219 82l5 5-5 5M219 124l5 5-5 5"/>
<rect class="sf2" x="224" y="30" width="72" height="30" rx="15"/><text x="260" y="50" text-anchor="middle" style="font-size:12.5px">eSIM</text>
<rect class="sf2" x="224" y="72" width="72" height="30" rx="15"/><text x="260" y="92" text-anchor="middle" style="font-size:12.5px">Numbers</text>
<rect class="sf2" x="224" y="114" width="72" height="30" rx="15"/><text x="260" y="134" text-anchor="middle" style="font-size:12.5px">Gifts</text>
<g transform="translate(70 162)" fill="none" class="ico sT"><rect x="0" y="6" width="12" height="9" rx="2"/><path d="M2.5 6V3.5a3.5 3.5 0 0 1 7 0V6"/></g>
<text class="m" x="90" y="176" style="font-size:11.5px">Rate fixed when you top up</text>
</svg></div>
            </div>
        </div>
    </div>
</section>

<section class="nx-bento-wrap">
    <div class="nx-bento-inner">
        <p class="nx-bento-eyebrow">{{ $s['arch_eyebrow'] ?? '' }}</p>
        <h2 class="nx-bento-headline">{{ $s['arch_headline'] ?? '' }}<br><span class="grad">{{ $s['arch_headline_accent'] ?? '' }}</span></h2>
        <div class="nx-bento-intro">
            <p>{{ $s['arch_intro_1'] ?? '' }}</p>
            <p>{{ $s['arch_intro_2'] ?? '' }}</p>
        </div>
        <div class="nx-bento-grid">
            <div class="nx-bento-cell is-span-5" data-cell="s3-a" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['s1_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['s1_title'] ?? '' }}</h3>
                    <p class="nx-bento-cell-text">{{ $s['s1_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="A plan is priced once by a single pricing service, quoted again on the server with any coupon or credits, and the same total is charged and shown on the receipt">
<rect class="sf2" x="24" y="10" width="176" height="38" rx="11"/>
<text class="m" x="38" y="25" style="font-size:10px">You choose</text>
<text class="b" x="38" y="41">Plan + destination</text>
<path class="ico sT" fill="none" d="M112 48V60M106 55l6 6 6-6"/>
<rect class="fT" x="24" y="62" width="176" height="42" rx="11"/>
<text class="w b" x="38" y="80">Pricing service</text>
<text class="w" x="38" y="95" style="font-size:10px;opacity:.9">One source for every price</text>
<path class="ico sT" fill="none" d="M112 104V116M106 111l6 6 6-6"/>
<rect class="sf" x="24" y="118" width="176" height="72" rx="12"/>
<text class="b" x="38" y="138">Quote</text>
<rect class="fTs" x="38" y="148" width="60" height="20" rx="10"/><text class="tt b" x="68" y="162" text-anchor="middle" style="font-size:10px">Coupon</text>
<rect class="fTs" x="104" y="148" width="66" height="20" rx="10"/><text class="tt b" x="137" y="162" text-anchor="middle" style="font-size:10px">Credits</text>
<path class="ln" d="M200 84H212"/>
<rect class="fTs" x="212" y="64" width="98" height="40" rx="12"/>
<text class="tt b" x="261" y="82" text-anchor="middle" style="font-size:11px">Re-quoted</text>
<text class="m" x="261" y="96" text-anchor="middle" style="font-size:10px">on the server</text>
<path class="ln" d="M200 154H222"/>
<path class="ln" d="M217 149l5 5-5 5"/>
<rect class="sf" x="222" y="112" width="88" height="78" rx="12"/>
<text class="b" x="234" y="132">Receipt</text>
<rect class="fTs" x="230" y="142" width="72" height="24" rx="8"/>
<text class="tt b" x="240" y="158" style="font-size:11px">Total</text>
<circle class="fG" cx="288" cy="154" r="8"/>
<path class="ico sW" fill="none" style="stroke-width:1.8" d="M284.5 154l2.5 2.5 4.5-5.5"/>
<text class="m" x="266" y="181" text-anchor="middle" style="font-size:10px">matches quote</text>
</svg></div>
            </div>
            <div class="nx-bento-cell is-span-7" data-cell="s3-b" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['s2_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['s2_title'] ?? '' }}</h3>
                    <p class="nx-bento-cell-text">{{ $s['s2_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="Two separate failover lanes, Naara Data and Naara Connect, with no fallback across lanes and skipped partners marked open or paused">
<text class="m b" x="20" y="18">Naara Data</text>
<path class="ln" d="M78 46H142M178 46H242"/>
<circle class="fT" cx="60" cy="46" r="18"/><text class="w b" x="60" y="51" text-anchor="middle">1</text>
<circle class="sf2 dash" cx="160" cy="46" r="18"/><text class="m b" x="160" y="51" text-anchor="middle">2</text>
<circle class="fT" cx="260" cy="46" r="18"/><text class="w b" x="260" y="51" text-anchor="middle">3</text>
<text class="m" x="60" y="80" text-anchor="middle" style="font-size:10px">first try</text>
<text class="ct" x="160" y="80" text-anchor="middle" style="font-size:10px">circuit open</text>
<text class="m" x="260" y="80" text-anchor="middle" style="font-size:10px">next</text>
<path class="ln dash" d="M20 99H96M224 99H300"/>
<rect class="fCs" x="98" y="88" width="124" height="22" rx="11"/>
<text class="ct b" x="160" y="103" text-anchor="middle" style="font-size:10px">no cross-lane fallback</text>
<text class="m b" x="20" y="130">Naara Connect</text>
<path class="ln" d="M78 158H142M178 158H242"/>
<circle class="sf2 dash" cx="60" cy="158" r="18"/><text class="m b" x="60" y="163" text-anchor="middle">1</text>
<circle class="fT" cx="160" cy="158" r="18"/><text class="w b" x="160" y="163" text-anchor="middle">2</text>
<circle class="fT" cx="260" cy="158" r="18"/><text class="w b" x="260" y="163" text-anchor="middle">3</text>
<text class="ct" x="60" y="192" text-anchor="middle" style="font-size:10px">paused by admin</text>
<text class="m" x="160" y="192" text-anchor="middle" style="font-size:10px">first try</text>
<text class="m" x="260" y="192" text-anchor="middle" style="font-size:10px">next</text>
</svg></div>
            </div>
            <div class="nx-bento-cell is-span-6" data-cell="s3-c" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['s3_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['s3_title'] ?? '' }}</h3>
                    <p class="nx-bento-cell-text">{{ $s['s3_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="Wallet ledger where each row records the balance before and after, ending with an exact refund">
<rect class="sf" x="20" y="10" width="280" height="180" rx="16"/>
<g transform="translate(36 26)" fill="none" class="ico sT"><rect x="0" y="6" width="12" height="9" rx="2"/><path d="M2.5 6V3.5a3.5 3.5 0 0 1 7 0V6"/></g>
<text class="b" x="56" y="40">Wallet ledger</text>
<text class="m" x="212" y="40" text-anchor="end">before</text><text class="m" x="276" y="40" text-anchor="end">after</text>
<line class="ln" x1="34" y1="52" x2="286" y2="52"/>
<circle class="fT" cx="46" cy="76" r="8"/><path class="ico sW" style="stroke-width:1.8" fill="none" d="M42 76h8M46 72v8"/>
<text x="62" y="80">Top-up</text><text class="m" x="212" y="80" text-anchor="end">0.00</text><text x="276" y="80" text-anchor="end">40.00</text>
<circle class="fC" cx="46" cy="108" r="8"/><path class="ico sW" style="stroke-width:1.8" fill="none" d="M42 108h8"/>
<text x="62" y="112">eSIM order</text><text class="m" x="212" y="112" text-anchor="end">40.00</text><text x="276" y="112" text-anchor="end">28.50</text>
<rect class="fTs" x="28" y="126" width="264" height="32" rx="9"/>
<circle class="fG" cx="46" cy="142" r="8"/><path class="ico sW" style="stroke-width:1.6" fill="none" d="M43 141h5a2.6 2.6 0 0 1 0 5h-3M45 138.5l-2.5 2.5 2.5 2.5"/>
<text class="tt b" x="62" y="146">Refund</text><text class="m" x="212" y="146" text-anchor="end">28.50</text><text class="tt b" x="276" y="146" text-anchor="end">40.00</text>
<text class="m" x="160" y="176" text-anchor="middle" style="font-size:10px">Same reference, same transaction</text>
</svg></div>
            </div>
            <div class="nx-bento-cell is-span-6" data-cell="s3-d" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['s4_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['s4_title'] ?? '' }}</h3>
                    <p class="nx-bento-cell-text">{{ $s['s4_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="A reliability score line that dips slightly after one failed order and recovers, with the circuit shown as closed">
<rect class="sf" x="12" y="10" width="296" height="180" rx="16"/>
<text class="m" x="28" y="34">Reliability score, trailing 30 days</text>
<path class="fTs" d="M28 82 C58 78 88 76 118 78 S150 80 166 106 S192 98 208 86 S252 68 292 64 L292 148 L28 148 Z"/>
<path class="ico sT" style="stroke-width:3" fill="none" d="M28 82 C58 78 88 76 118 78 S150 80 166 106 S192 98 208 86 S252 68 292 64"/>
<line class="ln" x1="28" y1="148" x2="292" y2="148"/>
<circle class="fCs sC" cx="166" cy="106" r="7" stroke-width="1.8"/>
<path class="ln" d="M166 113V128"/>
<text class="ct b" x="166" y="141" text-anchor="middle" style="font-size:10.5px">one failed order</text>
<text class="m" x="28" y="172" style="font-size:10px">More orders, more weight</text>
<rect class="fTs" x="204" y="158" width="88" height="22" rx="11"/>
<text class="tt b" x="248" y="173" text-anchor="middle" style="font-size:10.5px">Circuit closed</text>
</svg></div>
            </div>
        </div>
    </div>
</section>

<section class="nx-bento-wrap">
    <div class="nx-bento-inner">
        <p class="nx-bento-eyebrow">{{ $s['promise_eyebrow'] ?? 'The Naara Promise' }}</p>
        <div class="nx-bento-formula">
            <div class="nx-bento-formula-line"><span class="grad">{{ $s['pl1_accent'] ?? '' }}</span> {{ $s['pl1_rest'] ?? '' }}</div>
            <div class="nx-bento-formula-line"><span class="grad">{{ $s['pl2_accent'] ?? '' }}</span> {{ $s['pl2_rest'] ?? '' }}</div>
            <div class="nx-bento-formula-line"><span class="grad">{{ $s['pl3_accent'] ?? '' }}</span> {{ $s['pl3_rest'] ?? '' }}</div>
        </div>
        <div class="nx-bento-intro">
            <p>{{ $s['promise_intro'] ?? '' }}</p>
        </div>
        <div class="nx-bento-grid">
            <div class="nx-bento-cell is-span-4" data-cell="s4-a" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['p1_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['p1_title'] ?? '' }}</h3>
                    <span class="nx-bento-eyebrow-sm" style="margin-top:-.4rem">{{ $s['p1_sub'] ?? '' }}</span>
                    <p class="nx-bento-cell-text" style="margin-top:.5rem">{{ $s['p1_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="Device check showing a phone model marked eSIM supported, above four plan facts: data, validity, voice and coverage">
<rect class="sf" x="18" y="8" width="284" height="184" rx="16"/>
<text class="m" x="34" y="30">Your phone</text>
<rect class="sf2" x="32" y="38" width="256" height="36" rx="10"/>
<text class="b" x="46" y="61">Galaxy S24</text>
<rect class="fT" x="180" y="46" width="100" height="20" rx="10"/>
<text class="w b" x="230" y="60" text-anchor="middle" style="font-size:10px">eSIM supported</text>
<rect class="sf2" x="32" y="86" width="124" height="44" rx="10"/><text class="m" x="44" y="103" style="font-size:10px">Data</text><text class="b" x="44" y="120">5 GB</text>
<rect class="sf2" x="164" y="86" width="124" height="44" rx="10"/><text class="m" x="176" y="103" style="font-size:10px">Validity</text><text class="b" x="176" y="120">30 days</text>
<rect class="sf2" x="32" y="138" width="124" height="44" rx="10"/><text class="m" x="44" y="155" style="font-size:10px">Voice</text><text class="b" x="44" y="172">Data only</text>
<rect class="sf2" x="164" y="138" width="124" height="44" rx="10"/><text class="m" x="176" y="155" style="font-size:10px">Coverage</text><text class="b" x="176" y="172">Local</text>
</svg></div>
            </div>
            <div class="nx-bento-cell is-span-4" data-cell="s4-b" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['p2_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['p2_title'] ?? '' }}</h3>
                    <span class="nx-bento-eyebrow-sm" style="margin-top:-.4rem">{{ $s['p2_sub'] ?? '' }}</span>
                    <p class="nx-bento-cell-text" style="margin-top:.5rem">{{ $s['p2_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="A QR code with a manual activation code, a verification code countdown that auto-refunds on timeout, and a pay, provisioned, delivered progress line">
<rect class="sf" x="24" y="10" width="104" height="100" rx="12"/><rect class="fQ" x="62.5" y="23.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="71.5" y="32.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="35.5" y="50.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="62.5" y="50.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="89.5" y="50.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="44.5" y="59.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="71.5" y="59.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="98.5" y="59.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="62.5" y="77.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="89.5" y="77.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="71.5" y="86.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="98.5" y="86.5" width="7.5" height="7.5" rx="1.5"/><rect class="fQ" x="35.5" y="23.5" width="25.5" height="25.5" rx="3"/><rect class="fS" x="42.2" y="30.2" width="13.5" height="13.5" rx="1.5"/><rect class="fQ" x="89.5" y="23.5" width="25.5" height="25.5" rx="3"/><rect class="fS" x="96.2" y="30.2" width="13.5" height="13.5" rx="1.5"/><rect class="fQ" x="35.5" y="77.5" width="25.5" height="25.5" rx="3"/><rect class="fS" x="42.2" y="84.2" width="13.5" height="13.5" rx="1.5"/>
<text class="m" x="76" y="130" text-anchor="middle" style="font-size:10px">QR + manual code</text>
<path class="ln dash" d="M160 20V120"/>
<circle class="ln" style="stroke-width:8" cx="226" cy="62" r="38"/>
<circle class="sT" cx="226" cy="62" r="38" stroke-width="8" fill="none" stroke-linecap="round" stroke-dasharray="180 240" transform="rotate(-90 226 62)"/>
<text class="h" x="226" y="68" text-anchor="middle" style="font-size:17px">OTP</text>
<circle class="fG" cx="226" cy="24" r="10"/>
<path class="ico sW" style="stroke-width:2.2" fill="none" d="M221.5 24l3.2 3.2 5-6"/>
<text class="m" x="226" y="130" text-anchor="middle" style="font-size:10px">Refund if it times out</text>
<path class="sT" stroke-width="2" d="M70 160H250"/>
<circle class="fT" cx="70" cy="160" r="6"/><circle class="fT" cx="160" cy="160" r="6"/><circle class="fG" cx="250" cy="160" r="6"/>
<text class="b" x="70" y="184" text-anchor="middle" style="font-size:10.5px">Pay</text>
<text class="b" x="160" y="184" text-anchor="middle" style="font-size:10.5px">Provisioned</text>
<text class="b" x="250" y="184" text-anchor="middle" style="font-size:10.5px">In My Lines</text>
</svg></div>
            </div>
            <div class="nx-bento-cell is-span-4" data-cell="s4-c" role="button" tabindex="0">
                <div class="nx-bento-cell-content">
                    <span class="nx-bento-eyebrow-sm">{{ $s['p3_label'] ?? '' }}</span>
                    <h3 class="nx-bento-cell-title">{{ $s['p3_title'] ?? '' }}</h3>
                    <span class="nx-bento-eyebrow-sm" style="margin-top:-.4rem">{{ $s['p3_sub'] ?? '' }}</span>
                    <p class="nx-bento-cell-text" style="margin-top:.5rem">{{ $s['p3_text'] ?? '' }}</p>
                </div>
                <div class="nx-bento-art" aria-hidden="true"><svg class="ill" viewBox="0 0 320 200" role="img" aria-label="A shield at the centre, with the Nia support agent, NaaraCredits and automatic refunds around it">
<path class="fT" d="M160 30l40 14v30c0 22-16 36-40 44-24-8-40-22-40-44V44z"/>
<path class="ico sW" style="stroke-width:3.4" fill="none" d="M143 76l12 12 24-26"/>
<rect class="sf" x="8" y="46" width="86" height="46" rx="14"/>
<path class="sf" d="M28 92l-7 14 20-14z"/>
<text class="b" x="22" y="66">Nia</text>
<text class="m" x="22" y="82" style="font-size:10px">your account</text>
<text class="m" x="51" y="122" text-anchor="middle" style="font-size:10px">hands off to</text>
<text class="m" x="51" y="135" text-anchor="middle" style="font-size:10px">a human</text>
<circle class="fG" cx="270" cy="66" r="27"/>
<circle cx="270" cy="66" r="20" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="1.6"/>
<text class="w h" x="270" y="73" text-anchor="middle" style="font-size:20px">N</text>
<text class="m" x="270" y="112" text-anchor="middle" style="font-size:10px">NaaraCredits</text>
<rect class="sf2" x="64" y="150" width="192" height="38" rx="19"/>
<g transform="translate(84 160) scale(.7)" fill="none" class="ico sT"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></g>
<text class="b" x="112" y="174" style="font-size:12px">Automatic refund</text>
</svg></div>
            </div>
        </div>
    </div>
</section>

@php($bentoModals = \App\Support\SiteContent::diagnosisModals($s))
<div class="nx-bento-modal" data-bento-modal aria-hidden="true">
    <div class="nx-bento-modal__panel" role="dialog" aria-modal="true" aria-labelledby="nx-bento-modal-headline">
        <div class="nx-bento-modal__actions">
            <button type="button" class="nx-bento-modal__btn" data-bento-theme aria-label="Toggle light or dark" aria-pressed="false">
                <x-icon name="moon" class="h-5 w-5" />
            </button>
            <button type="button" class="nx-bento-modal__btn" data-bento-close aria-label="Close">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>
        <div class="nx-bento-modal__interior">
            <div class="nx-bento-modal__copy">
                <p class="nx-bento-modal__eyebrow" data-m-eyebrow></p>
                <h3 class="nx-bento-modal__headline" id="nx-bento-modal-headline" data-m-headline></h3>
                <div class="nx-bento-modal__body" data-m-body></div>
                <p class="nx-bento-modal__quote" data-m-quote hidden></p>
                <ul class="nx-bento-modal__bullets" data-m-bullets></ul>
            </div>
            <div class="nx-bento-modal__art" data-m-art aria-hidden="true"></div>
        </div>
    </div>
    <script type="application/json" data-bento-modal-data>@json($bentoModals)</script>
</div>
</div>
