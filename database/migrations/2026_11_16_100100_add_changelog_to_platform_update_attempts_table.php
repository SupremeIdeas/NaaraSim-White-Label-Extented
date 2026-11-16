<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_update_attempts') && ! Schema::hasColumn('platform_update_attempts', 'changelog')) {
            Schema::table('platform_update_attempts', function (Blueprint $table) {
                // What the update changed, copied from the package so the update history explains itself.
                $table->text('changelog')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platform_update_attempts', 'changelog')) {
            Schema::table('platform_update_attempts', function (Blueprint $table) {
                $table->dropColumn('changelog');
            });
        }
    }
};
