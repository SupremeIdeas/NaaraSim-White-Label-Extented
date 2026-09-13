<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US/Canada port-in intake (Prompt 11): a customer's request to bring an
 * existing number to Naara. Honest by design — bringing a number in is a
 * multi-day, human-reviewed carrier process (the audit), never instant
 * provisioning, so this is an intake + status-tracking record, not a live
 * order. account_number/pin are the losing-carrier secrets, stored encrypted
 * and purged once the request closes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('port_in_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');                 // the +1 number to bring in
            $table->string('status')->default('submitted'); // submitted|in_review|submitted_to_carrier|completed|rejected
            $table->text('account_number')->nullable();      // encrypted — losing-carrier account #
            $table->text('pin')->nullable();                 // encrypted — losing-carrier port PIN
            $table->string('billing_name');
            $table->string('billing_address');
            $table->text('notes')->nullable();               // customer notes
            $table->text('admin_notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('provider')->nullable();          // ops-set target provider (PRIVATE)
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('port_in_requests');
    }
};
