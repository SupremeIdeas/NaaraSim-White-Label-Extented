<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * error_logs (blueprint Section 18.2 / Part 17.5) — the in-app error log that
 * backs the admin error-log module and its dated file archive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->index();
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('severity')->default('error')->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
