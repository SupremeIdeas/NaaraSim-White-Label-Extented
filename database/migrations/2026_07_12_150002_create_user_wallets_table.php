<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_wallets (blueprint Section 18.1). One wallet per user. Balances are
 * only ever changed inside a DB transaction that also writes a
 * wallet_transactions row (money-safety rule 1.2 — enforced in WalletService,
 * Module 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('ngn_balance', 18, 2)->default(0);
            $table->decimal('usd_balance', 18, 4)->default(0);
            $table->decimal('total_deposits', 18, 2)->default(0);
            $table->decimal('total_spent', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wallets');
    }
};
