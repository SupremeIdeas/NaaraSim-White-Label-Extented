<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request: let an admin attach an image to a Journey Goal, the same
 * upload pattern eSIM country/region images already use (MediaStorage +
 * a nullable path column). Separate from the pre-existing `icon` column
 * (an SVG-sprite name) — an uploaded image, when set, takes precedence over
 * the icon badge on the customer-facing My Journey card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journey_goals', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('journey_goals', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
