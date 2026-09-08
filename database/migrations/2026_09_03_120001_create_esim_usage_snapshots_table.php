<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connectivity Analytics blueprint, Part A §2.2.1 — the time-series enabler for
 * every usage graph. `esim_orders.data_remaining_mb` is a single mutable value
 * that can only ever answer "what's left right now"; a usage-over-time chart
 * needs a real history, which is what this table exists to hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esim_usage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esim_order_id')->constrained('esim_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // denormalized for fast per-user reads
            $table->unsignedBigInteger('data_total_mb')->nullable();
            $table->unsignedBigInteger('data_remaining_mb')->nullable();
            $table->unsignedBigInteger('data_used_mb')->nullable(); // computed at write time, stored for cheap graph reads
            $table->string('bundle_status')->nullable(); // whatever the provider's getUsage() reports
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['esim_order_id', 'captured_at']);
            $table->index(['user_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esim_usage_snapshots');
    }
};
