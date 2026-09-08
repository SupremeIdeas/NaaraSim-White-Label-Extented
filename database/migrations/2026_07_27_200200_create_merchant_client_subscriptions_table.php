<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client's eSIM subscription lifecycle (Merchant V2 client control). One row
 * per client eSIM plan the merchant runs — its type (Naara Data vs Naara
 * Connect), current provider order, validity countdown, and the auto-renew
 * earmark. Money moves through the wallet (reserve/settle); this table is the
 * lifecycle record the merchant manages + the alerts key off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_client_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('merchant_client_id')->constrained('merchant_clients')->cascadeOnDelete();
            $table->foreignId('esim_order_id')->nullable()->constrained('esim_orders')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('esim_plans')->nullOnDelete();
            $table->string('esim_type')->default('data');     // data | connect
            $table->string('status')->default('active');       // active | expired | disabled
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->decimal('renewal_price', 18, 4)->default(0);   // merchant price locked at mark-time
            $table->string('reserve_reference')->nullable();        // wallet reservation ref (earmark)
            $table->timestamp('due_alerted_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_client_subscriptions');
    }
};
