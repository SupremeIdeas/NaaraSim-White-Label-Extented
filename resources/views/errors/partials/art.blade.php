{{-- Illustrations for the error pages. Pure inline SVG (no emoji, no images, no scripts), coloured by the page's own CSS variables so each install keeps its brand colour and
     both light and dark mode work. Props: $name (compass|alert|wrench|lock|door|clock|gauge|phone). --}}
<svg viewBox="0 0 320 200" role="img" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="g1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" style="stop-color:var(--c1)"/><stop offset="1" style="stop-color:var(--c2)"/></linearGradient>
        <linearGradient id="g2" x1="0" y1="1" x2="1" y2="0"><stop offset="0" style="stop-color:var(--c2)"/><stop offset="1" style="stop-color:var(--c1)"/></linearGradient>
        <radialGradient id="halo" cx=".5" cy=".5" r=".5"><stop offset="0" style="stop-color:var(--c1);stop-opacity:.28"/><stop offset="1" style="stop-color:var(--c1);stop-opacity:0"/></radialGradient>
        <filter id="soft" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#0b0b0c" flood-opacity=".18"/></filter>
    </defs>
    <circle cx="160" cy="96" r="92" fill="url(#halo)"/>
    <ellipse cx="160" cy="180" rx="92" ry="7" style="fill:var(--ink)" opacity=".09"/>
    <g class="tw" style="fill:var(--c2)"><path d="M54 52l3 8 8 3-8 3-3 8-3-8-8-3 8-3z"/><path d="M270 40l2.5 6 6 2.5-6 2.5-2.5 6-2.5-6-6-2.5 6-2.5z"/><circle cx="268" cy="132" r="3"/><circle cx="48" cy="128" r="2.5"/></g>

@switch($name)
@case('compass')
    {{-- 404: a folded map, a dashed route that stops short, and a pin that cannot be found --}}
    <g filter="url(#soft)">
        <path d="M70 72l52-11v90l-52 11z" style="fill:var(--paper);stroke:var(--line)" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M122 61l52 12v90l-52-12z" style="fill:var(--paper2);stroke:var(--line)" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M174 73l52-11v90l-52 11z" style="fill:var(--paper);stroke:var(--line)" stroke-width="1.5" stroke-linejoin="round"/>
    </g>
    <path d="M82 104l30-6M84 124l32-7M132 92l30 7M136 130l30 7M186 92l30-6M186 126l30-6" style="stroke:var(--line)" stroke-width="3" stroke-linecap="round"/>
    <path d="M86 140c24-22 34-34 58-28s40 26 62 4" fill="none" stroke="url(#g1)" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="1 9"/>
    <path d="M78 134l12 12M90 134l-12 12" style="stroke:var(--c1)" stroke-width="4" stroke-linecap="round"/>
    <g class="bob"><path d="M214 48a17 17 0 0 1 17 17c0 15-17 31-17 31s-17-16-17-31a17 17 0 0 1 17-17z" fill="url(#g1)"/><circle cx="214" cy="65" r="7" fill="#fff"/>
        <path d="M211.2 62.6a3 3 0 1 1 4.4 2.6c-.9.5-1.4 1-1.4 1.9" fill="none" style="stroke:var(--c1)" stroke-width="1.8" stroke-linecap="round"/><circle cx="214.2" cy="70.2" r="1" style="fill:var(--c1)"/></g>
    <g class="spin" style="transform-origin:262px 150px"><circle cx="262" cy="150" r="13" style="fill:var(--paper);stroke:var(--line)" stroke-width="1.5"/><path d="m267 145-3.2 8.6-8.6 3.2 3.2-8.6z" fill="url(#g1)"/></g>
