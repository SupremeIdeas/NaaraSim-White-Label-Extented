<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addendum D hardening (D-H1). All additive and reversible.
 *
 *  - payout_requests.destination_snapshot (encrypted) + destination_digest: what the money is
 *    going TO is frozen at request time, so editing/deleting the account afterwards can never
 *    redirect an already-evaluated payout (G-05).
 *  - payout_webhook_events: one row per provider event, unique(provider, provider_event_id), so a
 *    duplicate delivery is a no-op (G-08). Raw payloads are encrypted and pruned after a TTL.
 *  - payout_invariant_runs: results of the read-only money-invariants checker (G-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->longText('destination_snapshot')->nullable()->after('reference');
            $table->string('destination_digest', 64)->nullable()->after('destination_snapshot');
        });

        Schema::create('payout_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('provider_event_id', 191);
            $table->string('request_reference')->nullable()->index();
            $table->string('event_type')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->string('outcome', 60)->nullable();       // applied | ignored | unknown_reference | conflict
            $table->string('payload_hash', 64);
            $table->longText('raw_payload')->nullable();      // encrypted, pruned after the TTL
            $table->unique(['provider', 'provider_event_id']);
            $table->index('received_at');
        });

        Schema::create('payout_invariant_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('running'); // ok | violations
            $table->unsignedInteger('violation_count')->default(0);
            $table->json('results')->nullable();              // [{id, name, ok, offenders:[...]}]
            $table->string('triggered_by', 60)->default('schedule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_invariant_runs');
        Schema::dropIfExists('payout_webhook_events');
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropColumn(['destination_snapshot', 'destination_digest']);
        });
    }
};
