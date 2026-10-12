<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Privacy-safe counters only: no user id, no IP, no per-visitor rows.
        Schema::create('blog_reader_events', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('event', 24);              // share_open | share_click | share_copy | readnext_shown | readnext_clicked | readnext_dismissed | readnext_disabled
            $t->string('channel', 24)->default(''); // share channel key, or '' when not applicable
            $t->unsignedBigInteger('post_id')->default(0);
            $t->unsignedInteger('total')->default(0);
            $t->unique(['day', 'event', 'channel', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_reader_events');
    }
};
