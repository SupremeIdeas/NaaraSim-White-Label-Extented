<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spam-report + auto-block (Prompt 11): a number that crossed the configurable
 * distinct-reporter threshold (or was blocked directly by an admin). Checked
 * before dialing (VoiceDialerService::begin) so a call to a known scam/spam
 * number is refused before any wallet hold is placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spam_blocked_callers', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->unique();
            $table->string('phone_number');
            $table->unsignedInteger('report_count_at_block')->default(0);
            $table->string('source')->default('auto'); // auto | admin
            $table->timestamp('blocked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_blocked_callers');
    }
};
