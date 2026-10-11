<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rail Guide (Addendum B) G4/G5: the global-rail acknowledgement (written before a user
 * is enrolled) and the guide event stream (no PII beyond ids; hashes only for ip/agent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_rail_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('rail');
            $table->char('country', 2);
            $table->unsignedSmallInteger('guide_version');
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'rail', 'guide_version']);
        });

        Schema::create('payout_guide_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);   // guide_viewed|country_changed|rail_recommended|rail_selected|global_ack_confirmed|blocked_notify_requested
            $table->char('country', 2)->nullable();
            $table->string('rail')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index(['country', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_guide_events');
        Schema::dropIfExists('payout_rail_acknowledgements');
    }
};
