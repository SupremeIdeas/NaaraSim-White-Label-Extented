<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wallet_transactions (blueprint Section 18.1). Every balance change records
 * balance_before and balance_after in the same transaction. High-traffic
 * table — indexed on user_id+type and created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit', 'refund', 'referral', 'withdrawal']);
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('NGN');
            $table->decimal('balance_before', 18, 4);
            $table->decimal('balance_after', 18, 4);
            $table->string('reference')->nullable()->index();
            $table->string('description')->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
