<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recycled-number pre-check (Prompt 11): permanent numbers we've pulled for
 * abuse/complaint must never be re-provisioned to another user. Matched on
 * `msisdn` (digits only) so provider formatting differences can't slip a
 * blocked number back into the catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->unique();       // digits-only match key
            $table->string('phone_number');           // display E.164
            $table->string('provider')->nullable();   // PRIVATE — never surfaced
            $table->string('reason')->nullable();
            $table->string('source')->default('admin'); // admin | system
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_numbers');
    }
};
