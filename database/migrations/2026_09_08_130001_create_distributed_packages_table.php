<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Updater — Batch 4 §4. One row per package the master platform makes
 * available to white-label instances. Built (Batch 1) and published are two
 * deliberate, separate steps: `is_published` is flipped on explicitly, so a
 * package can be built and privately tested against a staging white-label copy
 * before being offered broadly. The distribution check endpoint only ever
 * considers `is_published = true` rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributed_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_id')->unique(); // matches manifest.json's package_id
            $table->string('product');               // e.g. naarasim-whitelabel
            $table->string('version');
            $table->string('package_type');          // code, code_and_migrations, migrations, theme
            $table->string('min_compatible_version');
            $table->string('tier_requirement')->nullable();
            $table->text('changelog')->nullable();
            $table->string('storage_path');          // disk-relative path to the .naaraupdate file
            $table->unsignedBigInteger('size_bytes');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['is_published', 'product']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributed_packages');
    }
};
