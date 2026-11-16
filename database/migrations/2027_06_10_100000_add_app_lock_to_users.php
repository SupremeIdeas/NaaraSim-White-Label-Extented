<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'app_lock_minutes')) {
            // null = app lock off; 0 = lock whenever the app is left; N = lock after N idle minutes.
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedSmallInteger('app_lock_minutes')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'app_lock_minutes')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('app_lock_minutes'));
        }
    }
};
