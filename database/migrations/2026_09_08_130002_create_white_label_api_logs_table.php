<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Updater — Batch 4 §2. A pure, append-only event log of what external
 * white-label instances did when they called the distribution API — the
 * oversight layer for the API surface. Deliberately separate from
 * platform_update_attempts (which records what happened on THIS master instance
 * itself); two audiences, two tables, one logging discipline.
 *
 * No updated_at — append-only, matching AuditLog's immutability discipline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('white_label_instance_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint');   // e.g. updates.check, updates.download
            $table->string('method');
            $table->unsignedSmallInteger('response_status');
            $table->string('ip_address')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['white_label_instance_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_api_logs');
    }
};
