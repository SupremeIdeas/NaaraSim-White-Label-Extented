<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * color-system skill audit (2026-09-06): `text-accent-dark` was already
 * referenced in a few blade files (e.g. the sidebar's "Soon" badge) but
 * `--brand-accent-dark` never existed as a real CSS variable or Tailwind
 * color, so those classes silently did nothing. Measuring every preset's
 * raw `--brand-accent` against white/tinted-light surfaces with
 * `contrast_check.py` showed the gap was masking a real, widespread WCAG
 * failure: `text-accent` used directly on a light surface (marketing
 * "eyebrow" labels, badges, star ratings) sits at ~1.1–3.5:1 contrast on
 * 18 of the 20 shipped presets — including naara-official's own warm
 * gold (2.38:1) — well under the 4.5:1 text / 3:1 UI-component floor.
 *
 * This migration backfills a proper `accent_dark` value (the same hue,
 * mixed toward black until it clears 4.5:1 on white — see
 * ThemePresetSeeder::presets()/newPresets() for the identical values a
 * fresh install seeds) into every existing preset's `tokens.colors`,
 * purely ADDITIVE — the key has never existed before, so there is no
 * admin tuning to clobber. Only fills the gap when the key is absent, so
 * re-running this after an admin sets their own value (once the admin UI
 * exposes it) is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->accentDark() as $slug => $dark) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue; // never seeded on this install
            }

            $tokens = json_decode((string) $row->tokens, true) ?: [];
            if (array_key_exists('accent_dark', $tokens['colors'] ?? [])) {
                continue; // already has one — never overwrite
            }

            $tokens['colors']['accent_dark'] = $dark;
            DB::table('theme_presets')->where('slug', $slug)->update([
                'tokens' => json_encode($tokens),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->accentDark()) as $slug) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            $tokens = json_decode((string) $row->tokens, true) ?: [];
            unset($tokens['colors']['accent_dark']);
            DB::table('theme_presets')->where('slug', $slug)->update([
                'tokens' => json_encode($tokens),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Every shipped preset's accent, mixed toward black until it clears
     * 4.5:1 against white (verified with contrast_check.py) — identical
     * values to ThemePresetSeeder, so a fresh install and an upgraded one
     * end up with the same tokens either way.
     */
    private function accentDark(): array
    {
        return [
            'naara-official' => '148 112 16',
            'verdant-pulse' => '90 127 29',
            'cobalt-frost' => '36 123 161',
            'mango-burst' => '124 58 140',
            'arctic-teal' => '104 114 129',
            'rosewood-luxe' => '127 108 77',
            'aurora-shift' => '72 105 242',
            'sunset-transit' => '169 92 75',
            'midnight-signal' => '20 127 143',
            'paperwhite' => '111 111 120',
            'fintra-clean' => '144 101 35',
            'origin-bold' => '23 23 23',
            'capable-mono' => '99 128 0',
            'waitlisty-soft' => '162 85 174',
            'genius-grid' => '138 112 12',
            'lander-hero' => '169 92 75',
            'aries-contrast' => '135 108 13',
            'emerald-route' => '151 105 43',
            'coral-current' => '159 103 7',
            'slate-signal' => '146 109 42',
        ];
    }
};
