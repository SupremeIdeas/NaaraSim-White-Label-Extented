<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * settings (blueprint Section 18.2) — all platform config incl. pricing config
 * and provider keys. `value` is encrypted at rest (encrypted:array cast on the
 * model), so the column is longText rather than a native JSON type. is_public
 * governs whether a setting may be exposed to the frontend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('group')->nullable()->index();
            $table->string('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
