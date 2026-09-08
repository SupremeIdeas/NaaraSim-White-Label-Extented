<?php

namespace Database\Seeders;

use App\Models\ThemePreset;
use Illuminate\Database\Seeder;

/**
 * NAARA THEME SYSTEM — Batch 2 §1, palette refresh + expansion (2026-09-04),
 * extended to 40 presets by the color-system skill audit (2026-09-06).
 * Seeds all 40 switchable presets.
 *
 * `naara-official` (row 1) is the permanent built-in: its tokens are TRANSCRIBED
 * from the live app.css `--brand-*` set and it emits NO override CSS (the built-in
 * look already ships), so it is re-seeded authoritatively every run. It is
 * NEVER touched by the refresh below.
 *
 * Rows 2–15 are the ORIGINAL 14 persona slugs, RECOLOURED (owner request: too
 * many of the original palettes read as teal/gold variations of naara-official
 * itself). Each new palette is inspired by the colour-story of one of 15
 * reference mockups the owner forwarded — never their copy/imagery/branding,
 * just the mood of the colours. Rows 16–20 are 5 brand-new personas, added so
 * the platform shipped 20 themes total (see newPresets() below); one is
 * inspired by the one reference image left over after the 14 recolours, the
 * rest are original combinations chosen to stay visually distinct from every
 * other preset. Rows 21–40 are the 40-theme expansion (see phase2Presets()
 * below), each inspired by the color-system skill's curated palette library.
 * Every palette keeps `primary` dark enough for white button text and now
 * also ships its own `accent_dark` (>=4.5:1 on white — see
 * `ThemePresetContrastTest`, which enforces both for every row here).
 * All of it is seeded with `firstOrCreate` (rows 2–40) / `updateOrCreate`
 * (row 1 only) so a re-run NEVER clobbers an admin's own tuning made through
 * the picker — a one-time migration (not this seeder) is what actually
 * updates rows in a database that already seeded an older palette; see
 * `2026_09_04_120000_refresh_theme_preset_palettes.php` and
 * `2026_09_06_180000_add_theme_preset_accent_dark_token.php`.
 *
 * Icons: only `naara-official` keeps the 3D set; every other preset points at the
 * shared `naara-sprite-01` family (the sprite sheet itself ships in Batch 3 §5 —
 * until then icons render via the existing sprite, the flag is just stored data).
 * Structural `layout_variants` stay on `variant-a` here; Batch 2 §2 assigns
 * variant-b/variant-c once those partials exist, so nothing renders half-wired.
 */
