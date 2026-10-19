<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The "Menu columns" dial: how many icons per row the More menu shows (3 by default, 4 on request). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_appearance') && ! Schema::hasColumn('user_appearance', 'menucols')) {
            Schema::table('user_appearance', fn (Blueprint $t) => $t->string('menucols', 12)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_appearance', 'menucols')) {
            Schema::table('user_appearance', fn (Blueprint $t) => $t->dropColumn('menucols'));
        }
    }
};
