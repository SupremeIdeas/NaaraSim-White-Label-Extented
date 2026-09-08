<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Email Studio broadcast send history (NAARA-BUILD-20 §3.2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body_html');
            $table->string('audience_type');
            $table->string('audience_value')->nullable();
            $table->string('audience_label');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('status')->default('queued'); // queued | sent | failed
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_broadcasts');
    }
};
