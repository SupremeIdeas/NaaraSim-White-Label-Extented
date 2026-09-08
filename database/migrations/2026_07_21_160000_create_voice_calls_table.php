<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * voice_calls (Live Voice — Part B: in-browser dialer). One row per outbound
 * WebRTC call. The wallet is charged UPFRONT for the funded block of minutes
 * (an authorization hold — atomic, via WalletService), then settled on hang-up:
 * unused minutes are refunded (money-safety rules 1–7). provider_rate is the
 * wholesale per-minute COST and is NEVER exposed to the user (rule 1.2) — only
 * retail_per_min is ever surfaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('destination'); // E.164 the user dialled
            $table->string('provider')->default('twilio');
            $table->string('call_sid')->nullable();          // Twilio call ref (filled by webhook)
            $table->string('status')->default('connecting');  // connecting|in-progress|completed|no-answer|failed
            // Money columns (4-dp, same precision as the wallet).
            $table->decimal('retail_per_min', 12, 4);         // user-facing rate (retail)
            $table->decimal('provider_rate', 12, 4)->default(0); // wholesale cost — HIDDEN from users
            $table->unsignedInteger('minutes_authorized');    // funded block held upfront
            $table->decimal('amount_held', 12, 4);            // retail_per_min * minutes_authorized
            $table->string('hold_reference');                 // idempotency key for the upfront debit
            $table->unsignedInteger('minutes_billed')->nullable(); // billed on settlement
            $table->decimal('amount_charged', 12, 4)->nullable();
            $table->decimal('refunded', 12, 4)->default(0);   // unused minutes returned
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('settled_at')->nullable();       // set once → settlement is idempotent
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('call_sid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_calls');
    }
};
