<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * custom_pages (admin CMS) — operator-authored pages with raw custom HTML,
 * served publicly at /{slug} inside the marketing shell. Managed only by admins.
 * Inline <script> is still blocked by the site CSP, so a custom page can style
 * and lay out freely without becoming a script-injection vector for visitors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('html')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('in_nav')->default(false);
            $table->boolean('full_width')->default(false); // render without the shell padding
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_pages');
    }
};
