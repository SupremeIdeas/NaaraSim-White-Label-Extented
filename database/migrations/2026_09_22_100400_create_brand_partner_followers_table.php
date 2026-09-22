<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request (2026-09-22): let users "Connect" to a brand directly on
 * Naara (distinct from following an external social handle for a credit
 * reward) — a simple in-platform follow relationship plus a cached follower
 * count for fast display on the directory card and the profile page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_partner_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['brand_partner_id', 'user_id']);
        });

        Schema::table('brand_partners', function (Blueprint $table) {
            $table->unsignedInteger('naara_followers_count')->default(0)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('brand_partners', function (Blueprint $table) {
            $table->dropColumn('naara_followers_count');
        });
        Schema::dropIfExists('brand_partner_followers');
    }
};
