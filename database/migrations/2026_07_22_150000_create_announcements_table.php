<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin announcements / offers (owner request). An admin composes an offer or
 * update once; it fans out to every active user's in-app notification bell so
 * everyone sees it and can act (a CTA, optionally a coupon they can claim). This
 * table is the record of what was sent, to how many, and by whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('icon')->default('gift');
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('coupon_code')->nullable();       // optional claimable offer
            $table->string('audience')->default('all');        // all (active users)
            $table->unsignedInteger('recipients')->default(0); // fan-out count
            $table->string('status')->default('draft');        // draft|sending|sent
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
