<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * merchants (ROADMAP §Layer 3 — reseller system). A KYB-verified user runs a
 * co-branded storefront under NaaraSim, reselling everything at an admin-set
 * reseller margin. The merchant NEVER sets their own markup; the reseller margin
 * lives in settings/per-merchant and MarginGuard still floors every price. Their
 * customers are linked via users.merchant_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('business_name');
            $table->string('slug')->unique();
            $table->string('logo_url')->nullable();
            $table->string('brand_color', 9)->nullable();     // co-brand accent (#RRGGBB)
            $table->string('status')->default('pending');     // pending | active | suspended | rejected
            // Optional per-merchant reseller margin override (%); null = use the
            // global/per-service admin setting. Admin-set only — never the merchant.
            $table->decimal('reseller_margin_pct', 6, 3)->nullable();
            $table->text('reason')->nullable();               // rejection/suspension note
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // A customer permanently belongs to the merchant whose invite they used.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->after('referred_by')
                ->constrained('merchants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_id');
        });
        Schema::dropIfExists('merchants');
    }
};
