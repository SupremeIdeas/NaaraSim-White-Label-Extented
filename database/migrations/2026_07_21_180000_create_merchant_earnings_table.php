<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * merchant_earnings (ROADMAP §Layer 3.4). An append-only ledger of a merchant's
 * reseller earnings — the M−R margin ACTUALLY COLLECTED when one of their
 * customers buys (accrual), plus holds/releases when the merchant cashes out.
 * Mirrors wallet_transactions: every row records balance_after and carries a
 * unique reference for idempotency, so no accrual or hold is ever double-counted.
 *
 * Money-safety: the earning is only ever the cash collected ABOVE retail, so the
 * admin's own margin (retail − cost) is never touched — the merchant is paid from
 * the upcharge their customer actually paid, nothing more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('type');                       // accrual | hold | release
            $table->decimal('amount', 12, 4);             // signed: accrual/release +, hold −
            $table->decimal('balance_after', 12, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('reference')->unique();        // idempotency key
            $table->string('source_type')->nullable();    // esim | number (accruals)
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_earnings');
    }
};
