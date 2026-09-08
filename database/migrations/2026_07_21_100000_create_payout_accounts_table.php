<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payout_accounts (ROADMAP §Layer 0.1 — the money-OUT foundation shared by
 * NaaraCredit cash-out and the merchant system). A destination the platform can
 * send settled earnings to. The `account_name` is NEVER user-typed — it is
 * resolved from the PSP (Paystack Resolve Account / Flutterwave Resolve Bank
 * Account) and stored read-only, so money can never go to a mistyped number.
 * A `provider_recipient_ref` caches the PSP transfer-recipient id once created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('bank');          // bank | mobile_money
            $table->char('country', 2);                       // ISO-3166 alpha-2
            $table->char('currency', 3);                      // ISO-4217
            $table->string('bank_code');                      // PSP bank/network code
            $table->string('bank_name')->nullable();          // display label for the code
            $table->string('account_number');
            $table->string('account_name');                   // RESOLVED, read-only
            $table->string('provider');                       // paystack | flutterwave | ...
            $table->string('provider_recipient_ref')->nullable();
            $table->boolean('is_verified')->default(false);   // name resolved successfully
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
