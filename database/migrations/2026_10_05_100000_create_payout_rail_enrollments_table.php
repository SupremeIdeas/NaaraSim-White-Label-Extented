<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funding Radar (Addendum A) R1: who CHOSE a global payout rail. The enrollment is the
 * tracking record; `payout_accounts` stays the money-path record. Users who merely live
 * in an unserved country are NOT enrolled (that is "unserved demand", tracked apart).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_rail_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider');
            $table->char('country', 2);
            $table->char('currency', 3)->nullable();
            $table->string('status', 16)->default('selected'); // selected|onboarding|active|paused|declined
            $table->boolean('stripe_connect_eligible')->default(false);
            $table->string('ineligible_reason')->nullable();    // country_not_supported|onboarding_declined|recipient_agreement_unsupported|user_choice
            $table->foreignId('payout_account_id')->nullable()->constrained('payout_accounts')->nullOnDelete();
            $table->timestamp('selected_at')->useCurrent();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_status_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['provider', 'status']);
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_rail_enrollments');
    }
};
