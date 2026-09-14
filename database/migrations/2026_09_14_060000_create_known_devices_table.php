<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A durable record of every IP+user-agent pair a user has ever signed in
     * from — unlike the `sessions` table (which only reflects currently-live
     * sessions and gets pruned), this is what lets a login listener answer
     * "have I ever seen this device for this user" so a genuinely new one can
     * be flagged (Sept-14 owner request: 2FA/device-login alerts).
     */
    public function up(): void
    {
        Schema::create('known_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64); // sha256(ip.'|'.user_agent)
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('known_devices');
    }
};
