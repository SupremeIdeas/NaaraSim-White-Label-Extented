<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the NaaraSim profile/lifecycle columns to the users table
 * (blueprint Section 18.1). 2FA is already covered by Fortify's
 * two_factor_secret column, so twofa_secret is intentionally not duplicated.
 * Authorization roles are owned by Spatie Permission; the `role` column here
 * mirrors the schema for quick display/filtering only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('country_code', 2)->nullable()->after('phone');
            $table->string('referral_code')->nullable()->unique()->after('country_code');
            $table->unsignedBigInteger('referred_by')->nullable()->index()->after('referral_code');
            $table->enum('kyc_status', ['unverified', 'pending', 'verified', 'rejected'])
                ->default('unverified')->after('referred_by');
            $table->boolean('is_active')->default(true)->after('kyc_status');
            $table->string('role')->default('user')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the indexes before their columns so the SQLite table rebuild
            // (and MySQL) don't leave a dangling index reference.
            $table->dropUnique('users_referral_code_unique');
            $table->dropIndex('users_referred_by_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'country_code',
                'referral_code',
                'referred_by',
                'kyc_status',
                'is_active',
                'role',
            ]);
        });
    }
};