@break
@case('alert')
    {{-- 500: a server stack with a crack, and a cable that has come out of its socket --}}
    <g filter="url(#soft)">
        <rect x="98" y="38" width="124" height="38" rx="9" style="fill:var(--paper);stroke:var(--line)" stroke-width="1.5"/>
        <g class="jolt"><rect x="104" y="80" width="124" height="38" rx="9" style="fill:var(--paper);stroke:var(--line)" stroke-width="1.5"/></g>
        <rect x="98" y="122" width="124" height="38" rx="9" style="fill:var(--paper);stroke:var(--line)" stroke-width="1.5"/>
    </g>
    <g style="fill:var(--line)"><rect x="112" y="52" width="46" height="8" rx="4"/><rect x="118" y="94" width="46" height="8" rx="4"/><rect x="112" y="136" width="46" height="8" rx="4"/></g>
    <circle cx="198" cy="57" r="4.5" style="fill:#22c55e"/><circle cx="210" cy="57" r="4.5" style="fill:#22c55e"/>
    <circle class="blink" cx="204" cy="99" r="4.5" style="fill:#ef4444"/><circle cx="216" cy="99" r="4.5" style="fill:#ef4444"/>
    <circle cx="198" cy="141" r="4.5" style="fill:#22c55e"/><circle cx="210" cy="141" r="4.5" style="fill:#22c55e"/>
    <path d="M166 80l-9 14 10 4-12 20" fill="none" stroke="url(#g1)" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M222 150c34 2 38 22 6 28" fill="none" style="stroke:var(--muted)" stroke-width="4" stroke-linecap="round"/>
    <g class="jolt"><rect x="214" y="146" width="16" height="14" rx="3" fill="url(#g2)"/><path d="M232 150h8M232 156h8" style="stroke:var(--muted)" stroke-width="3" stroke-linecap="round"/></g>
    <path class="tw" d="M247 132l3 7 7 2-7 3-3 7-3-7-7-3 7-2z" style="fill:var(--c2)"/>
    <g class="bob"><path d="M232 28l24 42h-48z" fill="url(#g1)" stroke="#fff" stroke-width="3" stroke-linejoin="round"/><rect x="230" y="43" width="4" height="13" rx="2" fill="#fff"/><circle cx="232" cy="62" r="2.6" fill="#fff"/></g>
@break
@case('wrench')
    {{-- 503: meshing gears, a wrench and a progress bar --}}
    <g filter="url(#soft)">
        <g class="spin" style="transform-origin:128px 94px"><circle cx="128" cy="94" r="35" fill="none" style="stroke:var(--c1)" stroke-width="12" stroke-dasharray="11 11.9"/><circle cx="128" cy="94" r="29" fill="url(#g1)"/><circle cx="128" cy="94" r="11" style="fill:var(--paper)"/></g>
        <g class="spin2" style="transform-origin:192px 124px"><circle cx="192" cy="124" r="25" fill="none" style="stroke:var(--c2)" stroke-width="10" stroke-dasharray="8.2 8.5"/><circle cx="192" cy="124" r="20" fill="url(#g2)"/><circle cx="192" cy="124" r="8" style="fill:var(--paper)"/></g>
    </g>
    <g class="bob" style="transform-origin:218px 62px"><g transform="rotate(38 218 62)"><rect x="212" y="40" width="12" height="56" rx="6" style="fill:var(--ink)" opacity=".86"/><path d="M206 34a15 15 0 0 1 24 0l-3 12h-18z" style="fill:var(--ink)" opacity=".86"/><circle cx="218" cy="38" r="4" style="fill:var(--paper)"/></g></g>
    <rect x="100" y="164" width="120" height="9" rx="4.5" style="fill:var(--line)"/><rect class="bar" x="100" y="164" width="72" height="9" rx="4.5" fill="url(#g1)"/>
@break
@case('lock')
    {{-- 403: a shield with a padlock, and a key that does not fit --}}
    <g filter="url(#soft)"><path d="M160 34l54 19v44c0 38-24 62-54 74-30-12-54-36-54-74V53z" fill="url(#g1)"/></g>
    <path d="M160 46l42 15v36c0 29-18 49-42 59-24-10-42-30-42-59V61z" fill="#fff" opacity=".16"/>
    <rect x="137" y="92" width="46" height="38" rx="9" fill="#fff"/>
    <path d="M144 92V82a16 16 0 0 1 32 0v10" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round"/>
    <circle cx="160" cy="108" r="5.5" style="fill:var(--c1)"/><rect x="157.5" y="110" width="5" height="12" rx="2.5" style="fill:var(--c1)"/>
    <g class="bob" style="transform-origin:246px 138px"><g transform="rotate(-28 246 138)"><circle cx="246" cy="128" r="12" fill="none" style="stroke:var(--c2)" stroke-width="6"/><rect x="243" y="138" width="6" height="30" rx="3" style="fill:var(--c2)"/><path d="M249 152h9M249 160h7" style="stroke:var(--c2)" stroke-width="5" stroke-linecap="round"/></g></g>
