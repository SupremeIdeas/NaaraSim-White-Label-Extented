<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request (2026-09-15) — admin-editable reference links shown inside
 * the in-app Merchant White Label Guide (`MerchantWhiteLabel`). Lets the
 * admin point merchants at recommended hosting/domain providers and swap the
 * plain URL for the admin's own affiliate link at any time, with no code
 * change or redeploy — same "admin edits a row, the app just reflects it"
 * shape as `WhiteLabelLicensePlan`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_guide_links', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('label');
            $table->string('url');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_guide_links');
    }
};
