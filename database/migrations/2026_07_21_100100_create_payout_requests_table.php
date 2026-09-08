<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payout_requests (ROADMAP §Layer 0.2 — the money-out ledger). One row per
 * withdrawal, moving pending → processing → paid | failed | reversed. The source
 * funds are held by the caller (a referral-credit bucket, a merchant's earnings)
 * BEFORE the request is created; `source_bucket` records which. `reference` is
 * unique for idempotency — a repeated reference never sends twice. Final state
 * comes from the PSP webhook, never the synchronous send alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('payee_type')->default('user');   // user | merchant (forward-compat)
            $table->foreignId('payout_account_id')->nullable()->constrained('payout_accounts')->nullOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3);
            $table->string('source_bucket');                 // referral_credits | merchant_earnings | ...
            $table->string('status')->default('pending');    // pending|approved|processing|paid|failed|reversed
            $table->string('provider')->nullable();
            $table->string('provider_ref')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();           // idempotency
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
