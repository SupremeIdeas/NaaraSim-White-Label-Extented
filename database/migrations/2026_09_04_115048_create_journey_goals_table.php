<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-defined "My Journey" achievements. Every metric is computed live from
 * real platform data (JourneyGoalService) — this table only stores the goal's
 * shape (what to measure, how much, over what period, what it pays out), never
 * a user's progress itself (see journey_goal_claims for that).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_goals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description');
            $table->string('metric'); // see JourneyGoalService::METRICS
            $table->decimal('target', 12, 2);
            $table->string('period_type')->default('lifetime'); // lifetime|monthly|quarterly|yearly|campaign
            $table->timestamp('starts_at')->nullable(); // campaign period_type only
            $table->timestamp('ends_at')->nullable();   // campaign period_type only
            $table->decimal('reward_credits', 12, 2);
            $table->string('audience')->default('all'); // all|merchant|merchant_v2
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_goals');
    }
};
