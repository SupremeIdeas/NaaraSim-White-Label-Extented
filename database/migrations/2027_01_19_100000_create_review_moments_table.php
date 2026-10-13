<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Review moments: a person had a real, completed experience (an eSIM delivered, a code received, a gift card delivered...). The row is what lets the app ask them how it went, once, at a calm moment. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_moments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('moment', 40);                         // esim_first_purchase, sms_verification, ...
            $t->string('ref', 64);                            // the order / thread / run this is about
            $t->timestamp('qualified_at');                    // when the experience completed
            $t->timestamp('shown_at')->nullable();            // when the sheet was shown
            $t->unsignedTinyInteger('stars')->nullable();     // 1..5, a private quality signal
            $t->string('outcome', 12)->nullable();            // rated | dismissed
            $t->boolean('trustpilot_opened')->default(false);
            $t->timestamps();
            $t->unique(['user_id', 'moment', 'ref']);
            $t->index(['user_id', 'shown_at']);
            $t->index(['moment', 'qualified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_moments');
    }
};
