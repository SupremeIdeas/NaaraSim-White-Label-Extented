<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * call_events (Live Voice — Part A). A lightweight log of inbound-call activity
 * on forwarded numbers (ringing/answered/completed/no-answer), written by a
 * queued job so the webhook stays fast (money-safety rule 8). Never stores the
 * provider name to the user surface — this is an internal/operator log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('call_forwarding_rule_id')->nullable()->constrained('call_forwarding_rules')->nullOnDelete();
            $table->string('call_sid')->nullable();
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->string('status')->default('ringing'); // ringing|answered|completed|no-answer|failed
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_events');
    }
};
