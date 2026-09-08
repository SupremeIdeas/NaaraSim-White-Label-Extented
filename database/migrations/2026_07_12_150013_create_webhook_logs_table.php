<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * webhook_logs (blueprint Section 18.2) — every inbound webhook is logged with
 * its raw payload and signature BEFORE processing, and the HMAC verification
 * result (verified) is recorded, so provider callbacks are fully auditable and
 * idempotent (money-safety rule 1.2 / 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('event_type')->nullable();
            $table->json('payload')->nullable();
            $table->string('signature')->nullable();
            $table->boolean('verified')->default(false);
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
