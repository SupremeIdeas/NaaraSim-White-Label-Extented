<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt 21-EXT §5 — a single global earnings ledger for white-label license
 * sale proceeds, deliberately separate from any general platform-profit
 * reporting (the owner was explicit these are a separate pool). Shaped
 * exactly like merchant_earnings (append-only, balance_after, unique
 * reference for idempotency) but with no owning merchant_id — there is
 * exactly one running balance, not one per admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_earnings', function (Blueprint $table) {
            $table->id();
            $table->string('type');                  // accrual | hold | release
            $table->decimal('amount', 12, 4);         // signed: accrual/release +, hold −
            $table->decimal('balance_after', 12, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('reference')->unique();   // idempotency key
            $table->string('source_type')->nullable(); // white_label_license
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_earnings');
    }
};
