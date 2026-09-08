<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security-question account recovery (owner request — fix_admin.md Part 2). A
 * JSON column holding 2–3 {question, answer_hash} pairs per user. Answers are
 * HASHED like passwords (never plaintext) and matched case-insensitively, so
 * this is a self-service recovery path for an admin when email reset isn't
 * available on a fresh self-hosted install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('security_questions')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('security_questions');
        });
    }
};
