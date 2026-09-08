<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client a Merchant V2 manages on behalf of — the client never has a NaaraSim
 * login; the merchant is the sole operator. Holds only the details needed to
 * administer that client's eSIM(s)/number(s).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->string('contact')->nullable();     // phone/email the merchant keeps
            $table->string('device')->nullable();      // device model / notes
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['merchant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_clients');
    }
};
