<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();            // dashboard.main, wallet.main ...
            $t->string('section', 40);
            $t->string('title', 120);
            $t->string('summary', 200)->nullable();
            $t->string('icon', 40)->default('compass');
            $t->string('entry_route', 80);              // named route the tour starts on
            $t->string('audience', 60)->default('all'); // all | new_users | role:x | plan:any
            $t->unsignedInteger('version')->default(1);
            $t->boolean('is_active')->default(true);
            $t->boolean('auto_start')->default(true);
            $t->boolean('quick_enabled')->default(true);
            $t->boolean('is_customized')->default(false); // admin edited: a code sync never overwrites it
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('tour_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position');
            $t->string('anchor', 80);                   // data-tour value
            $t->string('title', 120);
            $t->text('body');
            $t->json('translations')->nullable();       // {fr: {title, body}}
            $t->string('placement', 10)->default('auto');
            $t->string('viewport', 10)->default('all'); // all | mobile | desktop
            $t->string('before_click', 80)->nullable(); // anchor to click first (e.g. open a tab)
            $t->string('requires', 60)->nullable();     // route:x | feature:x ; step skipped when unmet
            $t->boolean('quick')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tour_id', 'anchor']);
        });

        Schema::create('tour_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('tour_key', 60);
            $t->unsignedInteger('tour_version')->default(1);
            $t->string('status', 14)->default('not_started'); // not_started|in_progress|completed|skipped|dismissed
            $t->unsignedSmallInteger('last_step')->default(0);
            $t->string('last_mode', 5)->default('full');
            $t->unsignedSmallInteger('replay_count')->default(0);
            $t->unsignedSmallInteger('quick_replay_count')->default(0);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'tour_key']);
        });

        Schema::create('tour_user_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->boolean('auto_tours_enabled')->default(true);
            $t->timestamp('window_started_at')->nullable();
            $t->timestamps();
        });

        // Privacy-safe aggregate counters: no user id, one row per day/tour/step/event.
        Schema::create('tour_events', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('tour_key', 60);
            $t->string('anchor', 80)->default('');
            $t->string('event', 20);                    // started|step_viewed|step_skipped|completed|dismissed|replay|step_missing
            $t->unsignedInteger('total')->default(0);
            $t->unique(['day', 'tour_key', 'anchor', 'event']);
        });

        \App\Support\Tours\TourRegistry::sync();   // seed from config/tours.php (idempotent; naara:verify tours self-heals the same way)
    }

    public function down(): void
    {
        foreach (['tour_events', 'tour_user_settings', 'tour_progress', 'tour_steps', 'tours'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
