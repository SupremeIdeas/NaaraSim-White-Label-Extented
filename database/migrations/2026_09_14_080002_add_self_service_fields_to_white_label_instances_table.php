<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt 21 §2 + Prompt 21-EXT §1.3 — the second, merchant-self-service
 * acquisition path into the SAME WhiteLabelInstance model (extend, don't
 * fork it). Every existing row defaults to acquisition_method =
 * admin_provisioned, so nothing about the current admin-driven flow's data
 * is reinterpreted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->string('acquisition_method')->default('admin_provisioned')->after('status');
            $table->string('requested_tier')->nullable()->after('tier');
            $table->string('hosting_preference')->nullable()->after('requested_tier');
            $table->timestamp('hosting_disclaimer_acknowledged_at')->nullable()->after('hosting_preference');
            $table->decimal('price_usd', 10, 2)->nullable()->after('hosting_disclaimer_acknowledged_at');
            $table->string('payment_reference')->nullable()->after('price_usd');
            $table->foreignId('merchant_id')->nullable()->after('owner_user_id')->constrained('merchants')->nullOnDelete();
            // Prompt 21-EXT §1.3 — the plan the merchant actually picked in the
            // carousel; nullable because an admin-provisioned instance never
            // goes through the plan catalog at all.
            $table->foreignId('license_plan_id')->nullable()->after('merchant_id')
                ->constrained('white_label_license_plans')->nullOnDelete();

            $table->index(['acquisition_method', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('license_plan_id');
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn([
                'acquisition_method', 'requested_tier', 'hosting_preference',
                'hosting_disclaimer_acknowledged_at', 'price_usd', 'payment_reference',
            ]);
        });
    }
};
