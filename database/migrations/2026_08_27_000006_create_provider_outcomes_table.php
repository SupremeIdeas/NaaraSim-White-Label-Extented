<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAARA-BUILD-15 — the outcome log (NCI Layer 2). One row per REAL purchase
 * attempt (success or failure) across all three routers. This is a genuine event
 * log, not a rolling counter: the 24h success rate and consecutive-failure count
 * are computed FROM this table, so they can never drift from reality. It is also
 * the raw material BUILD-16's NCI learns from — hence error_code, to tell a
 * timeout from an out-of-stock from an auth failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key');
            $table->string('stack');                 // esim | sms | permanent
            $table->string('outcome');               // success | failure
            $table->string('error_code')->nullable();
            $table->string('order_ref')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['provider_key', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_outcomes');
    }
};
