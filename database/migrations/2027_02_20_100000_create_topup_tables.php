<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Airtime & Data Top-Up layer: providers, routing, a normalised product
 * snapshot, price-locked quotes, orders with an append-only event trail, and a
 * redacted provider call log. Money itself moves through the existing wallet
 * ledger (WalletService); these tables hold only the top-up domain state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topup_providers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 24)->unique();
            $t->string('name', 60);
            $t->boolean('enabled')->default(true);
            $t->string('currency', 3)->default('USD');
            $t->decimal('min_float', 14, 2)->default(0);
            $t->decimal('last_balance', 14, 2)->nullable();
            $t->timestamp('last_balance_at')->nullable();
            $t->string('last_error', 255)->nullable();
            $t->boolean('low_float_alerted')->default(false);
            $t->timestamp('last_synced_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('topup_routes', function (Blueprint $t) {
            $t->id();
            $t->string('country_iso', 2)->nullable();      // null/'*' = every country
            $t->string('product_type', 8)->default('*');   // airtime | data | *
            $t->string('provider_code', 24);
            $t->unsignedSmallInteger('priority')->default(10);
            $t->boolean('enabled')->default(true);
            $t->decimal('min_amount_usd', 10, 2)->nullable();
            $t->decimal('max_amount_usd', 10, 2)->nullable();
            $t->timestamps();
            $t->index(['country_iso', 'product_type', 'priority']);
        });

        Schema::create('topup_products', function (Blueprint $t) {
            $t->id();
            $t->string('provider_code', 24);
            $t->string('provider_sku', 120);
            $t->string('country_iso', 2);
            $t->string('operator_name', 80);
            $t->string('operator_code', 80)->nullable();
            $t->string('type', 8);                          // airtime | data | combo
            $t->string('name', 160);
            $t->string('validity_text', 60)->nullable();
            $t->unsignedBigInteger('data_mb')->nullable();
            $t->boolean('data_unlimited')->default(false);
            $t->boolean('is_fixed')->default(true);
            $t->decimal('min_amount', 14, 2)->nullable();   // send currency
            $t->decimal('max_amount', 14, 2)->nullable();
            $t->decimal('fixed_amount', 14, 2)->nullable();
            $t->string('currency', 3)->default('USD');      // currency the customer's recipient receives (send)
            $t->decimal('cost_native', 14, 4)->nullable();  // PRIVATE: our cost for a fixed product, in cost_currency
            $t->decimal('cost_ratio', 14, 8)->nullable();   // PRIVATE: cost per 1 send-currency unit (range products), in cost_currency
            $t->string('cost_currency', 3)->nullable();     // currency our cost is expressed in (USD for Zendit, NGN for VTpass...)
            $t->boolean('active')->default(true);
            $t->timestamp('synced_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->unique(['provider_code', 'provider_sku', 'country_iso'], 'topup_products_sku_unique');
            $t->index(['country_iso', 'type', 'active']);
            $t->index(['operator_name']);
        });

        Schema::create('topup_quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('topup_products')->cascadeOnDelete();
            $t->string('phone_e164', 20);
            $t->decimal('send_amount', 14, 2);              // amount the recipient receives, send currency
            $t->string('send_currency', 3);
            $t->decimal('cost_usd', 12, 4);                 // PRIVATE
            $t->decimal('price_usd', 12, 4);                // what the customer pays
            $t->decimal('markup_pct', 8, 4)->default(0);
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->index(['user_id', 'expires_at']);
        });

        Schema::create('topup_orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('quote_id')->nullable()->constrained('topup_quotes')->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained('topup_products')->nullOnDelete();
            $t->string('phone_e164', 20);
            $t->string('country_iso', 2);
            $t->string('operator_name', 80)->nullable();
            $t->string('product_type', 8);
            $t->string('product_name', 160)->nullable();
            $t->decimal('send_amount', 14, 2);
            $t->string('send_currency', 3);
            $t->string('provider_code', 24);
            $t->string('provider_sku', 120);
            $t->string('reference', 64)->unique();          // our reference sent to the provider
            $t->string('provider_txn_id', 80)->nullable();
            $t->string('status', 16)->default('created');
            $t->decimal('price_usd', 12, 4);
            $t->decimal('cost_usd', 12, 4)->nullable();     // PRIVATE
            $t->decimal('cost_native', 14, 4)->nullable();
            $t->string('cost_currency', 3)->nullable();
            $t->string('wallet_ref', 100)->nullable()->unique();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->unsignedSmallInteger('requery_count')->default(0);
            $t->timestamp('last_requery_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->boolean('escalated')->default(false);
            $t->string('failure_reason', 255)->nullable();
            $t->string('idempotency_key', 80)->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['status', 'last_requery_at']);
            $t->index(['user_id', 'created_at']);
            $t->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('topup_order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('topup_orders')->cascadeOnDelete();
            $t->string('type', 40);
            $t->json('payload')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['order_id', 'created_at']);
        });

        Schema::create('topup_provider_logs', function (Blueprint $t) {
            $t->id();
            $t->string('provider_code', 24);
            $t->string('endpoint', 160);
            $t->string('reference', 80)->nullable();
            $t->text('request')->nullable();
            $t->text('response')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->unsignedInteger('duration_ms')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['provider_code', 'created_at']);
            $t->index('reference');
        });
    }

    public function down(): void
    {
        foreach (['topup_provider_logs', 'topup_order_events', 'topup_orders', 'topup_quotes', 'topup_products', 'topup_routes', 'topup_providers'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
