<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant V2 — a client-management tier on top of the existing reseller
 * program. `standard` is the current merchant; `v2` adds managing eSIMs/numbers
 * on behalf of clients who never log in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('tier')->default('standard')->after('status'); // standard | v2
            $table->timestamp('upgraded_at')->nullable()->after('tier');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['tier', 'upgraded_at']);
        });
    }
};
