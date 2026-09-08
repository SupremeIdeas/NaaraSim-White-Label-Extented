<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A partner's profit-share ledger — mirrors merchant_earnings' accrual/hold/
 * release shape and atomic discipline, but each accrual books a PERIOD's share
 * of platform profit (period_start/end recorded for audit), not a per-sale
 * margin. Kept structurally separate from the merchant ledger on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('type');                       // accrual | hold | release
            $table->decimal('amount', 12, 4);             // signed: accrual/release +, hold −
            $table->decimal('balance_after', 12, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('reference')->unique();        // idempotency key
            // Accruals record the profit period they represent (audit trail).
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_earnings');
    }
};
