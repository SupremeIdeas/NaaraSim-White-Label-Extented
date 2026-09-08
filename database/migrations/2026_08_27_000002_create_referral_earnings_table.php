<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * referral_earnings (NAARA-BUILD-22 §1). An append-only ledger of a referrer's
 * REAL, withdrawable earnings — a share of Naara's own margin on the referred
 * user's first successful transaction. Distinct from the existing NaaraCredit
 * referral bonus (CreditService): this is genuine cash the user can pay out.
 *
 * Mirrors merchant_earnings exactly: every row records balance_after and carries
 * a unique reference for idempotency, so a reward or hold is never double-booked.
 * Cost is never stored — the amount is a share of the internal margin, computed
 * server-side, and only the resulting payable earning is persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // the referrer
            $table->string('type');                       // accrual | hold | release
            $table->decimal('amount', 12, 4);             // signed: accrual/release +, hold −
            $table->decimal('balance_after', 12, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('reference')->unique();        // idempotency key
            $table->string('source_type')->nullable();    // esim | number
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete(); // the referred buyer
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_earnings');
    }
};
