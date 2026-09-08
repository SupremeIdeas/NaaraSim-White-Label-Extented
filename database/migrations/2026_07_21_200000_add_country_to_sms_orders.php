<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sms_orders.country (admin analytics — owner request). Records the country a
 * number was bought for, so the admin overview can show "most-bought numbers by
 * country". Nullable for rows created before this column existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_orders', function (Blueprint $table) {
            $table->string('country')->nullable()->after('service_name');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::table('sms_orders', function (Blueprint $table) {
            $table->dropIndex(['country']);
            $table->dropColumn('country');
        });
    }
};
