{{-- Simple top-up art: a phone, signal arcs and a refill chip. Skin tokens only, no motion beyond a soft pulse. --}}
<svg viewBox="0 0 320 260" class="ns-tp__svg" role="img" aria-label="A phone receiving a top-up" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="tpg" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="rgb(var(--nx-teal))" stop-opacity=".95"/>
            <stop offset="1" stop-color="rgb(var(--nx-teal))" stop-opacity=".7"/>
        </linearGradient>
    </defs>
    <circle cx="160" cy="130" r="112" fill="rgb(var(--nx-teal))" opacity=".08"/>
    <circle cx="160" cy="130" r="78" fill="rgb(var(--nx-teal))" opacity=".1"/>
    <g fill="none" stroke="rgb(var(--nx-teal))" stroke-width="5" stroke-linecap="round" opacity=".55">
        <path d="M226 74a60 60 0 0 1 0 112"/>
        <path d="M244 52a88 88 0 0 1 0 156" opacity=".6"/>
        <path d="M94 74a60 60 0 0 0 0 112"/>
        <path d="M76 52a88 88 0 0 0 0 156" opacity=".6"/>
    </g>
    <rect x="118" y="48" width="84" height="164" rx="18" fill="rgb(var(--nx-surface))" stroke="rgb(var(--nx-line-strong))" stroke-width="3"/>
    <rect x="128" y="64" width="64" height="118" rx="10" fill="url(#tpg)"/>
    <rect x="148" y="55" width="24" height="4" rx="2" fill="rgb(var(--nx-line-strong))"/>
    <g fill="#fff">
        <rect x="140" y="132" width="6" height="12" rx="2" opacity=".6"/>
        <rect x="150" y="124" width="6" height="20" rx="2" opacity=".75"/>
        <rect x="160" y="114" width="6" height="30" rx="2" opacity=".9"/>
        <rect x="170" y="104" width="6" height="40" rx="2"/>
    </g>
    <text x="160" y="94" text-anchor="middle" font-size="12" font-weight="800" fill="#fff" font-family="system-ui, sans-serif">+ Top up</text>
    <circle cx="160" cy="198" r="6" fill="none" stroke="rgb(var(--nx-line-strong))" stroke-width="2.5"/>
    <g class="ns-tp__chip">
        <rect x="206" y="150" width="76" height="34" rx="17" fill="rgb(var(--nx-text))"/>
        <text x="244" y="172" text-anchor="middle" font-size="14" font-weight="800" fill="rgb(var(--nx-surface))" font-family="system-ui, sans-serif">Data</text>
    </g>
    <g class="ns-tp__chip ns-tp__chip--b">
        <rect x="38" y="84" width="84" height="34" rx="17" fill="rgb(var(--nx-teal))"/>
        <text x="80" y="106" text-anchor="middle" font-size="14" font-weight="800" fill="#fff" font-family="system-ui, sans-serif">Airtime</text>
    </g>
</svg>
