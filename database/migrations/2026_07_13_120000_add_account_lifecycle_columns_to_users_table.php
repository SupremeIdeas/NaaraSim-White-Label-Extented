<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account lifecycle & data rights (blueprint Section 26 / GDPR). Columns for
 * self-deactivation (pause/resume), the queued data export, and the
 * super-admin-gated deletion workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Self-service pause. is_active already exists; this records when.
            $table->timestamp('deactivated_at')->nullable()->after('is_active');

            // Data export (Section 26.2): path on the private disk + when ready.
            $table->string('data_export_path')->nullable()->after('deactivated_at');
            $table->timestamp('data_export_ready_at')->nullable()->after('data_export_path');

            // Deletion workflow (Section 26.3): request -> super-admin approval.
            $table->timestamp('deletion_requested_at')->nullable()->after('data_export_ready_at');
            $table->timestamp('deletion_approved_at')->nullable()->after('deletion_requested_at');
            $table->unsignedBigInteger('deletion_approved_by')->nullable()->after('deletion_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'deactivated_at',
                'data_export_path',
                'data_export_ready_at',
                'deletion_requested_at',
                'deletion_approved_at',
                'deletion_approved_by',
            ]);
        });
    }
};
