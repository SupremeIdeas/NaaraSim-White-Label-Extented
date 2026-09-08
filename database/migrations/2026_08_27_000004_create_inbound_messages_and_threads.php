<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound SMS + conversation threads (Numbers overhaul §1). OutboundMessage was
 * send-only; this adds the receive side and a lightweight per-conversation
 * summary table so the inbox thread list + the nav unread badge are cheap indexed
 * lookups instead of a live UNION across two tables on every page load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_number_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_number', 32);
            $table->text('body')->nullable();
            $table->string('attachment_url', 500)->nullable();
            $table->string('provider', 40);
            $table->string('provider_ref')->nullable();
            $table->timestamp('read_at')->nullable();      // null = unread
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_ref']);  // webhook idempotency
            $table->index(['user_id', 'from_number']);
        });

        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_number_id')->nullable()->constrained()->nullOnDelete();
            $table->string('counterpart_number', 32);      // the other party
            $table->string('last_body', 500)->nullable();
            $table->string('last_direction', 3)->default('in'); // in | out
            $table->timestamp('last_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'counterpart_number']);
            $table->index(['user_id', 'last_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('inbound_messages');
    }
};
