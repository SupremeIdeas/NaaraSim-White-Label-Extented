<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-6 §C.1.4: a brand partner's own social handles. Same duplicate-platform-
 * allowed pattern as the platform's handles; claims reuse the one claims table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_partner_handles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('handle_label');
            $table->string('handle_url', 512);
            $table->decimal('credit_reward', 12, 2)->default(0);
            $table->string('verification', 8)->default('self'); // self | api
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['brand_partner_id', 'is_active', 'sort_order'], 'bph_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_partner_handles');
    }
};
