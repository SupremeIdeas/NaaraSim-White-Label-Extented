<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voicemail + transcription (Prompt 11): a voicemail is stored as a normal
 * InboundMessage (reuses the existing Messages inbox — no separate UI).
 * `voicemail_path` is the PRIVATE-disk audio file (never local disk, never a
 * raw Twilio URL exposed to the browser — streamed via an owner-scoped
 * controller, same pattern as SupportVoiceController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->string('voicemail_path')->nullable()->after('attachment_url');
            $table->unsignedInteger('voicemail_duration_seconds')->nullable()->after('voicemail_path');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->dropColumn(['voicemail_path', 'voicemail_duration_seconds']);
        });
    }
};
