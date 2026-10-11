<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Member-to-member earnings transfer (escrow + accept). For people whose country has no payout rail yet: they can send
 * their withdrawable earnings to a trusted member who CAN be paid out, who accepts and cashes out normally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earnings_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_bucket', 20);            // referral | merchant
            $table->decimal('amount_usd', 14, 4);
            $table->string('note', 200)->nullable();
            $table->string('status', 20)->default('pending'); // pending | accepted | declined | cancelled | expired
            $table->timestamp('expires_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['sender_id', 'status']);
            $table->index(['recipient_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earnings_transfers');
    }
};
