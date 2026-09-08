<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extended profile fields (owner request). Lets users who care build a solid
 * profile — bio, location, DOB, language/timezone preferences. All optional and
 * self-service; none affect money or auth. phone / country_code / avatar /
 * display_currency already exist from earlier migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('avatar');
            $table->string('city')->nullable()->after('bio');
            $table->string('address_line')->nullable()->after('city');
            $table->string('postal_code', 32)->nullable()->after('address_line');
            $table->date('date_of_birth')->nullable()->after('postal_code');
            $table->string('language', 8)->nullable()->after('date_of_birth');
            $table->string('timezone', 64)->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'city', 'address_line', 'postal_code', 'date_of_birth', 'language', 'timezone']);
        });
    }
};
