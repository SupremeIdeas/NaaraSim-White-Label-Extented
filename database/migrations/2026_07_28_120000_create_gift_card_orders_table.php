<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Gift orders (Phase 3 — money path). One row per purchase. Money-safety:
 * only the retail `price_charged` is stored (never cost); the `receipt` holds the
 * three-state redemption payload (code / link / account). `transaction_ref` is
 * the idempotency key on both the wallet debit and the provider order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gift_card_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');                 // reloadly | zendit (masked from users)
            $table->string('provider_product_id');
            $table->string('brand_name');
            $table->decimal('face_value', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('price_charged', 18, 4);    // retail USD (what the user paid)
            $table->string('status')->default('pending'); // pending|processing|delivered|failed|refunded|review
            $table->string('transaction_ref')->unique();  // idempotency (debit + provider)
            $table->string('provider_tx_id')->nullable();
            $table->json('receipt')->nullable();
            $table->json('fields')->nullable();          // recipient/required fields
            $table->string('review_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_orders');
    }
};
