<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card disputes / chargebacks (BUILD-2 §7.2). When a gateway reports a dispute
 * we FREEZE the disputed amount from the user's wallet (reserve earmark, so it
 * can't be spent or withdrawn while contested) and alert an admin. On
 * resolution we release the earmark (won) or release + debit it (lost/charged
 * back). Unique on (gateway, provider_dispute_id) — idempotent across retries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway');
            $table->string('provider_dispute_id');
            $table->string('reference')->nullable();      // original transaction reference
            $table->decimal('amount', 15, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('open');     // open | won | lost
            $table->decimal('frozen_amount', 15, 4)->default(0); // what we actually reserved
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'provider_dispute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_disputes');
    }
};
