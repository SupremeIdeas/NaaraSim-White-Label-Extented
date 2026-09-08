<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Universal Section Builder (Section Builder prompt §2–3). Foundation layer that
 * every future landing page — marketing or in-dashboard — can be assembled from,
 * instead of each feature maintaining its own bespoke admin CMS.
 *
 * `page_sections` holds the editable DRAFT rows for a page (one row per section,
 * ordered), so the admin can add / reorder / configure without a deploy. The
 * public site never reads these directly — it reads the last PUBLISHED snapshot
 * (see page_section_versions), so draft edits stay invisible until published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('page_key')->index();  // 'home', 'about', or a custom slug
            $table->string('type');               // one of SectionLibrary::types()
            $table->json('config')->nullable();   // per-type config (validated per-type)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
