<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The full per-theme page suite (owner request, 2026-09-07): "for each
 * theme, will and must carry its own homepage, about us page, and 3 extra
 * important page layouts." neon-vertex and midnight-signal are the first
 * two themes with hand-built about/how-it-works/contact pages — assigns
 * their about_page/how_it_works_page/contact_page section styles.
 * Additive only, same discipline as every other batch-1 backfill.
 */
return new class extends Migration
{
    private const KEYS = ['about_page', 'how_it_works_page', 'contact_page'];

    public function up(): void
    {
        foreach ($this->assignments() as $slug => $style) {
            $row = DB::table('theme_presets')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            $current = json_decode((string) $row->section_styles, true) ?: [];
            foreach (self::KEYS as $key) {
                if (! array_key_exists($key, $current)) {
                    $current[$key] = $style;
                }
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
            foreach (self::KEYS as $key) {
                if (($current[$key] ?? null) === $style) {
                    unset($current[$key]);
                }
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
