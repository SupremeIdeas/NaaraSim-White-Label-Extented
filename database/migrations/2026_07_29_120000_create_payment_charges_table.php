<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provider charge identifiers captured at webhook time (BUILD-2 §7). A refund
 * or a dispute needs the gateway's own id — Stripe's payment_intent, PayPal's
 * capture id, Flutterwave's numeric transaction id — which our NAARA reference
 * doesn't carry. We record it when the successful payment webhook lands, keyed
 * by (gateway, reference), and reverse-index provider_charge_id so a dispute
 * webhook (which only cites the provider id) can map back to our reference/user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_charges', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('reference');            // our NAARA-… reference
            $table->string('provider_charge_id')->nullable(); // payment_intent / capture id / txn id
            $table->decimal('amount', 15, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'reference']);
            $table->index(['gateway', 'provider_charge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_charges');
    }
};
