<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUILD-9 §6.6: an auditable log of every priority-score adjustment — so a
 * boosted placement is always explainable ("why is this brand boosted?").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_priority_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handle_id')->nullable()->constrained('brand_partner_handles')->nullOnDelete();
            $table->string('month', 7); // YYYY-MM (the evaluated billing month)
            $table->unsignedInteger('guaranteed')->default(0);
            $table->unsignedInteger('actual')->default(0);
            $table->integer('adjustment')->default(0);
            $table->integer('new_priority_score')->default(0);
            $table->timestamps();

            $table->index(['brand_partner_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_priority_log');
    }
};
