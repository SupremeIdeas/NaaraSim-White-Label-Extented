<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * call_forwarding_rules (Live Voice — Part A). Inbound calls to a user's
 * permanent NaaraSim (Twilio) number forward to their real phone via a TwiML
 * <Dial>. One rule per provisioned number; the voice webhook reads it to decide
 * where to bridge the call. `forward_to_number` is the primary target,
 * `fallback_number` the no-answer/busy backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_forwarding_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('virtual_number_id')->nullable()->constrained('virtual_numbers')->nullOnDelete();
            $table->string('twilio_number');                 // the E.164 NaaraSim number (call target)
            $table->string('forward_to_number');             // where inbound calls go
            $table->string('fallback_number')->nullable();   // no-answer / busy backup
            $table->string('status')->default('active');     // active | inactive
            $table->timestamps();

            $table->unique('twilio_number');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_forwarding_rules');
    }
};
