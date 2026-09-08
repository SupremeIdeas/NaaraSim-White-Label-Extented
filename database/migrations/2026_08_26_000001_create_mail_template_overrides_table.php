<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email Studio template overrides (NAARA-BUILD-20 §3.1). Per-template admin edits
 * for the transactional emails — the underlying Blade file stays the real default,
 * so a template never breaks if no override exists; the mail path checks here first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_template_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->unique();
            $table->string('subject')->nullable();
            $table->string('heading')->nullable();
            $table->text('intro')->nullable();          // lead paragraph override (plain text)
            $table->string('button_text')->nullable();
            $table->string('accent_color', 9)->nullable(); // hex, e.g. #0A6E6E
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_template_overrides');
    }
};
