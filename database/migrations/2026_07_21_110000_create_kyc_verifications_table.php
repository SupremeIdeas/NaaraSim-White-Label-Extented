<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * kyc_verifications (ROADMAP §Layer 0.3 — the identity gate for withdrawals &
 * merchant migration). One row per attempt at a level: L2 (ID + liveness,
 * required to withdraw) or L3 (KYB business docs, required to become a merchant).
 * `checks` holds the provider's structured result (never raw PII beyond what's
 * needed). `reference` is unique per attempt for idempotent webhook handling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(2);   // 2 = individual, 3 = business (KYB)
            $table->string('provider');                          // smileid | dojah | manual | ...
            $table->string('status')->default('pending');        // pending | approved | rejected | failed
            $table->json('checks')->nullable();                  // structured provider result
            $table->string('reference')->unique();
            $table->text('reason')->nullable();                  // failure/rejection reason
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'level', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_verifications');
    }
};
