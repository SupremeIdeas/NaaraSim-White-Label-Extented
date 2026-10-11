<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Payout Layer — Phase 1 additive columns. Existing rows and every existing
 * code path are untouched (all new columns are nullable / defaulted).
 *
 * payout_accounts: `method`, `corridor_id`, `provider_status`, `details` (per-country
 *   bank fields, encrypted-json by the model cast), `payee_kyc_name` (the legal name
 *   the name-match gate compares), `lookup_hash` (blind index of the account number so
 *   the number itself can later be encrypted at rest without losing lookups).
 * payout_requests: the locked quote — `usd_amount`, `fx_rate`, `platform_fee_usd`,
 *   `corridor_id`, `quote_expires_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->string('method')->default('bank')->after('type');
            $table->foreignId('corridor_id')->nullable()->after('method')->constrained('payout_corridors')->nullOnDelete();
            $table->string('provider_status')->nullable()->after('provider_recipient_ref');
            $table->text('details')->nullable()->after('provider_status');
            $table->string('payee_kyc_name')->nullable()->after('account_name');
            $table->string('lookup_hash', 64)->nullable()->after('account_number')->index();
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->decimal('usd_amount', 18, 4)->nullable()->after('currency');
            $table->decimal('fx_rate', 18, 8)->nullable()->after('usd_amount');
            $table->decimal('platform_fee_usd', 12, 4)->default(0)->after('fx_rate');
            $table->foreignId('corridor_id')->nullable()->after('payout_account_id')->constrained('payout_corridors')->nullOnDelete();
            $table->timestamp('quote_expires_at')->nullable()->after('settled_at');
        });
    }

    public function down(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corridor_id');
            $table->dropColumn(['usd_amount', 'fx_rate', 'platform_fee_usd', 'quote_expires_at']);
        });
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corridor_id');
            $table->dropColumn(['method', 'provider_status', 'details', 'payee_kyc_name', 'lookup_hash']);
        });
    }
};
