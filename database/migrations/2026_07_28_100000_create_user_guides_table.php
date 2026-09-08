<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-audience user guides + agreement (normal user / merchant / merchant V2 /
 * developer). Each audience has an intro, an ordered set of sections (heading +
 * rich body + optional image), and an agreement block (pricing promise, rules &
 * regulations, the one-account policy). All admin-editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_guides', function (Blueprint $table) {
            $table->id();
            $table->string('audience')->unique();   // user | merchant | merchant_v2 | developer
            $table->string('title');
            $table->text('intro')->nullable();
            $table->json('sections')->nullable();   // [{heading, body(html), image}]
            $table->longText('agreement')->nullable(); // sanitized HTML: policy + rules + one-account warning
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_guides');
    }
};
