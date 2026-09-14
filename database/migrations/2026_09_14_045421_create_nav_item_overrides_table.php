<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-configurable bottom navigation (owner request): lets an admin reorder
 * or replace which items appear in the main bottom bar / More sheet, and in
 * the Numbers section's own bottom bar — without a deploy. Stores only a
 * catalog KEY (a route name) + position + active flag; label/icon/badge and
 * — critically — whether the item is even ELIGIBLE for a given viewer (role,
 * feature flag, entitlement lock) always come fresh from
 * App\Support\BottomNav's catalog, never from this table, so an override can
 * reorder or hide an eligible item but can never expose a link a viewer isn't
 * actually allowed to use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nav_item_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('nav', 16); // 'main' | 'numbers'
            $table->string('item_key', 60); // catalog key — the route name
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['nav', 'item_key']);
            $table->index(['nav', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_item_overrides');
    }
};
