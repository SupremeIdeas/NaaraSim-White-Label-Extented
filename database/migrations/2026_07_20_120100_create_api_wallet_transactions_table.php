<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * api_wallet_transactions (ROADMAP §Layer 2). The prepaid ledger for Developer
 * API clients — the same money-safety discipline as wallet_transactions
 * (balance_before / balance_after recorded in the same atomic transaction,
 * idempotent by reference). The running balance lives on
 * api_clients.prepaid_balance_usd; this table is its immutable history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->string('type'); // credit | debit | refund
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('USD');
            $table->decimal('balance_before', 18, 4);
            $table->decimal('balance_after', 18, 4);
            $table->string('reference');
            $table->string('description')->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();

            // Idempotency: one reference per client can only post once.
            $table->unique(['api_client_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_wallet_transactions');
    }
};
