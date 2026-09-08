<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-theme custom landing pages (owner request, 2026-09-07): the first two
 * genuinely unique, hand-built landing layouts — neon-vertex (mimicking a
 * SaaS-dashboard-hero reference) and midnight-signal (mimicking an
 * AI-travel-product hero) — get their landing_hero section style assigned.
 * Additive only, same discipline as every other batch-1 backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->assignments() as $slug => $style) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            $current = json_decode((string) $row->section_styles, true) ?: [];
            if (! array_key_exists('landing_hero', $current)) {
                $current['landing_hero'] = $style;
            }

            DB::table('theme_presets')->where('slug', $slug)->update([
                'section_styles' => json_encode($current),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->assignments() as $slug => $style) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            $current = json_decode((string) $row->section_styles, true) ?: [];
            if (($current['landing_hero'] ?? null) === $style) {
                unset($current['landing_hero']);
            }

            DB::table('theme_presets')->where('slug', $slug)->update([
                'section_styles' => $current === [] ? null : json_encode($current),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array<string, string> */
    private function assignments(): array
    {
        return [
            'neon-vertex' => 'neon-vertex',
            'midnight-signal' => 'midnight-signal',
        ];
    }
};
