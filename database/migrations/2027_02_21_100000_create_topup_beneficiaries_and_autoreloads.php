<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topup_beneficiaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('phone_e164', 20);
            $t->string('country_iso', 2);
            $t->string('operator_name', 80)->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'phone_e164']);
        });

        Schema::create('topup_autoreloads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('beneficiary_id')->constrained('topup_beneficiaries')->cascadeOnDelete();
            $t->string('country_iso', 2);
            $t->string('operator_name', 80);
            $t->string('product_type', 8);                  // airtime | data
            $t->decimal('send_amount', 14, 2);
            $t->string('send_currency', 3);
            $t->string('product_name', 160)->nullable();    // a data bundle is matched again by name/size at run time
            $t->string('frequency', 8);                     // daily | weekly | monthly
            $t->unsignedTinyInteger('run_hour')->default(9);
            $t->unsignedTinyInteger('run_weekday')->nullable();   // 0 (Sun) .. 6
            $t->unsignedTinyInteger('run_monthday')->nullable();  // 1 .. 28
            $t->string('timezone', 40)->default('UTC');
            $t->timestamp('next_run_at')->nullable();
            $t->timestamp('last_run_at')->nullable();
            $t->foreignId('last_order_id')->nullable()->constrained('topup_orders')->nullOnDelete();
            $t->string('last_status', 24)->nullable();
            $t->unsignedSmallInteger('runs')->default(0);
            $t->unsignedTinyInteger('failures')->default(0);
            $t->boolean('active')->default(true);
            $t->string('paused_reason', 160)->nullable();
            $t->timestamp('low_balance_notified_at')->nullable();
            $t->timestamps();
            $t->index(['active', 'next_run_at']);
        });

        // What past successful top-ups taught us about a number (portability): outranks the prefix table.
        Schema::create('topup_number_operators', function (Blueprint $t) {
            $t->id();
            $t->string('phone_e164', 20)->unique();
            $t->string('country_iso', 2);
            $t->string('operator_name', 80);
            $t->unsignedInteger('confirmations')->default(1);
            $t->timestamp('last_confirmed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topup_number_operators');
        Schema::dropIfExists('topup_autoreloads');
        Schema::dropIfExists('topup_beneficiaries');
    }
};
