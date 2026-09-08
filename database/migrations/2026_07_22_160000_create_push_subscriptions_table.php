<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web-push subscriptions (owner request — closed-tab notifications). Self-hosted
 * on the W3C Web Push standard: the browser mints a subscription (endpoint +
 * keys), we store it here, and BroadcastVapidJob signs+encrypts a payload with
 * our own VAPID keys — no third-party push SERVICE. One user may have several
 * subscriptions (multiple devices/browsers); the endpoint is the unique id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique(); // sha256(endpoint) — indexable dedupe
            $table->string('public_key');   // p256dh
            $table->string('auth_token');    // auth
            $table->string('content_encoding')->default('aesgcm');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
