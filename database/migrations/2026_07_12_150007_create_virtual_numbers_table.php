<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * virtual_numbers (blueprint Section 18.1). Permanent numbers on a monthly
 * billing model (Part 14.4). monthly_cost is PRIVATE (hidden on the model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('phone_number')->unique();
            $table->string('sid')->nullable();
            $table->json('capabilities')->nullable();
            $table->decimal('monthly_cost', 12, 4)->default(0);
            $table->decimal('monthly_retail', 18, 4)->default(0);
            $table->string('status')->default('active');
            $table->date('next_billing_date')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_numbers');
    }
};
