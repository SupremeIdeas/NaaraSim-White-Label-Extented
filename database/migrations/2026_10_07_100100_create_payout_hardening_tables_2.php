<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addendum D hardening (D-H2/D-H3). Additive and reversible.
 *
 *  - payout_requests.provider_reference: the provider-facing id (never our internal prefixed
 *    reference, which contains characters providers reject) — D-3.14.
 *  - payout_corridors.fixed_fee_usd_est: admin-only estimate used by the economics guard — D-3.15.
 *  - payout_accounting_entries: append-only double-entry trail — D-3.11.
 *  - payout_reconciliation_runs / _items: provider-statement matching — D-3.12.
 *  - payee_tax_profiles: reporting hooks only — D-3.18.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->string('provider_reference', 64)->nullable()->unique()->after('reference');
        });

        Schema::table('payout_corridors', function (Blueprint $table) {
            $table->decimal('fixed_fee_usd_est', 8, 2)->nullable()->after('est_provider_cost_bps'); // ADMIN ONLY
        });

        Schema::create('payout_accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('entry_group')->index();
            $table->foreignId('payout_request_id')->nullable()->constrained('payout_requests')->nullOnDelete();
            $table->unsignedBigInteger('float_movement_id')->nullable();
            $table->string('account_code', 40);                       // earnings_liability, payout_in_transit, provider_float_x, …
            $table->string('direction', 6);                           // debit | credit
            $table->decimal('amount_usd', 18, 4);
            $table->char('currency', 3)->nullable();
            $table->decimal('amount_local', 18, 4)->nullable();
            $table->decimal('fx_rate', 18, 8)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->string('memo')->nullable();
            $table->string('posting_key', 120)->nullable()->unique(); // idempotency: group:request:event
            $table->index(['account_code', 'occurred_at']);
        });

        Schema::create('payout_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->date('period_from');
            $table->date('period_to');
            $table->string('source', 20)->default('csv');             // csv | api
            $table->unsignedInteger('matched')->default(0);
            $table->unsignedInteger('flagged')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payout_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('payout_reconciliation_runs')->cascadeOnDelete();
            $table->string('kind', 24);   // matched | missing_at_provider | missing_in_system | amount_mismatch | fee_unrecorded
            $table->foreignId('payout_request_id')->nullable()->constrained('payout_requests')->nullOnDelete();
            $table->string('provider_reference')->nullable();
            $table->decimal('our_amount', 18, 4)->nullable();
            $table->decimal('provider_amount', 18, 4)->nullable();
            $table->decimal('provider_fee', 18, 4)->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->index(['run_id', 'kind']);
        });

        // "This wasn't me" / admin freeze: a frozen payee cannot create or send payouts (D-3.5).
        Schema::create('payout_user_freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reason', 40)->default('not_me');     // not_me | admin | returned_twice
            $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('frozen_at')->useCurrent();
            $table->timestamp('released_at')->nullable();
        });

        Schema::create('payee_tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('tax_country', 2)->nullable();
            $table->string('form_type', 20)->nullable();              // W-9 | W-8BEN | other
            $table->string('form_status', 20)->default('none');       // none | requested | received | expired
            $table->timestamp('collected_at')->nullable();
            $table->boolean('provider_collected')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payee_tax_profiles');
        Schema::dropIfExists('payout_user_freezes');
        Schema::dropIfExists('payout_reconciliation_items');
        Schema::dropIfExists('payout_reconciliation_runs');
        Schema::dropIfExists('payout_accounting_entries');
        Schema::table('payout_corridors', function (Blueprint $table) {
            $table->dropColumn('fixed_fee_usd_est');
        });
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropUnique(['provider_reference']);
            $table->dropColumn('provider_reference');
        });
    }
};
