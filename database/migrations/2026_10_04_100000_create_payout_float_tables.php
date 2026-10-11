<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Payout Layer — Phase 4: treasury. `payout_float_balances` is the platform's
 * pre-funded balance AT each provider (per currency); a row existing = "float is
 * tracked for this rail". `payout_float_movements` is the append-only ledger behind
 * it (top-ups, payouts, reversals, provider syncs, adjustments). A rail with no row
 * is untracked and behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_float_balances', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->char('currency', 3);
            $table->decimal('balance', 18, 4)->default(0);
            $table->decimal('low_threshold', 18, 4)->default(0);   // alert below this
            $table->boolean('auto_sync')->default(false);          // pull the provider's own balance
            $table->timestamp('last_synced_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'currency']);
        });

        Schema::create('payout_float_movements', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->char('currency', 3);
            $table->string('type', 24);                            // topup|payout|payout_reversal|sync|adjustment
            $table->decimal('amount', 18, 4);                      // signed: + adds float, - uses it
            $table->decimal('balance_after', 18, 4);
            $table->foreignId('payout_request_id')->nullable()->constrained('payout_requests')->nullOnDelete();
            $table->string('reference')->nullable()->unique();     // idempotency: payout:{id} / payout-reversal:{id} / topup:{ref}
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider', 'currency', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_float_movements');
        Schema::dropIfExists('payout_float_balances');
    }
};
