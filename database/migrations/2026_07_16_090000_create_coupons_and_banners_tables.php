<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 31 (pulled forward) — promo banners + margin-protected coupons.
 * Coupons discount RETAIL only; the CouponEngine clamps every discounted
 * price to cost + minimum profit so a code can never sell below wholesale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->decimal('percent_off', 5, 2);            // 1.00 – 90.00
            $table->string('applies_to', 20)->default('all'); // all | esim | number
            $table->unsignedInteger('max_redemptions')->nullable(); // null = unlimited
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('times_redeemed')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product', 20);                 // esim | number
            $table->string('reference');                   // order reference
            $table->decimal('list_price', 12, 4);          // retail before discount
            $table->decimal('paid_price', 12, 4);          // retail actually charged
            $table->decimal('amount_saved', 12, 4);
            $table->boolean('floor_clamped')->default(false); // MarginGuard trimmed it
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('placement', 30)->index();      // dashboard_home | menu_sheet | account
            $table->string('image_url');                   // desktop / default artwork
            $table->string('image_url_mobile')->nullable();// optional small-screen artwork
            $table->string('link_url')->nullable();        // internal path or external URL
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
