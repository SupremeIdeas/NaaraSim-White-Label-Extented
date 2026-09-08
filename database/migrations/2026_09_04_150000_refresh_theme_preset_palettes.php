<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NAARA THEME SYSTEM — palette refresh + expansion (owner request, 2026-09-04):
 * too many of the 14 original persona palettes read as teal/gold variations of
 * `naara-official` itself. This is a one-time DATA migration, not a rerun of
 * `ThemePresetSeeder` — the seeder's `firstOrCreate` (by design, so an admin's
 * own tuning through the picker is never clobbered) would silently no-op on
 * every one of these 14 already-seeded rows, so the new palette would only
 * ever reach a brand-new install. This migration is what actually updates a
 * database that already ran the old seed.
 *
 * `naara-official` is never touched. Each of the 14 recoloured rows is only
 * updated when its `tokens->colors->primary` still matches the OLD shipped
 * default — the same "never clobber admin tuning" discipline the seeder
 * itself follows, just applied here as a one-time UPDATE instead of an
 * INSERT-only firstOrCreate. The 5 brand-new rows are inserted only if their
 * slug doesn't already exist (mirrors firstOrCreate for a fresh install that
 * runs migrations before ever calling the seeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->recolours() as $slug => $new) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue; // never seeded (e.g. a fresh install that hasn't seeded yet)
            }

            $tokens = json_decode((string) $row->tokens, true) ?: [];
            if (($tokens['colors']['primary'] ?? null) !== $new['old_primary']) {
                continue; // admin already tuned this preset — never overwrite real edits
            }

            $tokens['colors'] = $new['colors'];
            DB::table('theme_presets')->where('slug', $slug)->update([
                'name' => $new['name'],
                'persona' => $new['persona'],
                'tokens' => json_encode($tokens),
                'updated_at' => now(),
            ]);
        }

        foreach ($this->newPresets() as $preset) {
            $exists = DB::table('theme_presets')->where('slug', $preset['slug'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('theme_presets')->insert([
                'slug' => $preset['slug'],
                'name' => $preset['name'],
                'persona' => $preset['persona'],
                'tokens' => json_encode([
                    'colors' => $preset['colors'],
                    'radius' => $preset['radius'],
                    'typography' => $preset['typography'],
                    'surface' => $preset['surface'],
                ]),
                'icon_family' => json_encode(['style' => 'sprite', 'set' => 'naara-sprite-01']),
                'hero_assets' => json_encode(['dashboard' => "/img/themes/{$preset['hero']}.webp"]),
                'layout_variants' => json_encode(array_fill_keys(
                    ['dashboard_home', 'esim', 'numbers', 'my_line', 'account_settings', 'profile', 'menu'],
                    'variant-a',
                )),
                'is_built_in' => false,
                'sort_order' => $preset['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->recolours() as $slug => $new) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            $tokens = json_decode((string) $row->tokens, true) ?: [];
            if (($tokens['colors']['primary'] ?? null) !== $new['colors']['primary']) {
                continue; // admin re-tuned it since — don't stomp that on rollback either
            }

            $tokens['colors'] = $new['old_colors'];
            DB::table('theme_presets')->where('slug', $slug)->update([
                'name' => $new['old_name'],
                'persona' => $new['old_persona'],
                'tokens' => json_encode($tokens),
                'updated_at' => now(),
            ]);
        }

        DB::table('theme_presets')->whereIn('slug', array_column($this->newPresets(), 'slug'))->delete();
    }

    /**
     * The 14 recoloured rows: old → new name/persona/colors, keyed by slug.
     * `old_primary` is the guard (see up()'s doc block); `old_colors` /
     * `old_name` / `old_persona` are what down() restores.
     */
    private function recolours(): array
    {
        return [
            'aurora-shift' => [
                'old_primary' => '30 110 140',
                'old_colors' => ['primary' => '30 110 140', 'primary_dark' => '20 78 100', 'accent' => '129 140 248', 'navy' => '11 22 40', 'action' => '232 65 42'],
                'old_name' => 'Aurora Shift', 'old_persona' => 'Cooler teal-to-indigo gradient hero bands, glassmorphic wallet card.',
                'name' => 'Indigo Current', 'persona' => 'Deep indigo-violet fintech energy with electric-blue highlights on a near-black gradient.',
                'colors' => ['primary' => '59 63 140', 'primary_dark' => '38 41 90', 'accent' => '76 111 255', 'navy' => '15 16 36', 'action' => '232 65 42'],
            ],
            'sunset-transit' => [
                'old_primary' => '180 74 48',
                'old_colors' => ['primary' => '180 74 48', 'primary_dark' => '140 55 35', 'accent' => '226 160 60', 'navy' => '33 20 18', 'action' => '216 70 48'],
                'old_name' => 'Sunset Transit', 'old_persona' => 'Warm coral/gold-forward, travel-photography heroes.',
                'name' => 'Boarding Pass', 'persona' => 'Airline-ticket navy with a warm coral pop — departures-board energy for a travel brand.',
                'colors' => ['primary' => '30 58 95', 'primary_dark' => '20 41 66', 'accent' => '242 132 107', 'navy' => '22 34 58', 'action' => '216 70 48'],
            ],
            'midnight-signal' => [
                'old_primary' => '13 148 136',
                'old_colors' => ['primary' => '13 148 136', 'primary_dark' => '10 110 100', 'accent' => '45 212 191', 'navy' => '6 11 18', 'action' => '244 63 94'],
                'old_name' => 'Midnight Signal', 'old_persona' => 'Near-black navy surfaces, neon-teal accents, dark-first design.',
                'name' => 'Midnight Signal', 'persona' => 'Near-black smart-home dark mode, cyan-teal accents, dark-first design.',
                'colors' => ['primary' => '16 124 132', 'primary_dark' => '11 92 98', 'accent' => '34 211 238', 'navy' => '16 20 28', 'action' => '244 63 94'],
            ],
            'paperwhite' => [
                'old_primary' => '23 37 84',
                'old_colors' => ['primary' => '23 37 84', 'primary_dark' => '15 23 42', 'accent' => '120 113 108', 'navy' => '30 41 59', 'action' => '185 55 40'],
                'old_name' => 'Paperwhite', 'old_persona' => 'Ultra-light, high-whitespace, minimal borders, editorial typography.',
                'name' => 'Paperwhite', 'persona' => 'Ultra-light, high-whitespace, ink-black type on paper — minimal, editorial, zero noise.',
                'colors' => ['primary' => '17 18 20', 'primary_dark' => '0 0 0', 'accent' => '139 139 150', 'navy' => '28 28 31', 'action' => '185 55 40'],
            ],
            'fintra-clean' => [
                'old_primary' => '22 78 99',
                'old_colors' => ['primary' => '22 78 99', 'primary_dark' => '12 55 70', 'accent' => '16 185 129', 'navy' => '15 23 42', 'action' => '225 60 45'],
                'old_name' => 'Ledger', 'old_persona' => 'Fintech-inspired dense data cards, tabular wallet numbers, crisp rules.',
                'name' => 'Ledger', 'persona' => 'Fintech slate-blue dashboards with a champagne-gold action colour, dense tabular numbers.',
                'colors' => ['primary' => '46 63 99', 'primary_dark' => '32 44 70', 'accent' => '240 169 59', 'navy' => '23 32 58', 'action' => '225 60 45'],
            ],
            'origin-bold' => [
                'old_primary' => '79 70 229',
                'old_colors' => ['primary' => '79 70 229', 'primary_dark' => '55 48 163', 'accent' => '245 158 11', 'navy' => '17 24 39', 'action' => '236 72 53'],
                'old_name' => 'Origin Bold', 'old_persona' => 'Oversized display type, big rounded pill CTAs, confident colour blocks.',
                'name' => 'Origin Bold', 'persona' => 'Vivid construction-orange with graphite accents, oversized type, confident colour blocks.',
                'colors' => ['primary' => '214 88 26', 'primary_dark' => '163 66 16', 'accent' => '23 23 23', 'navy' => '26 17 10', 'action' => '236 72 53'],
            ],
            'capable-mono' => [
                'old_primary' => '15 118 110',
                'old_colors' => ['primary' => '15 118 110', 'primary_dark' => '10 85 80', 'accent' => '100 116 139', 'navy' => '17 24 39', 'action' => '220 60 45'],
                'old_name' => 'Capable', 'old_persona' => 'Near-monochrome + single teal accent, restrained, enterprise-feel.',
                'name' => 'Capable', 'persona' => 'Near-black monochrome with a single neon-lime accent — restrained by day, electric by night.',
                'colors' => ['primary' => '22 24 26', 'primary_dark' => '0 0 0', 'accent' => '198 255 0', 'navy' => '10 10 10', 'action' => '220 60 45'],
            ],
            'waitlisty-soft' => [
                'old_primary' => '91 78 220',
                'old_colors' => ['primary' => '91 78 220', 'primary_dark' => '67 56 202', 'accent' => '244 114 182', 'navy' => '30 27 75', 'action' => '236 90 70'],
                'old_name' => 'Horizon', 'old_persona' => 'Soft pastel gradients, rounded-everything, approachable/consumer.',
                'name' => 'Horizon', 'persona' => 'Soft violet-purple with a magenta-pink pop, rounded-everything, approachable consumer feel.',
                'colors' => ['primary' => '109 63 160', 'primary_dark' => '79 45 120', 'accent' => '232 121 249', 'navy' => '30 20 45', 'action' => '236 90 70'],
            ],
            'genius-grid' => [
                'old_primary' => '13 148 136',
                'old_colors' => ['primary' => '13 148 136', 'primary_dark' => '15 118 110', 'accent' => '234 179 8', 'navy' => '17 24 39', 'action' => '225 60 45'],
                'old_name' => 'Grid Nine', 'old_persona' => 'Structured grid dashboard, card-heavy, information-dense home.',
                'name' => 'Grid Nine', 'persona' => 'Structured indigo-charcoal grid dashboard with an amber pop, information-dense home.',
                'colors' => ['primary' => '41 37 64', 'primary_dark' => '28 25 46', 'accent' => '250 204 21', 'navy' => '18 18 24', 'action' => '225 60 45'],
            ],
            'lander-hero' => [
                'old_primary' => '37 99 235',
                'old_colors' => ['primary' => '37 99 235', 'primary_dark' => '29 78 216', 'accent' => '14 165 233', 'navy' => '15 23 42', 'action' => '232 65 42'],
                'old_name' => 'Skyline', 'old_persona' => 'Big single-hero-first marketing pages, dashboard mirrors that scale.',
                'name' => 'Skyline', 'persona' => 'Big single-hero marketing energy — deep indigo-slate with a coral call to action.',
                'colors' => ['primary' => '39 50 86', 'primary_dark' => '27 35 62', 'accent' => '242 132 107', 'navy' => '15 19 28', 'action' => '232 65 42'],
            ],
            'aries-contrast' => [
                'old_primary' => '17 24 39',
                'old_colors' => ['primary' => '17 24 39', 'primary_dark' => '0 0 0', 'accent' => '212 160 23', 'navy' => '10 10 12', 'action' => '200 50 40'],
                'old_name' => 'Aries', 'old_persona' => 'High-contrast black/white/gold, sharp corners, luxury-travel feel.',
                'name' => 'Aries', 'persona' => 'High-contrast black, white and gold, sharp corners — live-odds, luxury-travel energy.',
                'colors' => ['primary' => '10 10 10', 'primary_dark' => '0 0 0', 'accent' => '245 197 24', 'navy' => '8 8 10', 'action' => '200 50 40'],
            ],
            'emerald-route' => [
                'old_primary' => '16 122 87',
                'old_colors' => ['primary' => '16 122 87', 'primary_dark' => '12 90 64', 'accent' => '205 170 120', 'navy' => '12 30 24', 'action' => '220 70 50'],
                'old_name' => 'Emerald Route', 'old_persona' => 'Deep emerald + sand palette, map/route motif throughout.',
                'name' => 'Emerald Route', 'persona' => 'Deep forest green with a warm terracotta accent, spa-fresh route/map motif.',
                'colors' => ['primary' => '31 61 43', 'primary_dark' => '20 42 30', 'accent' => '216 150 61', 'navy' => '14 26 18', 'action' => '220 70 50'],
            ],
            'coral-current' => [
                'old_primary' => '200 60 45',
                'old_colors' => ['primary' => '200 60 45', 'primary_dark' => '160 45 34', 'accent' => '245 158 11', 'navy' => '28 16 14', 'action' => '200 60 45'],
                'old_name' => 'Coral Current', 'old_persona' => 'Coral/action-red forward, energetic, youth-travel positioning.',
                'name' => 'Crimson Current', 'persona' => 'Bold crimson-burgundy with a gold pop, energetic sport/campaign feel.',
                'colors' => ['primary' => '140 20 48', 'primary_dark' => '105 14 36', 'accent' => '245 158 11', 'navy' => '26 10 14', 'action' => '230 110 40'],
            ],
            'slate-signal' => [
                'old_primary' => '51 65 85',
                'old_colors' => ['primary' => '51 65 85', 'primary_dark' => '30 41 59', 'accent' => '20 184 166', 'navy' => '15 23 42', 'action' => '220 60 45'],
                'old_name' => 'Slate Signal', 'old_persona' => 'Cool slate greys + single teal pop, quiet/professional.',
                'name' => 'Velvet Reserve', 'persona' => 'Deep wine-maroon with champagne-gold accents — premium, exclusive, after-hours feel.',
                'colors' => ['primary' => '67 20 36', 'primary_dark' => '46 14 25', 'accent' => '224 168 64', 'navy' => '20 10 14', 'action' => '220 60 45'],
            ],
        ];
    }

    /** The 5 brand-new rows (mirrors ThemePresetSeeder::newPresets()). */
    private function newPresets(): array
    {
        $display = 'Supreme Display';

        return [
            ['slug' => 'verdant-pulse', 'name' => 'Verdant Pulse', 'sort_order' => 16,
                'persona' => 'Fresh spring-green, crisp white cards, quick-action urban utility feel.',
                'colors' => ['primary' => '22 140 72', 'primary_dark' => '15 105 54', 'accent' => '163 230 53', 'navy' => '10 26 16', 'action' => '230 60 45'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-female'],

            ['slug' => 'cobalt-frost', 'name' => 'Cobalt Frost', 'sort_order' => 17,
                'persona' => 'Icy cobalt blue with a sky-blue pop, crisp winter-clean minimalism.',
                'colors' => ['primary' => '30 86 160', 'primary_dark' => '20 60 112', 'accent' => '56 189 248', 'navy' => '10 20 35', 'action' => '230 60 45'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'portal-gateway'],

            ['slug' => 'mango-burst', 'name' => 'Mango Burst', 'sort_order' => 18,
                'persona' => 'Tropical burnt-orange with a deep plum pop, playful travel energy.',
                'colors' => ['primary' => '198 90 10', 'primary_dark' => '150 68 8', 'accent' => '124 58 140', 'navy' => '28 14 8', 'action' => '220 70 45'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-male'],

            ['slug' => 'arctic-teal', 'name' => 'Arctic Teal', 'sort_order' => 19,
                'persona' => 'Cool, calm teal with a slate-blue accent — a quieter, icier cousin of the house teal.',
                'colors' => ['primary' => '20 110 120', 'primary_dark' => '14 80 88', 'accent' => '148 163 184', 'navy' => '15 20 24', 'action' => '225 65 50'],
                'radius' => ['control' => '0.5rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'worldwide'],

            ['slug' => 'rosewood-luxe', 'name' => 'Rosewood Luxe', 'sort_order' => 20,
                'persona' => 'Rosewood-pink with a champagne-gold pop — upscale, boutique, gift-shop warmth.',
                'colors' => ['primary' => '122 36 54', 'primary_dark' => '90 26 40', 'accent' => '230 196 140', 'navy' => '24 12 16', 'action' => '220 65 48'],
                'radius' => ['control' => '0.875rem', 'card' => '2rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.4'],
                'hero' => 'branded'],
        ];
    }
};
