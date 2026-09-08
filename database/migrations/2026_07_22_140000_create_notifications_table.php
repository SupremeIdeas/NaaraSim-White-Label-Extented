<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications (owner request). Laravel's native database-notifications
 * table — fully self-hosted, no third-party push service, works on VPS and
 * shared cPanel alike. Every user-facing notification is delivered here (bell +
 * notification centre) in addition to email, and admin announcements/offers
 * fan out into it so everyone sees and can act on them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The bell query is "this user's unread, newest first" — index for it.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
