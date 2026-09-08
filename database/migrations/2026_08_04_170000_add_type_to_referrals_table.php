<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-7 §4: distinguish a normal customer referral from a merchant-to-merchant
 * referral on the SAME table (no second referrals table) so all existing
 * referral plumbing applies. Default 'customer' keeps every existing row intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('type', 16)->default('customer')->after('referred_id');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
