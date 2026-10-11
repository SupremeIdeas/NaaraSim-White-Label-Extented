<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A person who texts STOP to a Naara Line must not be texted by that line again until they text START (carrier / CTIA compliance). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_opt_outs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('virtual_number_id')->constrained('virtual_numbers')->cascadeOnDelete();
            $t->string('peer', 32);                     // the other party's number, normalised
            $t->timestamp('opted_out_at');
            $t->unique(['virtual_number_id', 'peer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_opt_outs');
    }
};
