<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payout Guardian + engine safety (Addendum C §3). All additive.
 *
 * payout_requests gains the approval/review state the Guardian drives.
 * payout_provider_calls is a crash-safe INTENT LOG written before any provider
 * call, so a timeout or dead worker after submit can never be mistaken for a
 * failure (the double-pay fix). payout_decisions is append-only. The trust and
 * fingerprint tables back the soft signals and the destination-sharing gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->string('approval_source')->nullable()->after('approved_by');          // admin | system
            $table->string('review_state')->default('auto_pending')->after('approval_source');
            $table->string('hold_reason')->nullable()->after('review_state');
            $table->unsignedSmallInteger('risk_score')->nullable()->after('hold_reason');
            $table->timestamp('next_check_at')->nullable()->after('risk_score');
            $table->timestamp('evaluating_at')->nullable()->after('next_check_at');
            $table->timestamp('fx_locked_at')->nullable()->after('quote_expires_at');

            $table->index(['review_state', 'next_check_at']);
        });

        Schema::create('payout_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_request_id')->constrained('payout_requests')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('decision');                 // approve | defer | hold | reject
            $table->unsignedSmallInteger('score')->default(0);
            $table->json('rules')->nullable();          // [{id,result,weight,evidence}]
            $table->string('engine_version')->default('1');
            $table->boolean('shadow')->default(false);
            $table->string('decided_by')->default('system'); // system | admin:{id}
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamp('next_check_at')->nullable();

            $table->index('payout_request_id');
        });

        Schema::create('payout_provider_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_request_id')->constrained('payout_requests')->cascadeOnDelete();
            $table->string('provider');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('idempotency_key');          // = request.reference
            $table->string('state')->default('intent'); // intent|submitted|confirmed|unknown|definitively_failed
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('provider_ref')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('next_lookup_at')->nullable();
            $table->unsignedSmallInteger('lookup_attempts')->default(0);
            $table->unsignedSmallInteger('not_found_count')->default(0);
            $table->timestamp('first_not_found_at')->nullable();

            $table->unique(['payout_request_id', 'attempt']);
            $table->index(['state', 'next_lookup_at']);
        });

        Schema::create('payout_trust_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('tier')->default('new');    // new | trusted | vip
            $table->unsignedInteger('clean_payouts')->default(0);
            $table->timestamp('last_incident_at')->nullable();
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('override_reason')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('payout_account_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('fingerprint', 64);          // HMAC-SHA256 of the normalised destination
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payout_account_id')->nullable()->constrained('payout_accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'fingerprint', 'user_id'], 'payout_fp_unique');
            $table->index(['provider', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_account_fingerprints');
        Schema::dropIfExists('payout_trust_profiles');
        Schema::dropIfExists('payout_provider_calls');
        Schema::dropIfExists('payout_decisions');
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropIndex(['review_state', 'next_check_at']);
            $table->dropColumn(['approval_source', 'review_state', 'hold_reason', 'risk_score', 'next_check_at', 'evaluating_at', 'fx_locked_at']);
        });
    }
};
