<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant V2 invoice ledger. A merchant's clients never log in to NaaraSim and
 * never pay through it — this is the merchant's OWN bookkeeping of what a client
 * owes them (for eSIM/number reselling), sent over WhatsApp/email as a branded,
 * public read-only link. No NaaraSim wallet ever moves because of this table;
 * "paid" here just means the merchant marked it settled after collecting
 * payment themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('merchant_client_id')->constrained('merchant_clients')->cascadeOnDelete();
            $table->foreignId('merchant_client_subscription_id')->nullable()
                ->constrained('merchant_client_subscriptions')->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 10, 2);
            // draft (not yet sent) -> sent (awaiting payment) -> paid, or void at any point before paid.
            $table->string('status')->default('draft');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->unique();
            $table->string('public_token', 40)->unique();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['merchant_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_invoices');
    }
};
