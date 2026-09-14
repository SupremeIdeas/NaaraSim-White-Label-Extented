<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt 21-EXT §1 — the seeded plan catalog that supersedes Prompt 21 §4.1's
 * "no fixed price list" instruction. Admin-editable (name/price/cover art/
 * description/features), never merchant-editable. `tier` matches
 * WhiteLabelInstance::TIERS exactly — no new tier vocabulary invented here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_license_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();              // basic | medium | extended | extended_v2
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price_usd', 10, 2);
            $table->string('tier');                        // normal | extended (WhiteLabelInstance::TIERS)
            $table->string('support_level')->default('standard'); // standard | priority
            $table->string('cover_image_url')->nullable(); // Wasabi-stored, never local disk
            $table->json('features')->nullable();          // comparison-table bullet list
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_license_plans');
    }
};
