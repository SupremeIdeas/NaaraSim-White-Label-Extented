<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spam-report + auto-block (Prompt 11): a user-reported signal on a phone
 * number, from either Contacts or the Dialer. One row per (number, reporter) —
 * a user reporting the same number twice updates the existing row rather than
 * inflating the count, so the auto-block threshold is genuinely distinct users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spam_reports', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');          // digits-only match key
            $table->string('phone_number');    // display E.164
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->string('source')->default('dialer'); // dialer | contacts
            $table->timestamps();

            $table->unique(['msisdn', 'reporter_user_id']);
            $table->index('msisdn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_reports');
    }
};
