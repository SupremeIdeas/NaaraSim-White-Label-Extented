<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wizard_sessions (NaaraSim Wizard, roadmap §7). One row per user holds the
 * guided-purchase state so the widget can be minimised (e.g. to top up the
 * wallet) and resumed without losing progress. No money lives here — only the
 * chosen Model/country/service and the current step. Cleared on completion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wizard_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('step')->default('purpose');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wizard_sessions');
    }
};
