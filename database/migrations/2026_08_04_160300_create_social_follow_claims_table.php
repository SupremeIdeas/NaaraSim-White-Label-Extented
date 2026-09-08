<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-6 §C.1.2: the "signed contract" — one claim per user per handle, for
 * BOTH the platform's own handles and brand-partner handles (exactly one of the
 * two FKs is set; the app layer enforces that, the unique indexes enforce
 * "once"). This is what makes a follow permanent and unrepeatable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_follow_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handle_id')->nullable()->constrained('social_follow_handles')->cascadeOnDelete();
            $table->foreignId('brand_partner_handle_id')->nullable()->constrained('brand_partner_handles')->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamps();

            // One claim per user per handle of either kind → the follow can't repeat.
            $table->unique(['user_id', 'handle_id']);
            $table->unique(['user_id', 'brand_partner_handle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_follow_claims');
    }
};
