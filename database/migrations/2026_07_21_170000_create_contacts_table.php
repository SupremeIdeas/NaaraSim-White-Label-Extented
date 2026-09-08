<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * contacts (Live Voice — Part C: in-app contact book). A simple per-user address
 * book that feeds the dialer's destination field — "tap a name, call it". Not a
 * provider-billed feature, so no feature gate; just auth-scoped CRUD. Unique on
 * (user_id, phone_number) so CSV/vCard re-imports update rather than duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone_number');
            $table->timestamps();

            $table->unique(['user_id', 'phone_number']);
            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
