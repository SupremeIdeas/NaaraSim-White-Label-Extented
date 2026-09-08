<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-9 §2.3: the billing record for a self-service brand listing. Monthly
 * charges reuse the Naara Line renewal pattern (idempotent per month).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('brand_subscription_plans');
            $table->string('status', 16)->default('active'); // active | past_due | cancelled
            $table->timestamp('started_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('last_charged_at')->nullable();
            $table->unsignedInteger('grace_reminders_sent')->default(0);
            $table->timestamps();

            $table->index(['status', 'next_billing_at']);
            $table->index('brand_partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_subscriptions');
    }
};
