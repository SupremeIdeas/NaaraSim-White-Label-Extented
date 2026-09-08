<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAARA THEME SYSTEM — swappable sections (owner request, 2026-09-07):
 * "Naara official themes to have the capability to reuse any theme header,
 * bottom nav, login screen and any other sections on demand from across
 * these themes as swappable capability... admin can still decide to use
 * the Naara official theme and still swap any section for any area
 * possible, no bloating."
 *
 * `section_styles` is a sibling of `layout_variants` (which picks between
 * a handful of STRUCTURAL variants of a page's own content) but for CHROME
 * sections that are shared across the whole app rather than owned by one
 * page: header, bottom_nav, login, landing_hero (more added over time).
 * Each maps to a named, whitelisted style key (see
 * ThemePreset::SECTION_STYLE_ALLOW) — never a raw path or arbitrary string
 * — so a preset can point a section at any built style family, including
 * one "borrowed" from a completely different theme's persona, without
 * duplicating a single file per theme. Nullable + defaults to nothing so
 * every existing preset (all 40) keeps rendering today's exact shared
 * chrome until an admin deliberately assigns a style.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_presets', function (Blueprint $table) {
            $table->json('section_styles')->nullable()->after('layout_variants');
        });
    }

    public function down(): void
    {
        Schema::table('theme_presets', function (Blueprint $table) {
            $table->dropColumn('section_styles');
        });
    }
};
