<?php

/**
 * Icon packs: the menu icon art is ONE set of duotone SVG shapes (public/images/icon-packs); a pack is only its two colours,
 * `ic` (the line and fill colour) and `ac` (the accent). The resolver recolours the one set on the fly (route icon-pack.svg), so 40 packs cost
 * a few hundred bytes of data instead of 40 copies of hundreds of files. Pack slugs equal the theme preset slugs, so choosing a theme can bring its pack.
 * Source: the owner's Naara Icons bundles (39 packs), measured: every pack differs from the others by these two colours alone.
 */
return [
    'default' => 'orchid-lime',
    // Where the canonical (uncoloured-by-pack) SVG set lives, per group, relative to public/. A white-label install lists only the groups it ships.
    'dirs' => [
        'more' => 'images/icon-packs/more/svg',
        'admin' => 'images/icon-packs/admin/svg',
    ],
    'packs' => [
        // The install's own default colourway (its brand), not a copy of the Naara one.
        'orchid-lime' => ['label' => 'Orchid Lime (default)', 'ic' => '#A21CAF', 'ac' => '#84CC16'],
        'afterdark-plum' => ['label' => 'Afterdark Plum', 'ic' => '#311D3F', 'ac' => '#CA3E47'],
        'arctic-teal' => ['label' => 'Arctic Teal', 'ic' => '#146E78', 'ac' => '#94A3B8'],
        'aries-contrast' => ['label' => 'Aries Contrast', 'ic' => '#0A0A0A', 'ac' => '#F5C518'],
        'aurora-borealis' => ['label' => 'Aurora Borealis', 'ic' => '#1F6F78', 'ac' => '#8B5CF6'],
        'aurora-shift' => ['label' => 'Aurora Shift', 'ic' => '#3B3F8C', 'ac' => '#4C6FFF'],
        'blush-editorial' => ['label' => 'Blush Editorial', 'ic' => '#E36387', 'ac' => '#5FA8D3'],
        'canopy-green' => ['label' => 'Canopy Green', 'ic' => '#0B8457', 'ac' => '#C97D4B'],
        'capable-mono' => ['label' => 'Capable Mono', 'ic' => '#16181A', 'ac' => '#C6FF00'],
        'cobalt-essence' => ['label' => 'Cobalt Essence', 'ic' => '#3D7EFC', 'ac' => '#FBBF24'],
        'cobalt-frost' => ['label' => 'Cobalt Frost', 'ic' => '#1E56A0', 'ac' => '#38BDF8'],
        'cocoa-dust' => ['label' => 'Cocoa Dust', 'ic' => '#7D5A50', 'ac' => '#C58147'],
        'communal-teal' => ['label' => 'Communal Teal', 'ic' => '#67999C', 'ac' => '#E8896B'],
        'copper-line' => ['label' => 'Copper Line', 'ic' => '#B5651D', 'ac' => '#2F6690'],
        'coral-current' => ['label' => 'Coral Current', 'ic' => '#8C1430', 'ac' => '#F59E0B'],
        'dusk-route' => ['label' => 'Dusk Route', 'ic' => '#547792', 'ac' => '#D97D3D'],
        'electric-ledger' => ['label' => 'Electric Ledger', 'ic' => '#246AF3', 'ac' => '#FEC947'],
        'emerald-route' => ['label' => 'Emerald Route', 'ic' => '#1F3D2B', 'ac' => '#D8963D'],
        'fintra-clean' => ['label' => 'Fintra Clean', 'ic' => '#2E3F63', 'ac' => '#F0A93B'],
        'founding' => ['label' => 'Founding', 'ic' => '#3A6351', 'ac' => '#E48257'],
        'frostbite' => ['label' => 'Frostbite', 'ic' => '#3282B8', 'ac' => '#00909E'],
        'genius-grid' => ['label' => 'Genius Grid', 'ic' => '#292540', 'ac' => '#FACC15'],
        'heirloom-taupe' => ['label' => 'Heirloom Taupe', 'ic' => '#766161', 'ac' => '#6E929F'],
        'lander-hero' => ['label' => 'Lander Hero', 'ic' => '#273256', 'ac' => '#F2846B'],
        'lavender-ink' => ['label' => 'Lavender Ink', 'ic' => '#A579F2', 'ac' => '#E0A8D8'],
        'mango-burst' => ['label' => 'Mango Burst', 'ic' => '#C65A0A', 'ac' => '#7C3A8C'],
        'midnight-signal' => ['label' => 'Midnight Signal', 'ic' => '#107C84', 'ac' => '#22D3EE'],
        'neon-vertex' => ['label' => 'Neon Vertex', 'ic' => '#3D087B', 'ac' => '#F43B86'],
        'noir-reserve' => ['label' => 'Noir Reserve', 'ic' => '#5C3D2E', 'ac' => '#B85C38'],
        'origin-bold' => ['label' => 'Origin Bold', 'ic' => '#D6581A', 'ac' => '#171717'],
        'paperwhite' => ['label' => 'Paperwhite', 'ic' => '#111214', 'ac' => '#8B8B96'],
        'rosewood-luxe' => ['label' => 'Rosewood Luxe', 'ic' => '#7A2436', 'ac' => '#E6C48C'],
        'sandstone-route' => ['label' => 'Sandstone Route', 'ic' => '#A67C52', 'ac' => '#3E7C59'],
        'signal-grey' => ['label' => 'Signal Grey', 'ic' => '#83868F', 'ac' => '#FF1313'],
        'slate-signal' => ['label' => 'Slate Signal', 'ic' => '#431424', 'ac' => '#E0A840'],
        'solar-flare' => ['label' => 'Solar Flare', 'ic' => '#CF7F0B', 'ac' => '#CD1818'],
        'starlight-violet' => ['label' => 'Starlight Violet', 'ic' => '#3B3386', 'ac' => '#C9A227'],
        'sunset-transit' => ['label' => 'Sunset Transit', 'ic' => '#1E3A5F', 'ac' => '#F2846B'],
        'verdant-pulse' => ['label' => 'Verdant Pulse', 'ic' => '#168C48', 'ac' => '#A3E635'],
        'waitlisty-soft' => ['label' => 'Waitlisty Soft', 'ic' => '#6D3FA0', 'ac' => '#E879F9'],
    ],
];
