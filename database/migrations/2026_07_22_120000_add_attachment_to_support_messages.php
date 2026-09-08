<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidence attachments for NaaraCare (owner request). A customer can attach a
 * file (screenshot / photo / PDF) to a support message as evidence; the AI
 * agent reads it (vision/document) to diagnose, and staff can view it on the
 * ticket. Stored on the PRIVATE disk (never web-public) like voice notes, so
 * only the owner or a working staff member can reach it via a signed route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('voice_status');
            $table->string('attachment_mime')->nullable()->after('attachment_path');
            $table->string('attachment_name')->nullable()->after('attachment_mime');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_mime', 'attachment_name']);
        });
    }
};
