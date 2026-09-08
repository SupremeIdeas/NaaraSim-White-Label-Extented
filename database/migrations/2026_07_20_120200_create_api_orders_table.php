<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * api_orders (ROADMAP §Layer 2). A developer's order placed through the API,
 * keyed by a per-client `reference` the developer polls for status. It links to
 * the real fulfilment record (esim_orders / sms_orders) but is the ONLY thing
 * the developer sees — masked to their price + delivery details, never cost or
 * the supplier. The unique (client, reference) is the idempotency boundary that
 * stops a retried request from provisioning (and charging) twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->string('kind'); // esim | number
            $table->string('reference');
            $table->string('status')->default('processing'); // processing|completed|failed|refunded
            $table->decimal('price_usd', 18, 4);
            $table->string('currency', 3)->default('USD');
            $table->foreignId('esim_order_id')->nullable()->constrained('esim_orders')->nullOnDelete();
            $table->foreignId('sms_order_id')->nullable()->constrained('sms_orders')->nullOnDelete();
            $table->json('result')->nullable(); // masked delivery (iccid/qr/lpa or number/code)
            $table->timestamps();

            $table->unique(['api_client_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_orders');
    }
};
