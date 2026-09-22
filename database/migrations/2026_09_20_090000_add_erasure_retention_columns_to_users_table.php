<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erasure fix (Tier 0 #2, overdue): account deletion moves from an instant
 * hard-delete to anonymize-and-retain, with a real, eventual purge. These two
 * columns record when a deletion-approved account was anonymized and when its
 * retained financial/order records become eligible for the final, irreversible
 * purge (admin-configurable window; see account_erasure.retention_years).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('deletion_approved_by');
            $table->timestamp('retention_purge_due_at')->nullable()->after('anonymized_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['anonymized_at', 'retention_purge_due_at']);
        });
    }
};
