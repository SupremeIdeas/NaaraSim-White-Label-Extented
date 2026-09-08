<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * esim_plans (blueprint Section 18.1) — the money-critical catalogue table.
 *
 *  - cost_price_usd / airalo_min_price are PRIVATE and must never reach a
 *    user-facing payload (hidden on the model).
 *  - final_retail_usd is a STORED generated column:
 *        COALESCE(manual_retail_usd, computed_retail_usd)
 *    so a manual admin override always wins over the engine-computed retail,
 *    and the database — not application code — is the single source of truth
 *    for the price shown to users.
 *
 * The two source columns are defined before the generated column so the
 * expression can reference them (required by MySQL; also fine on SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esim_plans', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['esimgo', 'airalo', 'quibity']);
            $table->string('provider_plan_id')->index();
            $table->string('name');
            $table->string('type')->nullable();
            $table->unsignedBigInteger('data_mb')->nullable();
            $table->unsignedInteger('validity_days')->nullable();
            $table->json('countries')->nullable();

            // Cost / pricing inputs (PRIVATE cost columns).
            $table->decimal('cost_price_usd', 12, 4)->default(0);
            $table->decimal('airalo_min_price', 12, 4)->nullable();
            $table->decimal('markup_pct', 6, 3)->nullable();
            $table->decimal('override_markup_pct', 6, 3)->nullable();

            // Retail: engine-computed vs manual override; source columns first.
            $table->decimal('computed_retail_usd', 12, 4)->nullable();
            $table->decimal('manual_retail_usd', 12, 4)->nullable();

            // Generated: manual override wins, else the computed retail.
            $table->decimal('final_retail_usd', 12, 4)
                ->storedAs('COALESCE(manual_retail_usd, computed_retail_usd)');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esim_plans');
    }
};
