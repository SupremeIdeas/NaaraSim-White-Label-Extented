<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual LPA activation fallback (blueprint Section 32). Stores the raw
 * LPA activation string (LPA:1$smdp$matchingid) that the QR encodes, so it can
 * be shown beside every QR for users who can't scan and must enter it manually.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esim_orders', function (Blueprint $table) {
            $table->string('lpa_string')->nullable()->after('qr_code_url');
        });
    }

    public function down(): void
    {
        Schema::table('esim_orders', function (Blueprint $table) {
            $table->dropColumn('lpa_string');
        });
    }
};
