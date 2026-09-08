<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAARA-BUILD-8 §5 — AI-generated plan tooltips on esim_plans.
 *
 *  - ai_tooltip              the generated 1–2 sentence customer description.
 *  - ai_tooltip_override     a manual admin description that ALWAYS wins over the
 *                            generated one when set (§4.6).
 *  - ai_tooltip_generated_at when the generated copy was last produced — used to
 *                            avoid needlessly regenerating unchanged plans (§5.1).
 *
 * All nullable: generation is optional, queued, and degrades to null on failure
 * or when no Anthropic key is configured (§5.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->text('ai_tooltip')->nullable()->after('is_featured');
            $table->text('ai_tooltip_override')->nullable()->after('ai_tooltip');
            $table->timestamp('ai_tooltip_generated_at')->nullable()->after('ai_tooltip_override');
        });
    }

    public function down(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->dropColumn(['ai_tooltip', 'ai_tooltip_override', 'ai_tooltip_generated_at']);
        });
    }
};
