<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt 21-EXT2 §2 — the project-commencement brief a merchant fills once
 * their white-label license is active: desired brand name, WhatsApp contact,
 * hosting choice, and (for a self-hosted choice) the login credentials the
 * ops team needs to actually deploy it. Credential columns are encrypted at
 * rest via the model's `encrypted` casts (same discipline as
 * `PortInRequest::account_number`/`pin`) — this migration only shapes the
 * storage, the model owns the encryption.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_project_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('white_label_instance_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('desired_brand_name');
            $table->string('whatsapp_number');
            $table->string('brand_primary_color')->nullable();
            $table->string('brand_accent_color')->nullable();
            $table->string('logo_url')->nullable();
            $table->text('logo_design_reference')->nullable();
            $table->string('banner_reference_url')->nullable();
            $table->text('banner_design_request')->nullable();
            $table->string('hosting_choice');
            $table->timestamp('hosting_disclaimer_acknowledged_at')->nullable();
            $table->text('hosting_host')->nullable();
            $table->text('hosting_username')->nullable();
            $table->text('hosting_password')->nullable();
            $table->text('hosting_notes')->nullable();
            $table->text('additional_notes')->nullable();
            $table->string('status')->default('pending'); // pending, seen, in_progress, completed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedSmallInteger('deploy_days')->nullable();
            $table->timestamp('deploy_started_at')->nullable();
            $table->timestamp('deploy_completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_project_intakes');
    }
};
