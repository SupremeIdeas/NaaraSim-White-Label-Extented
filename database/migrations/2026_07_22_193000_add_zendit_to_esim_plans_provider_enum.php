<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add "zendit" to the esim_plans.provider enum — the Naara Connect line (Full
 * eSIMs: calls + data) is fulfilled by Zendit alongside the data-only trio.
 */
return new class extends Migration
{
    private const PROVIDERS = ['esimgo', 'airalo', 'quibity', 'zendit'];

    public function up(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->enum('provider', self::PROVIDERS)->change();
        });
    }

    public function down(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->enum('provider', ['esimgo', 'airalo', 'quibity'])->change();
        });
    }
};