@break
@case('door')
    {{-- 401: a door left open for whoever signs in --}}
    <g filter="url(#soft)"><rect x="104" y="36" width="112" height="134" rx="8" style="fill:var(--paper);stroke:var(--line)" stroke-width="2"/></g>
    <path d="M170 56l46-20v134l-46-10z" fill="url(#g2)" opacity=".38"/>
    <path d="M110 40l60 14v100l-60 14z" fill="url(#g1)"/>
    <path d="M122 62l36 9v62l-36 9z" fill="#fff" opacity=".14"/><circle cx="158" cy="108" r="5" fill="#fff"/>
    <g class="bob"><circle cx="248" cy="104" r="22" style="fill:var(--paper);stroke:var(--line)" stroke-width="2"/><circle cx="248" cy="98" r="7" fill="url(#g1)"/><path d="M235 117c2-8 8-11 13-11s11 3 13 11" fill="url(#g1)"/></g>
    <g class="tw"><path d="M248 74v-8M232 80l-5-6M264 80l5-6" style="stroke:var(--c2)" stroke-width="3" stroke-linecap="round"/></g>
@break
@case('clock')
    {{-- 419: an hourglass whose time ran out --}}
    <g filter="url(#soft)"><path d="M122 44h76c0 34-30 46-38 56 8 10 38 22 38 56h-76c0-34 30-46 38-56-8-10-38-22-38-56z" style="fill:var(--paper);stroke:var(--line)" stroke-width="2.5" stroke-linejoin="round"/></g>
    <path d="M132 50h56c-3 18-16 28-28 36-12-8-25-18-28-36z" fill="url(#g1)" opacity=".9"/>
    <path d="M160 100v38" stroke="url(#g1)" stroke-width="3" stroke-linecap="round" stroke-dasharray="3 5" class="sand"/>
    <path d="M136 150c4-14 14-20 24-20s20 6 24 20z" fill="url(#g2)"/>
    <rect x="114" y="34" width="92" height="11" rx="5.5" fill="url(#g1)"/><rect x="114" y="155" width="92" height="11" rx="5.5" fill="url(#g1)"/>
    <g class="bob"><circle cx="242" cy="66" r="22" style="fill:var(--paper);stroke:var(--line)" stroke-width="2"/><path d="M242 52v14l9 6" fill="none" style="stroke:var(--c1)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></g>
@break
@case('gauge')
    {{-- 429: a speedometer pushed into the red --}}
    <g filter="url(#soft)"><path d="M92 146a68 68 0 0 1 136 0z" style="fill:var(--paper);stroke:var(--line)" stroke-width="2"/></g>
    <path d="M104 146a56 56 0 0 1 112 0" fill="none" style="stroke:var(--line)" stroke-width="13" stroke-linecap="round"/>
    <path d="M104 146a56 56 0 0 1 15-38" fill="none" stroke="url(#g2)" stroke-width="13" stroke-linecap="round"/>
    <path d="M124 104a56 56 0 0 1 36-14" fill="none" stroke="#f59e0b" stroke-width="13" stroke-linecap="round"/>
    <path d="M178 94a56 56 0 0 1 38 52" fill="none" stroke="#ef4444" stroke-width="13" stroke-linecap="round"/>
    <g class="needle" style="transform-origin:160px 146px"><path d="M160 146L204 116" style="stroke:var(--ink)" stroke-width="5" stroke-linecap="round"/></g>
    <circle cx="160" cy="146" r="10" fill="url(#g1)"/><circle cx="160" cy="146" r="4" style="fill:var(--paper)"/>
    <path d="M70 120h-22M66 134h-26M72 106h-16" style="stroke:var(--c2)" stroke-width="3.5" stroke-linecap="round" class="tw"/>
@break
@case('phone')
    {{-- update needed: a phone with a download arrow --}}
    <g filter="url(#soft)"><rect x="118" y="30" width="84" height="142" rx="16" style="fill:var(--paper);stroke:var(--line)" stroke-width="2.5"/></g>
    <rect x="128" y="46" width="64" height="104" rx="8" fill="url(#g1)" opacity=".14"/><rect x="146" y="38" width="28" height="4" rx="2" style="fill:var(--line)"/>
    <circle cx="160" cy="98" r="26" fill="url(#g1)"/><g class="bob"><path d="M160 84v22m-10-9l10 10 10-10" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/></g>
    <rect x="140" y="132" width="40" height="6" rx="3" style="fill:var(--line)"/><circle cx="160" cy="160" r="4" style="fill:var(--line)"/>
@break
@default
    <circle cx="160" cy="96" r="44" fill="url(#g1)"/>
@endswitch
</svg>
