<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.display_currency (owner request — localized pricing). The currency a
 * user sees prices in (USD stays the settlement/default). Nullable — resolved
 * from their country_code / location when unset. Display-only; it never changes
 * what the wallet actually holds or how money is settled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_currency', 4)->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('display_currency');
        });
    }
};
