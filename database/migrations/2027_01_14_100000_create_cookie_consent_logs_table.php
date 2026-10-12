<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Proof of consent: one row per choice a visitor makes (accept, reject, custom save, withdraw), kept for the retention period set in Admin > Cookies. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_consent_logs', function (Blueprint $t) {
            $t->id();
            $t->string('consent_id', 40)->index();          // random id held in the visitor's consent cookie; links one visitor's choices over time
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('policy_version');
            $t->string('action', 14);                        // accept_all | reject_all | custom | withdraw | gpc
            $t->boolean('functional')->default(false);
            $t->boolean('analytics')->default(false);
            $t->boolean('marketing')->default(false);
            $t->string('ip_hash', 40)->nullable();           // HMAC of the address, never the address itself
            $t->string('user_agent', 200)->nullable();
            $t->string('path', 160)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consent_logs');
    }
};
