<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pricing_engine_logs (blueprint Section 18.2) — audits every price
 * calculation, including which guard (Airalo min / MarginGuard) fired and by
 * how much it adjusted the retail (guard_delta). Written by PricingEngine
 * (Module 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_engine_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->string('provider');
            $table->decimal('cost_price', 12, 4)->default(0);
            $table->decimal('markup_used', 6, 3)->nullable();
            $table->decimal('computed_retail', 12, 4)->nullable();
            $table->decimal('final_retail', 12, 4)->nullable();
            $table->enum('guard_active', ['none', 'airalo_min', 'margin_guard'])->default('none');
            $table->decimal('guard_delta', 12, 4)->default(0);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_engine_logs');
    }
};
