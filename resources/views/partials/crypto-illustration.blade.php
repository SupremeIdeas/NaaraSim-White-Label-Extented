{{-- Crypto top-up artwork (inline SVG, no external image): a wallet receiving USDC / USDT along a chain, with a shield for safety. Colours come from skin tokens
     and two coin brand variables (--oc-usdc, --oc-usdt), so it follows every theme in light and dark. Decorative: aria-hidden. --}}
@php($uid = 'oc'.substr(md5(uniqid('', true)), 0, 6))
<svg class="oc-ill {{ $class ?? '' }}" viewBox="0 0 360 220" role="img" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="{{ $uid }}-w" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="rgb(var(--nx-teal))"/><stop offset="1" stop-color="rgb(var(--nx-cta-b))"/></linearGradient>
        <linearGradient id="{{ $uid }}-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="rgb(var(--nx-gold))"/><stop offset="1" stop-color="rgb(var(--nx-gold-2))"/></linearGradient>
        <radialGradient id="{{ $uid }}-glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="rgb(var(--nx-teal))" stop-opacity=".45"/><stop offset="1" stop-color="rgb(var(--nx-teal))" stop-opacity="0"/></radialGradient>
        <filter id="{{ $uid }}-sh" x="-20%" y="-20%" width="140%" height="160%"><feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#000" flood-opacity=".28"/></filter>
    </defs>
    {{-- glow and orbit --}}
    <circle cx="188" cy="112" r="104" fill="url(#{{ $uid }}-glow)"/>
    <ellipse cx="188" cy="118" rx="150" ry="62" fill="none" stroke="rgb(var(--nx-teal-ink) / .35)" stroke-width="1.5" stroke-dasharray="3 7" class="oc-ill__orbit"/>
    {{-- chain nodes --}}
    <g class="oc-ill__nodes">
        <circle cx="44" cy="128" r="7" fill="rgb(var(--nx-surface))" stroke="rgb(var(--nx-teal-ink))" stroke-width="2.5"/><circle cx="44" cy="128" r="2.6" fill="rgb(var(--nx-teal-ink))"/>
        <circle cx="318" cy="96" r="7" fill="rgb(var(--nx-surface))" stroke="rgb(var(--nx-gold))" stroke-width="2.5"/><circle cx="318" cy="96" r="2.6" fill="rgb(var(--nx-gold))"/>
        <circle cx="232" cy="176" r="5.5" fill="rgb(var(--nx-surface))" stroke="rgb(var(--nx-teal-ink))" stroke-width="2.5"/>
        <path d="M51 126 C 96 112, 112 112, 132 116" fill="none" stroke="rgb(var(--nx-teal-ink) / .55)" stroke-width="2" stroke-dasharray="4 6" class="oc-ill__flow"/>
        <path d="M311 98 C 280 104, 262 110, 246 116" fill="none" stroke="rgb(var(--nx-gold) / .7)" stroke-width="2" stroke-dasharray="4 6" class="oc-ill__flow"/>
    </g>
    {{-- wallet --}}
    <g filter="url(#{{ $uid }}-sh)">
        <rect x="118" y="82" width="140" height="92" rx="18" fill="url(#{{ $uid }}-w)"/>
        <path d="M118 104 q0 -22 22 -22 h86 q20 0 20 16" fill="rgb(255 255 255 / .12)"/>
        <rect x="128" y="64" width="112" height="36" rx="10" fill="rgb(var(--nx-cta-a))" opacity=".55" transform="rotate(-8 184 82)"/>
        <rect x="206" y="112" width="52" height="34" rx="12" fill="rgb(255 255 255 / .18)"/>
        <circle cx="224" cy="129" r="6" fill="rgb(var(--nx-surface))"/>
        <rect x="132" y="150" width="46" height="6" rx="3" fill="rgb(255 255 255 / .35)"/>
        <rect x="132" y="104" width="34" height="6" rx="3" fill="rgb(255 255 255 / .35)"/>
    </g>
    {{-- coins dropping in --}}
    <g class="oc-ill__coin oc-ill__coin--a" filter="url(#{{ $uid }}-sh)">
        <ellipse cx="116" cy="62" rx="30" ry="9" fill="rgb(var(--oc-usdc) / .55)" transform="translate(0 7)"/>
        <circle cx="116" cy="56" r="26" fill="rgb(var(--oc-usdc))"/><circle cx="116" cy="56" r="20" fill="none" stroke="rgb(255 255 255 / .7)" stroke-width="2"/>
        <path d="M116 42 v28 M122 48 q-4 -4 -9 -3 q-7 1 -7 6 q0 5 8 6 q8 1 8 6 q0 5 -7 6 q-5 0 -9 -3" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
    </g>
    <g class="oc-ill__coin oc-ill__coin--b" filter="url(#{{ $uid }}-sh)">
        <ellipse cx="262" cy="46" rx="26" ry="8" fill="rgb(var(--oc-usdt) / .55)" transform="translate(0 6)"/>
        <circle cx="262" cy="42" r="23" fill="rgb(var(--oc-usdt))"/>
        <path d="M251 33 h22 M262 33 v22" stroke="#fff" stroke-width="4.5" stroke-linecap="round"/><ellipse cx="262" cy="46" rx="11" ry="3.6" fill="none" stroke="#fff" stroke-width="2.4"/>
    </g>
    <g class="oc-ill__coin oc-ill__coin--c" filter="url(#{{ $uid }}-sh)">
        <circle cx="176" cy="28" r="14" fill="url(#{{ $uid }}-g)"/><circle cx="176" cy="28" r="9" fill="none" stroke="rgb(255 255 255 / .6)" stroke-width="1.8"/>
    </g>
    {{-- shield --}}
    <g filter="url(#{{ $uid }}-sh)">
        <path d="M290 150 l26 9 v18 c0 15 -11 25 -26 31 c-15 -6 -26 -16 -26 -31 v-18 z" fill="rgb(var(--nx-surface))" stroke="rgb(var(--nx-ok))" stroke-width="3"/>
        <path d="M279 177 l8 8 l15 -17" fill="none" stroke="rgb(var(--nx-ok))" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
    </g>
    {{-- sparkles --}}
    <g fill="rgb(var(--nx-gold))"><path d="M70 60 l3 8 l8 3 l-8 3 l-3 8 l-3 -8 l-8 -3 l8 -3 z" opacity=".9"/><path d="M330 150 l2 5 l5 2 l-5 2 l-2 5 l-2 -5 l-5 -2 l5 -2 z" opacity=".7"/></g>
</svg>
