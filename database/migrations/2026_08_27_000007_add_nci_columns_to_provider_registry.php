<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NAARA-BUILD-16 — NCI's own columns on provider_registry, clearly distinct from
 * the live-truth fields Layer 1/2 write. NCI (Layer 3) is the ONLY writer to
 * these five; it never writes any other column, and no other layer writes these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_registry', function (Blueprint $table) {
            $table->float('nci_score')->nullable();          // 0–1 rolling reliability
            $table->float('nci_confidence')->nullable();     // 0–1, from sample size
            $table->string('nci_risk_rating')->nullable();   // low | medium | high
            $table->timestamp('nci_computed_at')->nullable();
            $table->unsignedInteger('nci_sample_size')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('provider_registry', function (Blueprint $table) {
            $table->dropColumn(['nci_score', 'nci_confidence', 'nci_risk_rating', 'nci_computed_at', 'nci_sample_size']);
        });
    }
};
