<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-6 §C.1: the platform's own official social handles (admin-managed).
 * Multiple rows of the same platform are allowed (e.g. two Facebook pages) —
 * that's just multiple rows, not a schema constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_follow_handles', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);            // instagram, tiktok, x, ... (extensible)
            $table->string('handle_label');            // e.g. "Naara Nigeria"
            $table->string('handle_url', 512);
            $table->decimal('credit_reward', 12, 2)->default(0); // surprise amount, never shown pre-follow
            $table->string('verification', 8)->default('self');  // self | api
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_follow_handles');
    }
};
