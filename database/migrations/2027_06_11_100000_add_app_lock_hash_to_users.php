<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'app_lock_hash')) {
            // The member's own "app password" (hashed). Opens the locked app together with the fingerprint; never the account password.
            Schema::table('users', function (Blueprint $t) {
                $t->string('app_lock_hash')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'app_lock_hash')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('app_lock_hash'));
        }
    }
};
