<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login notice / alert pop-ups. Admin composes a rich-text notice, targets it
 * (all / new / returning users), caps how many times each user sees it, sets a
 * time window, and optionally attaches a CTA link + coupon. alert_views tracks
 * per-user view counts + dismissal so the cap is honoured and a dismissed notice
 * stays gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body')->nullable();        // sanitized rich HTML
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('coupon_code')->nullable();
            $table->string('audience')->default('all');   // all | new | old
            $table->unsignedSmallInteger('new_days')->default(14); // "new" = registered within N days
            $table->string('trigger')->default('any');    // any | first_registration
            $table->unsignedSmallInteger('max_views')->default(1); // 0 = unlimited
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        Schema::create('alert_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('views')->default(0);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['alert_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_views');
        Schema::dropIfExists('alerts');
    }
};
