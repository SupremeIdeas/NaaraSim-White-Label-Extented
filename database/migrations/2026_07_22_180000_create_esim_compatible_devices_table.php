<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * eSIM device compatibility (esim_upgrade Part 2). One normalised table so the
 * compatibility modal's search, Apple/Android/Others pill tabs, and per-category
 * accordions all query the same source instead of hardcoded arrays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esim_compatible_devices', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('os_group');   // apple | android | others
            $table->string('category');   // phone | tablet | watch | laptop
            $table->string('device_name');
            $table->timestamps();

            $table->index(['os_group', 'category']);
            $table->index('device_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esim_compatible_devices');
    }
};
