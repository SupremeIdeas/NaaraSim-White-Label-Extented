<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naara Gift catalogue — normalized gift-card products synced from BOTH Reloadly
 * (primary) and Zendit (failover), so the storefront and admin see one unified
 * catalogue. Provider cost data lives in a PRIVATE json column, never exposed;
 * `is_primary` marks the provider the storefront uses per brand+country
 * (Reloadly wins where both carry it). `admin_enabled` is our own on/off toggle,
 * independent of the provider's `enabled` flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_products', function (Blueprint $table) {
            $table->id();
            $table->string('provider');                 // reloadly | zendit
            $table->string('provider_product_id');
            $table->string('brand_key')->index();       // normalized brand slug for dedupe
            $table->string('brand_name');
            $table->string('country', 2)->nullable()->index();
            $table->string('currency', 3)->nullable();
            $table->string('denomination_type')->default('FIXED'); // FIXED | RANGE
            $table->json('fixed_denominations')->nullable();       // face values
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->string('logo_url')->nullable();
            $table->string('brand_color')->nullable();
            $table->string('category')->nullable();
            $table->json('required_fields')->nullable();           // dynamic checkout fields
            $table->text('redeem_instruction')->nullable();
            $table->json('cost_meta')->nullable();                 // PRIVATE — cost/discount/fee, never exposed
            $table->boolean('provider_enabled')->default(true);
            $table->boolean('admin_enabled')->default(true);
            $table->boolean('is_primary')->default(true);          // storefront uses primary per brand+country
            $table->boolean('featured')->default(false);
            $table->timestamps();

            $table->unique(['provider', 'provider_product_id']);
            $table->index(['is_primary', 'admin_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_products');
    }
};
