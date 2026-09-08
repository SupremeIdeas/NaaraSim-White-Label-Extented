<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Price with Claude — the AI Pricing Architect (owner-requested extra layer).
 * Claude analyses live provider costs and current retail, then PROPOSES optimal
 * retail prices + safe promo caps. Nothing here ever sets a price directly: a
 * proposal is stored pending admin approval, and MarginGuard re-clamps every
 * value on apply, so the LLM can never push a sale below cost + minimum profit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|superseded|failed
            $table->string('model_used')->nullable();          // e.g. claude-sonnet-5
            $table->text('summary')->nullable();               // Claude's one-paragraph verdict
            $table->json('meta')->nullable();                  // recommended promo caps, market notes
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('pricing_proposal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('pricing_proposals')->cascadeOnDelete();
            $table->string('item_type', 12)->default('esim');   // esim|number
            $table->foreignId('plan_id')->nullable()->constrained('esim_plans')->cascadeOnDelete();
            $table->string('name');
            // All money is admin-side here (behind the admin gate) — cost is
            // stored so the profit maths is auditable. It never leaves admin.
            $table->decimal('cost_usd', 12, 4)->default(0);
            $table->decimal('current_retail_usd', 12, 4)->default(0);
            $table->decimal('proposed_retail_usd', 12, 4)->default(0);
            $table->decimal('floor_usd', 12, 4)->default(0);    // cost + min profit (the guard line)
            $table->decimal('projected_profit_usd', 12, 4)->default(0);
            $table->decimal('projected_margin_pct', 8, 2)->default(0);
            $table->boolean('guard_applied')->default(false);   // true if MarginGuard raised the proposal
            $table->boolean('accepted')->default(true);         // admin can deselect a line before approving
            $table->text('rationale')->nullable();
            $table->timestamps();
            $table->index(['proposal_id', 'accepted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_proposal_lines');
        Schema::dropIfExists('pricing_proposals');
    }
};
