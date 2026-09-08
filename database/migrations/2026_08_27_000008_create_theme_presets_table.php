<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAARA THEME SYSTEM — Batch 1 §1.1. One row per switchable visual skin.
 *
 * A preset is PAINT ONLY: colour/radius/typography tokens, an icon family flag,
 * per-surface hero image URLs, and which of the (at most) three structural
 * layout partials each page uses. It never carries business logic — the money
 * paths, provider routing and permission gates live in code and are untouched
 * by any theme. `naara-official` is the one built-in row (the current shipped
 * look) and is the permanent default + fallback; it can never be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_presets', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();            // 'naara-official', 'aurora-shift', ...
            $table->string('name');                      // display name in the admin picker
            $table->string('persona')->nullable();       // one-line art-direction summary
            $table->json('tokens');                      // colour/radius/typography tokens (§2.1)
            $table->json('icon_family');                 // {'style':'3d'|'sprite','set':'...'}
            $table->json('hero_assets')->nullable();     // per-surface hero image URLs
            $table->json('layout_variants')->nullable(); // which structural partial per page
            $table->boolean('is_built_in')->default(false); // true for naara-official ONLY
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_presets');
    }
};
