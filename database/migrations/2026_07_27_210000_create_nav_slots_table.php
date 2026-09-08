<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-assignable floating-nav slots (Homepage floating-nav prompt §2). Each row
 * is one segment of the floating pill bar — its label, icon, target, who sees it
 * (all / logged-in / logged-out), and whether it's the glowing centerpiece.
 * Admin can repoint any slot to any page/feature without a code change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('label');
            $table->string('icon')->default('grid');
            $table->string('target');                 // route name, /path, external URL, or 'wizard'
            $table->string('visibility')->default('all'); // all | auth | guest
            $table->boolean('is_center')->default(false);  // the glowing centerpiece
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_slots');
    }
};
