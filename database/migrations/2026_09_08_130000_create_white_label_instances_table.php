<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Updater — Batch 4 §1. The central registry of white-label brand
 * instances: who is a registered subscriber, tracked on the master platform.
 * Mirrors the Merchant registration/approval shape (pending → active, with a
 * reviewed_by/reviewed_at admin trail). The row also IS the Sanctum tokenable
 * for every request the deployed white-label copy makes back to the master.
 *
 * Registration + license-key issuance (how a row first lands here and how its
 * API token is generated and handed over) is Batch 6's job — this batch defines
 * the table it will populate. `tier` stays null until Batch 7's plan system
 * populates it via a real payment flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_instances', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name');
            $table->string('slug')->unique();
            $table->string('contact_email');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, active, suspended, rejected
            $table->string('tier')->nullable();
            $table->string('current_platform_version')->nullable();
            $table->timestamp('last_checked_in_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_instances');
    }
};
