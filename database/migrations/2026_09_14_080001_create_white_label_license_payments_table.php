<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt 21-EXT §4 — one row per successful charge against a
 * WhiteLabelInstance's self-service license: the initial plan purchase, and
 * (for a normal-tier instance) a later, independent balance-completion
 * payment. amount_paid_total for an instance is SUM(amount_usd) over its own
 * rows here — the single source of truth §3's balance math and §6's
 * threshold counts both read, never re-derived two different ways.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_license_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('white_label_instance_id')->constrained('white_label_instances')->cascadeOnDelete();
            $table->decimal('amount_usd', 10, 2);
            $table->string('kind');                    // initial | balance_completion
            $table->string('payment_reference')->unique(); // the WalletTransaction reference, idempotency key
            $table->timestamps();

            $table->index(['white_label_instance_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_license_payments');
    }
};
