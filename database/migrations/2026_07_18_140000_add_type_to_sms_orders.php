<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record the number type (otp | rental | permanent) on each order so the
 * dashboard can group + badge numbers by their public Model (Naara Verify /
 * Rent / Line) deterministically. Nullable so existing rows stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_orders', function (Blueprint $table) {
            $table->string('type', 12)->nullable()->after('service_name');
        });
    }

    public function down(): void
    {
        Schema::table('sms_orders', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
