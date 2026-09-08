<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound SMS sent FROM a user's Naara Line (Numbers V6 §6). Money-safety:
 * we store only the RETAIL amount charged — never the provider cost — and the
 * masked provider is kept for routing/settlement, hidden from any user payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_number_id')->constrained()->cascadeOnDelete();
            $table->string('to_number', 32);
            $table->text('body');
            $table->string('provider', 32);              // masked from users (hidden on the model)
            $table->string('provider_ref')->nullable();  // provider message SID / id
            $table->string('status', 16)->default('queued'); // queued | sent | failed
            $table->unsignedSmallInteger('segments')->default(1);
            $table->decimal('amount_charged', 12, 4)->default(0); // RETAIL only
            $table->string('reference')->unique();       // idempotency / wallet reconciliation
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
