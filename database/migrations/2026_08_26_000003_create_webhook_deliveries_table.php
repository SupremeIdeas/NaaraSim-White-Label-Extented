<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound webhook delivery log (Laravel readiness — Domain 13/14 observability).
 * Records every hit on a webhooks/* route so an operator can SEE whether a
 * provider (Paystack, Twilio, …) is actually calling us and whether it was
 * accepted (2xx) or rejected (401 signature fail) — the recurring "is the webhook
 * even reaching us?" question. Pure observability: written AFTER the response, it
 * never alters any handler's idempotency or money logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->index();
            $table->string('path', 191);
            $table->unsignedSmallInteger('status_code');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
