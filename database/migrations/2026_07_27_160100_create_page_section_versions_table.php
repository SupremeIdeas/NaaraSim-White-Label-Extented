<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Published snapshots + version history for the Section Builder (prompt §3).
 * Every publish writes an immutable JSON snapshot of the page's sections and
 * flips it live; the public renderer reads the row flagged `is_live`. Rollback
 * is "reset to previous settings by time" — restore any past snapshot back into
 * the draft rows and re-publish it, without losing the intervening history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_section_versions', function (Blueprint $table) {
            $table->id();
            $table->string('page_key')->index();
            $table->json('snapshot');                     // [{type, config, sort_order, is_active}, ...]
            $table->string('label')->nullable();          // optional admin note per publish
            $table->boolean('is_live')->default(false);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['page_key', 'is_live']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_section_versions');
    }
};
