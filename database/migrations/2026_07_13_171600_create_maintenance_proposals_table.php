<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claude-assisted maintenance loop (blueprint Section 29). Each row is a
 * proposed fix for a logged error: the human-readable diff plus the concrete
 * file changes, and the review/PR/rollback state. Nothing reaches production
 * without a super-admin approval that opens a CI-gated pull request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('error_log_id')->nullable()->constrained('error_logs')->nullOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('diff')->nullable();           // rendered, for review
            $table->json('changes')->nullable();            // path => new content, for applying
            $table->string('status')->default('pending')->index(); // pending|approved|rejected|pr_opened|rolled_back
            $table->string('branch')->nullable();
            $table->string('pr_url')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_proposals');
    }
};
