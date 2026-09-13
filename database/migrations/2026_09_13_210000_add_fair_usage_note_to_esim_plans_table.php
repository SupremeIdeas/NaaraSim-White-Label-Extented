<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->string('fair_usage_note', 300)->nullable()->after('ai_tooltip_override');
        });
    }

    public function down(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->dropColumn('fair_usage_note');
        });
    }
};
