<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Updater — Batch 8 (Feature-Entitlement Gating). Batch 6–7 made the
 * license real and made `tier` (normal/extended) gate which PACKAGES a fork can
 * download. This batch adds a second, orthogonal axis: `entitlement_level`,
 * which gates which FEATURES a fork's users may use.
 *
 * `tier` = product line (which repo/license was bought) → package entitlement.
 * `entitlement_level` = payment progression within that product → feature locks:
 *   - basic    — Normal white-label before it pays up (most features locked).
 *   - standard — Normal white-label after payment (only the richest features stay locked).
 *   - full     — Extended white-label (nothing locked).
 * They aren't 1:1: an extended-tier instance is always `full`; a normal-tier one
 * is `basic` until the operator marks it paid, then `standard`. Nullable so every
 * existing Batch 6/7 row is untouched until a license is (re-)issued — a null
 * level is treated as fully unlocked by the resolver (fail-open), never a
 * surprise lock on an already-registered instance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->string('entitlement_level')->nullable()->after('tier'); // basic | standard | full | null
        });
    }

    public function down(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->dropColumn('entitlement_level');
        });
    }
};
