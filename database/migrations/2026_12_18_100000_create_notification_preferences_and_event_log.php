<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('channels')->nullable();            // {category: {in_app: bool, push: bool, email: bool}}; absent = the catalog default
            $t->string('quiet_start', 5)->nullable();    // HH:MM in the person's own timezone
            $t->string('quiet_end', 5)->nullable();
            $t->timestamps();
        });

        // Idempotency: an (person, event, key) is delivered once, however many times the trigger fires or a job is retried.
        Schema::create('notification_event_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('event', 60);
            $t->string('dedupe_key', 120);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['user_id', 'event', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_log');
        Schema::dropIfExists('notification_preferences');
    }
};
