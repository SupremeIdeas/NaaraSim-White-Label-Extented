<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * api_clients (ROADMAP §Layer 2 — Developer API reselling). A developer owns one
 * or more API clients; each authenticates via a Sanctum token (the client is the
 * tokenable) with scoped abilities, is prepaid-billed from its own balance, and
 * is rate-limited by tier. The developer only ever sees THEIR own price
 * (wholesale + admin markup); provider cost is never exposed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('token_last_four', 8)->nullable(); // display only, never the token
            $table->json('scopes')->nullable();               // e.g. ["catalogue","order","status"]
            $table->string('rate_limit_tier')->default('standard');
            // Prepaid API wallet — its own money-safe ledger (built with the
            // ordering endpoint); orders never deliver without funds.
            $table->decimal('prepaid_balance_usd', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
