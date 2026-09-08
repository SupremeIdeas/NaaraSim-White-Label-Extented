<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * order_logs (blueprint Section 18.2) — per-order profit tracking that powers
 * analytics. provider_cost and profit are PRIVATE (hidden on the model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('naarasim_plan_id')->nullable()->index();
            $table->string('provider');
            $table->decimal('provider_cost', 12, 4)->default(0);
            $table->decimal('charged_to_user', 18, 4)->default(0);
            $table->decimal('profit', 12, 4)->default(0);
            $table->decimal('profit_pct', 8, 3)->nullable();
            $table->string('result')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_logs');
    }
};
