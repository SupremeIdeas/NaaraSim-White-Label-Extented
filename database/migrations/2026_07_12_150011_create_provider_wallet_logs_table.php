<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * provider_wallet_logs (blueprint Section 18.2) — reconciles the prepaid
 * wallets NaaraSim holds with each upstream provider (e.g. eSIM Go). Records
 * balance_before / balance_after around every top-up, deduction and refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_wallet_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->enum('event', ['topup', 'deduction', 'refund']);
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('USD');
            $table->decimal('balance_before', 18, 4)->nullable();
            $table->decimal('balance_after', 18, 4)->nullable();
            $table->string('reference')->nullable()->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_wallet_logs');
    }
};
