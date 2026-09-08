<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the three additional Full-eSIM (Naara Connect) providers to the
 * esim_plans.provider enum: 1GLOBAL, Monty Mobile and Gigs — joining Zendit in
 * the voice+data lane alongside the data-only trio.
 */
return new class extends Migration
{
    private const PROVIDERS = ['esimgo', 'airalo', 'quibity', 'zendit', 'oneglobal', 'montymobile', 'gigs'];

    public function up(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->enum('provider', self::PROVIDERS)->change();
        });
    }

    public function down(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->enum('provider', ['esimgo', 'airalo', 'quibity', 'zendit'])->change();
        });
    }
};
