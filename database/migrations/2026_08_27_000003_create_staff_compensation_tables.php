<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff profit-share compensation (NAARA-BUILD-23). A staff member shares a set
 * percentage of the SAME net platform profit partners share in
 * (PlatformProfitService), accrued monthly to a real earnings ledger they cash
 * out through the shared payout system.
 *
 * effective_from guarantees a rate change is NEVER retroactive: a new percentage
 * only applies to periods starting on or after that date, so an already-computed
 * or already-paid month is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_compensation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('profit_share_pct', 6, 3)->default(0); // admin-set
            $table->boolean('is_active')->default(false);
            $table->date('effective_from');                        // rate applies from here on
            $table->timestamps();
        });

        Schema::create('staff_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // the staff member
            $table->string('type');                     // accrual | hold | release
            $table->decimal('amount', 12, 4);           // signed
            $table->decimal('balance_after', 12, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('reference')->unique();      // idempotency key
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_earnings');
        Schema::dropIfExists('staff_compensation_profiles');
    }
};
