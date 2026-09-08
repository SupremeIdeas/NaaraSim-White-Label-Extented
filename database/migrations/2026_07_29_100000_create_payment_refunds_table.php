<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-triggered refunds of wallet top-ups (BUILD-2 §7.1). One row per refunded
 * top-up. The wallet reversal is a `wallet_transactions` ledger row (never a
 * direct balance write); this table is the audited record of the provider-side
 * refund + who did it. Unique on (gateway, reference) — a top-up is refunded at
 * most once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->string('reference');                 // original top-up reference (NAARA-…)
            $table->decimal('amount', 15, 4);
            $table->string('currency', 3);
            $table->string('status')->default('pending'); // pending | done | failed | manual
            $table->string('provider_refund_ref')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
