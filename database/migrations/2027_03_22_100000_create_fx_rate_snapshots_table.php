<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Last-known-good exchange rates with their timestamp, kept in the database so a cache flush or a feed outage never loses them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rate_snapshots', function (Blueprint $t) {
            $t->id();
            $t->string('source', 24);
            $t->string('base', 8)->default('USD');
            $t->json('rates_json');
            $t->timestamp('fetched_at');
            $t->index('fetched_at', 'fxs_fetched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rate_snapshots');
    }
};
