<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag an eSIM / number order with the MerchantClient it was bought FOR (V2). A
 * client can hold many orders over time — a proper one-to-many, not a single
 * "current" field — so history and concurrent assignments both work.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['esim_orders', 'sms_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('merchant_client_id')->nullable()->after('user_id')
                    ->constrained('merchant_clients')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['esim_orders', 'sms_orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('merchant_client_id');
            });
        }
    }
};
