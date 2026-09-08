<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (user, goal, period) a user has actually reached and been paid
 * for. The unique index is the real guard against a double grant — it holds
 * even if the evaluation job somehow fires twice for the same period, on top
 * of CreditService::earn()'s own reference-based idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_goal_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_goal_id')->constrained('journey_goals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_key'); // 'lifetime' | 'YYYY-MM' | 'YYYY-Q#' | 'YYYY' | 'campaign'
            $table->decimal('achieved_value', 12, 2);
            $table->decimal('credits_granted', 12, 2);
            $table->string('reference')->unique();
            $table->timestamps();

            $table->unique(['journey_goal_id', 'user_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_goal_claims');
    }
};
