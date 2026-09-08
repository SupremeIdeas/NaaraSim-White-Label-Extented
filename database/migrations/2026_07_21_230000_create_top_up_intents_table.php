<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * top_up_intents (owner request — deposit in local currency, money-safe half).
 * When a user funds in their local currency, we LOCK the USD credit at the live
 * rate here at initiation. On webhook success the wallet is credited THIS
 * usd_amount — never a figure re-derived from the gateway's reported currency —
 * so a rate move or a currency mismatch can never mis-credit the wallet. USD
 * deposits don't create an intent (they credit directly, unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('top_up_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('reference')->unique();        // the gateway payment ref
            $table->decimal('charge_amount', 18, 4);      // what the user pays
            $table->string('charge_currency', 4);         // …in their local currency
            $table->decimal('usd_amount', 18, 4);         // the LOCKED wallet credit
            $table->decimal('rate_usd_to_local', 18, 8);  // rate locked at init (audit)
            $table->string('status', 16)->default('pending'); // pending | credited
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_intents');
    }
};
