<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favourite flag for the contact book (Numbers V6 §5 — the iOS favourites row).
 * A real column so the favourites strip isn't fake UI; defaults to false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('phone_number')->index();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('is_favorite');
        });
    }
};