class ThemePresetSeeder extends Seeder
{
    /**
     * 5 brand-new personas (rows 16–20), added alongside the palette refresh
     * above so the platform now ships 20 themes total. Each gets its own hero
     * image reused from the existing 8-image set (see
     * docs/build-specs/THEME-PLACEHOLDER-ASSETS.md) and lives on the
     * structural baseline (variant-a everywhere) since no dedicated partials
     * exist for them yet.
     */
    private function newPresets(): array
    {
        $display = 'Supreme Display';

        return [
            ['slug' => 'verdant-pulse', 'name' => 'Verdant Pulse', 'sort_order' => 16,
                'persona' => 'Fresh spring-green, crisp white cards, quick-action urban utility feel.',
                'colors' => ['primary' => '22 140 72', 'primary_dark' => '15 105 54', 'accent' => '163 230 53', 'accent_dark' => '90 127 29', 'navy' => '10 26 16', 'action' => '230 60 45'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-female'],

            ['slug' => 'cobalt-frost', 'name' => 'Cobalt Frost', 'sort_order' => 17,
                'persona' => 'Icy cobalt blue with a sky-blue pop, crisp winter-clean minimalism.',
                'colors' => ['primary' => '30 86 160', 'primary_dark' => '20 60 112', 'accent' => '56 189 248', 'accent_dark' => '36 123 161', 'navy' => '10 20 35', 'action' => '230 60 45'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'portal-gateway'],

            ['slug' => 'mango-burst', 'name' => 'Mango Burst', 'sort_order' => 18,
                'persona' => 'Tropical burnt-orange with a deep plum pop, playful travel energy.',
                'colors' => ['primary' => '198 90 10', 'primary_dark' => '150 68 8', 'accent' => '124 58 140', 'accent_dark' => '124 58 140', 'navy' => '28 14 8', 'action' => '220 70 45'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-male'],

            ['slug' => 'arctic-teal', 'name' => 'Arctic Teal', 'sort_order' => 19,
                'persona' => 'Cool, calm teal with a slate-blue accent — a quieter, icier cousin of the house teal.',
                'colors' => ['primary' => '20 110 120', 'primary_dark' => '14 80 88', 'accent' => '148 163 184', 'accent_dark' => '104 114 129', 'navy' => '15 20 24', 'action' => '225 65 50'],
                'radius' => ['control' => '0.5rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'worldwide'],

            ['slug' => 'rosewood-luxe', 'name' => 'Rosewood Luxe', 'sort_order' => 20,
                'persona' => 'Rosewood-pink with a champagne-gold pop — upscale, boutique, gift-shop warmth.',
                'colors' => ['primary' => '122 36 54', 'primary_dark' => '90 26 40', 'accent' => '230 196 140', 'accent_dark' => '127 108 77', 'navy' => '24 12 16', 'action' => '220 65 48'],
                'radius' => ['control' => '0.875rem', 'card' => '2rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.4'],
                'hero' => 'branded'],
        ];
    }

    public function run(): void
    {
        // Row 1 — the built-in, authoritative every run.
        ThemePreset::updateOrCreate(
            ['slug' => 'naara-official'],
            [
                'name' => 'Naara Official',
                'persona' => 'Current shipped look — deep teal, warm gold, midnight navy, 3D icons. Permanent default.',
                'tokens' => [
                    'colors' => ['primary' => '10 110 110', 'primary_dark' => '8 85 85', 'accent' => '212 160 23', 'accent_dark' => '148 112 16', 'navy' => '13 27 42', 'action' => '232 65 42'],
                    'radius' => ['control' => '0.5rem', 'card' => '1.5rem', 'pill' => '9999px'],
                    'typography' => ['display' => 'Supreme Display', 'sans' => 'Didact Gothic'],
                    'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                ],
                'icon_family' => ['style' => '3d', 'set' => 'default'],
                // Default home hero (owner reference) — the traveller/balloons scene
                // behind "My Connectivity". An admin HeroBackground upload still wins.
                'hero_assets' => ['dashboard' => '/img/themes/balloons.webp'],
                'layout_variants' => $this->baselineVariants(),
                'is_built_in' => true,
                'sort_order' => 1,
            ],
        );

        // The data-forward personas lead with wallet + connectivity state on the
        // dashboard home (variant-b); everyone else keeps the baseline (variant-a).
        // Only pages whose variant partials EXIST are assigned non-baseline values,
        // so nothing renders half-wired.
        $dashboardB = ['fintra-clean', 'genius-grid', 'slate-signal', 'capable-mono'];
        // eSIM variant-b = header/tiles lead, hero below (editorial/minimal personas).
        $esimB = ['paperwhite', 'aries-contrast', 'capable-mono'];
        // Numbers variant-b = the six-card bento leads, hero below (action-first personas).
        $numbersB = ['coral-current', 'origin-bold', 'waitlisty-soft'];

        // Per-theme home hero art (owner request): applying a theme swaps the
        // dashboard hero to its own image. Seeded under public/img/themes/. Eight
        // unique images cover the fourteen personas; the reuse is logged in
        // docs/build-specs/THEME-PLACEHOLDER-ASSETS.md for a later 1:1 swap.
        $hero = fn (string $f) => ['dashboard' => "/img/themes/{$f}.webp"];
        $heroMap = [
            'aurora-shift' => $hero('islands-female'),
            'sunset-transit' => $hero('balloons'),
            'midnight-signal' => $hero('portal-gateway'),
            'paperwhite' => $hero('app-ui-phone'),
            'fintra-clean' => $hero('before-after'),
            'origin-bold' => $hero('branded'),
            'capable-mono' => $hero('app-ui-phone'),
            'waitlisty-soft' => $hero('balloons'),
            'genius-grid' => $hero('before-after'),
            'lander-hero' => $hero('worldwide'),
            'aries-contrast' => $hero('portal-gateway'),
            'emerald-route' => $hero('islands-male'),
            'coral-current' => $hero('islands-female'),
            'slate-signal' => $hero('worldwide'),
        ];

        // Rows 2–15 — original personas. firstOrCreate = never clobber admin tuning.
        foreach ($this->presets() as $preset) {
            $variants = $this->baselineVariants();
            if (in_array($preset['slug'], $dashboardB, true)) {
                $variants['dashboard_home'] = 'variant-b';
            }
            if (in_array($preset['slug'], $esimB, true)) {
                $variants['esim'] = 'variant-b';
            }
            if (in_array($preset['slug'], $numbersB, true)) {
                $variants['numbers'] = 'variant-b';
            }

            ThemePreset::firstOrCreate(['slug' => $preset['slug']], [
                'name' => $preset['name'],
                'persona' => $preset['persona'],
                'tokens' => [
                    'colors' => $preset['colors'],
                    'radius' => $preset['radius'],
                    'typography' => $preset['typography'],
                    'surface' => $preset['surface'],
                ],
                'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
                'hero_assets' => $heroMap[$preset['slug']] ?? [],
                'layout_variants' => $variants,
                'section_styles' => $this->batch1SectionStyles($preset['slug']),
                'is_built_in' => false,
                'sort_order' => $preset['sort_order'],
            ]);
        }

        // Rows 16–20 — the 5 brand-new personas added alongside the palette
        // refresh. Same firstOrCreate discipline: never clobber admin tuning.
        foreach ($this->newPresets() as $preset) {
            ThemePreset::firstOrCreate(['slug' => $preset['slug']], [
                'name' => $preset['name'],
                'persona' => $preset['persona'],
                'tokens' => [
                    'colors' => $preset['colors'],
                    'radius' => $preset['radius'],
                    'typography' => $preset['typography'],
                    'surface' => $preset['surface'],
                ],
                'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
                'hero_assets' => $hero($preset['hero']),
                'layout_variants' => $this->baselineVariants(),
                'is_built_in' => false,
                'sort_order' => $preset['sort_order'],
            ]);
        }

        // Rows 21–40 — the 40-theme expansion (color-system skill audit,
        // 2026-09-06). Same firstOrCreate discipline: never clobber admin
        // tuning. See phase2Presets() for how each palette was chosen and
        // validated.
        foreach ($this->phase2Presets() as $preset) {
            ThemePreset::firstOrCreate(['slug' => $preset['slug']], [
                'name' => $preset['name'],
                'persona' => $preset['persona'],
                'tokens' => [
                    'colors' => $preset['colors'],
                    'radius' => $preset['radius'],
                    'typography' => $preset['typography'],
                    'surface' => $preset['surface'],
                ],
                'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
                'hero_assets' => $hero($preset['hero']),
                'layout_variants' => $this->baselineVariants(),
                'section_styles' => $this->batch1SectionStyles($preset['slug']),
                'is_built_in' => false,
                'sort_order' => $preset['sort_order'],
            ]);
        }
    }

    /**
     * Theme visual rebuild, Batch 1 of 8 (owner request, 2026-09-07): the
     * first 5 presets to get their own unique header + login screen instead
     * of the shared "default" chrome, each pointing at a style key matching
     * its own slug (see ThemePreset::SECTION_STYLE_ALLOW and the partials
     * under resources/views/components/theme-sections/header/ and
     * resources/views/components/layouts/theme-sections/login/). A fresh
     * install gets these from the seeder directly; an existing database
     * upgrading gets the same values from
     * 2026_09_07_110000_assign_theme_batch1_section_styles.php instead —
     * the values must stay identical between the two, exactly like
     * accent_dark above. Kept its original name for a minimal diff even
     * though it now also seeds batch 2's slugs (2026-09-07) — see the
     * batch-2-specific comments inline below.
     */
    private function batch1SectionStyles(string $slug): array
    {
        // login_bg is independent of login STRUCTURE — chosen per persona
        // rather than tied 1:1 to the slug (owner request: "some login bg
        // will have custom unique dot grid... mesh grain on some, Aurora
        // bg"). Paperwhite deliberately gets 'none' — its whole persona is
        // "zero noise," and origin-bold sharing 'dot-grid' with aries-
        // contrast demonstrates a style family reused across two themes.
        // Batch 2 (2026-09-07) extends every match below with 2 brand-new
        // personas (solar-flare, noir-reserve) plus completes the 3 batch-1
        // themes that only had header/login/bottom_nav until now
        // (aries-contrast, paperwhite, origin-bold) — same additive
        // discipline, matching values kept identical with
        // 2026_09_07_163000_assign_theme_batch2_section_styles.php.
        $loginBg = match ($slug) {
            'aries-contrast', 'origin-bold' => 'dot-grid',
            'midnight-signal' => 'mesh-grain',
            'neon-vertex' => 'aurora',
            'paperwhite' => 'none',
            'solar-flare' => 'dot-grid',
            'noir-reserve' => 'mesh-grain',
            // Batch 3 (2026-09-07).
            'aurora-shift' => 'aurora',
            'sunset-transit' => 'mesh-grain',
            'fintra-clean' => 'none',
            'capable-mono' => 'dot-grid',
            'waitlisty-soft' => 'aurora',
            default => null,
        };

        // landing_hero: only themes with a genuinely unique, hand-built
        // landing page get one (owner request, 2026-09-07) — not every
        // persona has one yet, unlike header/login/bottom_nav.
        $landingHero = match ($slug) {
            'neon-vertex', 'midnight-signal', 'aries-contrast', 'paperwhite', 'origin-bold', 'solar-flare', 'noir-reserve',
            'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft' => $slug,
            default => null,
        };

        // The full page suite (owner request, 2026-09-07: "for each theme...
        // homepage, about us page, and 3 extra important page layouts") —
        // every theme with a landing_hero above also carries hand-built
        // about/how-it-works/contact pages; every other theme stays on the
        // shared default content until its own suite is built.
        $fullSuitePage = match ($slug) {
            'neon-vertex', 'midnight-signal', 'aries-contrast', 'paperwhite', 'origin-bold', 'solar-flare', 'noir-reserve',
            'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft' => $slug,
            default => null,
        };

        return match ($slug) {
            'aries-contrast', 'midnight-signal', 'neon-vertex', 'paperwhite', 'origin-bold', 'solar-flare', 'noir-reserve',
            'aurora-shift', 'sunset-transit', 'fintra-clean', 'capable-mono', 'waitlisty-soft' => array_filter([
                'header' => $slug,
                'login' => $slug,
                'bottom_nav' => $slug,
                'login_bg' => $loginBg,
                'landing_hero' => $landingHero,
                'about_page' => $fullSuitePage,
                'how_it_works_page' => $fullSuitePage,
                'contact_page' => $fullSuitePage,
                // Footer (owner request, 2026-09-07): same two themes as the
                // full page suite above — a hand-built footer ships together
                // with the rest of that theme's persona.
                'footer' => $fullSuitePage,
            ], fn ($v) => $v !== null),
            default => [],
        };
    }

    /**
     * The baseline page→variant map. Every page starts on variant-a (the
     * extracted current markup); §2 assigns variant-b per persona only where the
     * partial exists. Pages without a built variant stay on variant-a forever via
     * ThemePreset::layoutVariant()'s default, so this map can stay conservative.
     */
    private function baselineVariants(): array
    {
        return array_fill_keys(
            ['dashboard_home', 'esim', 'numbers', 'my_line', 'account_settings', 'profile', 'menu'],
            'variant-a',
        );
    }

    /**
     * The 14 original persona slugs, RECOLOURED (owner request, 2026-09-04):
     * `name`/`persona`/`colors` are new for every row here; `sort_order`,
     * `radius`, `typography` and `surface` are UNCHANGED from the original
     * seed, so every existing layout-variant assignment, hero-art mapping and
     * font choice stays valid with zero other wiring touched. Colours are
     * channel triples ("R G B"); every `primary` is kept dark enough for
     * white button text, matching the bar the original palette set.
     *
     * @return list<array<string,mixed>>
     */
    private function presets(): array
    {
        $display = 'Supreme Display';

        return [
            ['slug' => 'aurora-shift', 'name' => 'Indigo Current', 'sort_order' => 2,
                'persona' => 'Deep indigo-violet fintech energy with electric-blue highlights on a near-black gradient.',
                'colors' => ['primary' => '59 63 140', 'primary_dark' => '38 41 90', 'accent' => '76 111 255', 'accent_dark' => '72 105 242', 'navy' => '15 16 36', 'action' => '232 65 42'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5']],

            ['slug' => 'sunset-transit', 'name' => 'Boarding Pass', 'sort_order' => 3,
                'persona' => 'Airline-ticket navy with a warm coral pop — departures-board energy for a travel brand.',
                'colors' => ['primary' => '30 58 95', 'primary_dark' => '20 41 66', 'accent' => '242 132 107', 'accent_dark' => '169 92 75', 'navy' => '22 34 58', 'action' => '216 70 48'],
                'radius' => ['control' => '0.5rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5']],

            ['slug' => 'midnight-signal', 'name' => 'Midnight Signal', 'sort_order' => 4,
                'persona' => 'Near-black smart-home dark mode, cyan-teal accents, dark-first design.',
                'colors' => ['primary' => '16 124 132', 'primary_dark' => '11 92 98', 'accent' => '34 211 238', 'accent_dark' => '20 127 143', 'navy' => '16 20 28', 'action' => '244 63 94'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.4']],

            ['slug' => 'paperwhite', 'name' => 'Paperwhite', 'sort_order' => 5,
                'persona' => 'Ultra-light, high-whitespace, ink-black type on paper — minimal, editorial, zero noise.',
                'colors' => ['primary' => '17 18 20', 'primary_dark' => '0 0 0', 'accent' => '139 139 150', 'accent_dark' => '111 111 120', 'navy' => '28 28 31', 'action' => '185 55 40'],
                'radius' => ['control' => '0.375rem', 'card' => '0.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'none', 'card_border_opacity' => '0.8']],

            ['slug' => 'fintra-clean', 'name' => 'Ledger', 'sort_order' => 6,
                'persona' => 'Fintech slate-blue dashboards with a champagne-gold action colour, dense tabular numbers.',
                'colors' => ['primary' => '46 63 99', 'primary_dark' => '32 44 70', 'accent' => '240 169 59', 'accent_dark' => '144 101 35', 'navy' => '23 32 58', 'action' => '225 60 45'],
                'radius' => ['control' => '0.375rem', 'card' => '0.75rem', 'pill' => '0.5rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.7']],

            ['slug' => 'origin-bold', 'name' => 'Origin Bold', 'sort_order' => 7,
                'persona' => 'Vivid construction-orange with graphite accents, oversized type, confident colour blocks.',
                'colors' => ['primary' => '214 88 26', 'primary_dark' => '163 66 16', 'accent' => '23 23 23', 'accent_dark' => '23 23 23', 'navy' => '26 17 10', 'action' => '236 72 53'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5']],

            ['slug' => 'capable-mono', 'name' => 'Capable', 'sort_order' => 8,
                'persona' => 'Near-black monochrome with a single neon-lime accent — restrained by day, electric by night.',
                'colors' => ['primary' => '22 24 26', 'primary_dark' => '0 0 0', 'accent' => '198 255 0', 'accent_dark' => '99 128 0', 'navy' => '10 10 10', 'action' => '220 60 45'],
                'radius' => ['control' => '0.375rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],

            ['slug' => 'waitlisty-soft', 'name' => 'Horizon', 'sort_order' => 9,
                'persona' => 'Soft violet-purple with a magenta-pink pop, rounded-everything, approachable consumer feel.',
                'colors' => ['primary' => '109 63 160', 'primary_dark' => '79 45 120', 'accent' => '232 121 249', 'accent_dark' => '162 85 174', 'navy' => '30 20 45', 'action' => '236 90 70'],
                'radius' => ['control' => '0.875rem', 'card' => '2rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.4']],

            ['slug' => 'genius-grid', 'name' => 'Grid Nine', 'sort_order' => 10,
                'persona' => 'Structured indigo-charcoal grid dashboard with an amber pop, information-dense home.',
                'colors' => ['primary' => '41 37 64', 'primary_dark' => '28 25 46', 'accent' => '250 204 21', 'accent_dark' => '138 112 12', 'navy' => '18 18 24', 'action' => '225 60 45'],
                'radius' => ['control' => '0.5rem', 'card' => '1rem', 'pill' => '0.75rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],

            ['slug' => 'lander-hero', 'name' => 'Skyline', 'sort_order' => 11,
                'persona' => 'Big single-hero marketing energy — deep indigo-slate with a coral call to action.',
                'colors' => ['primary' => '39 50 86', 'primary_dark' => '27 35 62', 'accent' => '242 132 107', 'accent_dark' => '169 92 75', 'navy' => '15 19 28', 'action' => '232 65 42'],
                'radius' => ['control' => '0.625rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5']],

            ['slug' => 'aries-contrast', 'name' => 'Aries', 'sort_order' => 12,
                'persona' => 'High-contrast black, white and gold, sharp corners — live-odds, luxury-travel energy.',
                'colors' => ['primary' => '10 10 10', 'primary_dark' => '0 0 0', 'accent' => '245 197 24', 'accent_dark' => '135 108 13', 'navy' => '8 8 10', 'action' => '200 50 40'],
                'radius' => ['control' => '0.125rem', 'card' => '0.25rem', 'pill' => '0.25rem'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.8']],

            ['slug' => 'emerald-route', 'name' => 'Emerald Route', 'sort_order' => 13,
                'persona' => 'Deep forest green with a warm terracotta accent, spa-fresh route/map motif.',
                'colors' => ['primary' => '31 61 43', 'primary_dark' => '20 42 30', 'accent' => '216 150 61', 'accent_dark' => '151 105 43', 'navy' => '14 26 18', 'action' => '220 70 50'],
                'radius' => ['control' => '0.5rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5']],

            ['slug' => 'coral-current', 'name' => 'Crimson Current', 'sort_order' => 14,
                'persona' => 'Bold crimson-burgundy with a gold pop, energetic sport/campaign feel.',
                'colors' => ['primary' => '140 20 48', 'primary_dark' => '105 14 36', 'accent' => '245 158 11', 'accent_dark' => '159 103 7', 'navy' => '26 10 14', 'action' => '230 110 40'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5']],

            ['slug' => 'slate-signal', 'name' => 'Velvet Reserve', 'sort_order' => 15,
                'persona' => 'Deep wine-maroon with champagne-gold accents — premium, exclusive, after-hours feel.',
                'colors' => ['primary' => '67 20 36', 'primary_dark' => '46 14 25', 'accent' => '224 168 64', 'accent_dark' => '146 109 42', 'navy' => '20 10 14', 'action' => '220 60 45'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],
        ];
    }

    /**
     * Rows 21–40 — the 40-theme expansion (owner request, following the
     * color-system skill installation). Each palette is inspired by one of
     * the skill's curated mood palettes (`04-palette-library.md`) or named
     * production palettes (Cobalt Essence, Lemonade, Starlight, Dusk
     * Navy-Orange, Lavender Ink, Signal Red — "Electric Ledger"/"Signal
     * Grey"/"Dusk Route" here are those last three renamed to avoid
     * colliding with existing personas), plus 3 originals to round out the
     * set. Every `primary` and `accent_dark` was verified with the skill's
     * `contrast_check.py` BEFORE being written here — `primary` clears
     * >=3:1 white-text contrast (matches the existing 20's own bar) and
     * `accent_dark` clears >=4.5:1 on white (see ThemePresetContrastTest,
     * which enforces both for every seeded preset going forward). Dark
     * mode is NOT authored per-theme: every non-default preset's dark mode
     * automatically collapses to the one shared standard palette in
     * ThemePreset::styleCss() — only the light-mode identity below matters.
     * Hero art reuses the existing 8-image set, same as rows 2–20.
     */
    private function phase2Presets(): array
    {
        $display = 'Supreme Display';

        return [
            ['slug' => 'solar-flare', 'name' => 'Solar Flare', 'sort_order' => 21,
                'persona' => 'Vivid amber-orange with a fierce crimson pop on deep navy — sports-broadcast energy and urgency.',
                'colors' => ['primary' => '207 127 11', 'primary_dark' => '149 91 8', 'accent' => '205 24 24', 'accent_dark' => '205 24 24', 'navy' => '10 20 45', 'action' => '220 55 35'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-male'],

            ['slug' => 'frostbite', 'name' => 'Frostbite', 'sort_order' => 22,
                'persona' => 'Cool steel-blue with an icy teal accent on near-black — crisp, glacial, high-trust fintech feel.',
                'colors' => ['primary' => '50 130 184', 'primary_dark' => '36 94 132', 'accent' => '0 144 158', 'accent_dark' => '0 130 142', 'navy' => '5 5 35', 'action' => '225 65 70'],
                'radius' => ['control' => '0.5rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'portal-gateway'],

            ['slug' => 'cocoa-dust', 'name' => 'Cocoa Dust', 'sort_order' => 23,
                'persona' => 'Warm mocha-brown with a burnt-copper pop — artisanal, café-culture warmth.',
                'colors' => ['primary' => '125 90 80', 'primary_dark' => '90 65 58', 'accent' => '197 129 71', 'accent_dark' => '158 103 57', 'navy' => '25 18 16', 'action' => '200 70 50'],
                'radius' => ['control' => '0.875rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5'],
                'hero' => 'before-after'],

            ['slug' => 'neon-vertex', 'name' => 'Neon Vertex', 'sort_order' => 24,
                'persona' => 'Deep ultraviolet with a hot-pink flash on near-black — nightlife, electronic, after-hours energy.',
                'colors' => ['primary' => '61 8 123', 'primary_dark' => '44 6 89', 'accent' => '244 59 134', 'accent_dark' => '207 50 114', 'navy' => '14 5 30', 'action' => '236 72 100'],
                'radius' => ['control' => '0.375rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.4'],
                'hero' => 'app-ui-phone'],

            ['slug' => 'canopy-green', 'name' => 'Canopy', 'sort_order' => 25,
                'persona' => 'Forest green with a warm terracotta pop — outdoor, eco-conscious, grounded travel feel.',
                'colors' => ['primary' => '11 132 87', 'primary_dark' => '8 95 63', 'accent' => '201 125 75', 'accent_dark' => '161 100 60', 'navy' => '10 26 18', 'action' => '210 75 55'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5'],
                'hero' => 'worldwide'],

            ['slug' => 'noir-reserve', 'name' => 'Noir Reserve', 'sort_order' => 26,
                'persona' => 'Espresso-brown with a burnt-sienna accent — quiet, tactile, old-money luxury.',
                'colors' => ['primary' => '92 61 46', 'primary_dark' => '66 44 33', 'accent' => '184 92 56', 'accent_dark' => '184 92 56', 'navy' => '30 24 24', 'action' => '190 60 45'],
                'radius' => ['control' => '0.25rem', 'card' => '0.75rem', 'pill' => '0.5rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.7'],
                'hero' => 'branded'],

            ['slug' => 'communal-teal', 'name' => 'Communal', 'sort_order' => 27,
                'persona' => 'Soft aqua-teal with a coral pop — friendly, social, community-platform warmth.',
                'colors' => ['primary' => '103 153 156', 'primary_dark' => '74 110 112', 'accent' => '232 137 107', 'accent_dark' => '162 96 75', 'navy' => '14 26 26', 'action' => '230 90 70'],
                'radius' => ['control' => '0.875rem', 'card' => '2rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-female'],

            ['slug' => 'blush-editorial', 'name' => 'Blush Editorial', 'sort_order' => 28,
                'persona' => 'Rose-pink with a sky-blue pop on deep indigo — fashion-editorial, expressive, youthful.',
                'colors' => ['primary' => '227 99 135', 'primary_dark' => '163 71 97', 'accent' => '95 168 211', 'accent_dark' => '66 118 148', 'navy' => '28 20 36', 'action' => '225 80 100'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.4'],
                'hero' => 'islands-female'],

            ['slug' => 'heirloom-taupe', 'name' => 'Heirloom', 'sort_order' => 29,
                'persona' => 'Warm taupe with a dusty slate-blue accent — heritage, craft, understated vintage.',
                'colors' => ['primary' => '118 97 97', 'primary_dark' => '85 70 70', 'accent' => '110 146 159', 'accent_dark' => '88 117 127', 'navy' => '22 18 18', 'action' => '200 65 55'],
                'radius' => ['control' => '0.5rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'before-after'],

            ['slug' => 'founding', 'name' => 'Founding', 'sort_order' => 30,
                'persona' => 'Deep forest-teal with a terracotta pop — institutional, established, rooted in place.',
                'colors' => ['primary' => '58 99 81', 'primary_dark' => '42 71 58', 'accent' => '228 130 87', 'accent_dark' => '171 98 65', 'navy' => '20 18 18', 'action' => '205 70 55'],
                'radius' => ['control' => '0.375rem', 'card' => '0.75rem', 'pill' => '0.5rem'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.7'],
                'hero' => 'worldwide'],

            ['slug' => 'afterdark-plum', 'name' => 'Afterdark', 'sort_order' => 31,
                'persona' => 'Deep plum with a crimson flash on near-black — bold editorial, gaming, nightlife energy.',
                'colors' => ['primary' => '49 29 63', 'primary_dark' => '35 21 45', 'accent' => '202 62 71', 'accent_dark' => '202 62 71', 'navy' => '14 10 18', 'action' => '220 55 60'],
                'radius' => ['control' => '0.25rem', 'card' => '0.5rem', 'pill' => '0.25rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.7'],
                'hero' => 'app-ui-phone'],

            ['slug' => 'cobalt-essence', 'name' => 'Cobalt Essence', 'sort_order' => 32,
                'persona' => 'Electric cobalt blue with a warm amber pop — modern SaaS dashboard energy.',
                'colors' => ['primary' => '61 126 252', 'primary_dark' => '44 91 181', 'accent' => '251 191 36', 'accent_dark' => '138 105 20', 'navy' => '24 25 35', 'action' => '230 65 55'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'portal-gateway'],

            ['slug' => 'electric-ledger', 'name' => 'Electric Ledger', 'sort_order' => 33,
                'persona' => 'Deep navy with an electric-blue primary and warm amber highlight — fintech-wallet precision.',
                'colors' => ['primary' => '36 106 243', 'primary_dark' => '26 76 175', 'accent' => '254 201 71', 'accent_dark' => '140 111 39', 'navy' => '10 25 48', 'action' => '225 60 60'],
                'radius' => ['control' => '0.375rem', 'card' => '0.75rem', 'pill' => '0.5rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.7'],
                'hero' => 'before-after'],

            ['slug' => 'starlight-violet', 'name' => 'Starlight', 'sort_order' => 34,
                'persona' => 'Deep indigo-violet with a champagne-gold pop — premium, creative, after-hours glamour.',
                'colors' => ['primary' => '59 51 134', 'primary_dark' => '42 37 96', 'accent' => '201 162 39', 'accent_dark' => '141 113 27', 'navy' => '25 20 32', 'action' => '220 65 70'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.4'],
                'hero' => 'branded'],

            ['slug' => 'dusk-route', 'name' => 'Dusk Route', 'sort_order' => 35,
                'persona' => 'Steel-blue with a warm burnt-orange pop — cinematic dusk-to-dawn travel mood.',
                'colors' => ['primary' => '84 119 146', 'primary_dark' => '60 86 105', 'accent' => '217 125 61', 'accent_dark' => '163 94 46', 'navy' => '17 26 36', 'action' => '215 70 50'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-male'],

            ['slug' => 'lavender-ink', 'name' => 'Lavender Ink', 'sort_order' => 36,
                'persona' => 'Soft lavender-violet with a rose-pink pop on deep indigo — playful-premium hybrid.',
                'colors' => ['primary' => '165 121 242', 'primary_dark' => '119 87 174', 'accent' => '224 168 216', 'accent_dark' => '134 101 130', 'navy' => '10 10 36', 'action' => '225 80 130'],
                'radius' => ['control' => '0.875rem', 'card' => '2rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.4'],
                'hero' => 'islands-female'],

            ['slug' => 'signal-grey', 'name' => 'Signal Grey', 'sort_order' => 37,
                'persona' => 'Cool graphite-grey with a vivid signal-red accent — high-alert, bold editorial focus.',
                'colors' => ['primary' => '131 134 143', 'primary_dark' => '94 96 103', 'accent' => '255 19 19', 'accent_dark' => '230 17 17', 'navy' => '5 5 8', 'action' => '220 40 40'],
                'radius' => ['control' => '0.125rem', 'card' => '0.25rem', 'pill' => '0.25rem'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.8'],
                'hero' => 'app-ui-phone'],

            ['slug' => 'copper-line', 'name' => 'Copper Line', 'sort_order' => 38,
                'persona' => 'Burnt copper with a steel-blue pop — industrial-craft, workshop-warm precision.',
                'colors' => ['primary' => '181 101 29', 'primary_dark' => '130 73 21', 'accent' => '47 102 144', 'accent_dark' => '47 102 144', 'navy' => '30 20 15', 'action' => '200 75 50'],
                'radius' => ['control' => '0.375rem', 'card' => '0.75rem', 'pill' => '0.5rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'before-after'],

            ['slug' => 'aurora-borealis', 'name' => 'Aurora Borealis', 'sort_order' => 39,
                'persona' => 'Deep teal with a violet flash — northern-lights inspired, cool and otherworldly.',
                'colors' => ['primary' => '31 111 120', 'primary_dark' => '22 80 86', 'accent' => '139 92 246', 'accent_dark' => '132 87 234', 'navy' => '10 25 28', 'action' => '220 65 90'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5'],
                'hero' => 'worldwide'],

            ['slug' => 'sandstone-route', 'name' => 'Sandstone Route', 'sort_order' => 40,
                'persona' => 'Warm sandstone-brown with an olive-green pop — desert-route, earthy overland travel feel.',
                'colors' => ['primary' => '166 124 82', 'primary_dark' => '120 89 59', 'accent' => '62 124 89', 'accent_dark' => '62 124 89', 'navy' => '24 20 14', 'action' => '205 80 55'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-male'],
        ];
    }
}
