<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hardens the wallet double-credit/replay guard with a real DB constraint
 * (production-readiness audit, 2026-09). WalletService::apply()'s idempotency
 * check already looks up an existing transaction by (user_id, reference)
 * before writing a new one — this migration makes that assumption a real DB
 * constraint rather than relying solely on the app-level check + cache lock.
 *
 * Deliberately COMPOUND on (user_id, reference), never `reference` ALONE:
 * several call sites build a reference from a fixed/period string without a
 * user id baked in (e.g. batch billing runs), and those are only guaranteed
 * unique PER USER, not globally — a global unique constraint would reject a
 * second user's legitimate transaction that happens to share the same batch
 * reference. The existing plain index on `reference` is kept as-is: at least
 * one lookup (DisputeService, resolving a chargeback by gateway+reference
 * before the user is known) genuinely queries by reference alone.
 *
 * Guarded: if a database already has a violating pair (should not happen —
 * every write path was audited before this migration was written, and a
 * fresh check found zero duplicates), the migration fails loudly with the
 * exact rows to fix rather than silently skipping the constraint or silently
 * rewriting financial records.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('wallet_transactions')
            ->select('user_id', 'reference', DB::raw('COUNT(*) as c'))
            ->whereNotNull('reference')
            ->groupBy('user_id', 'reference')
            ->having('c', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $sample = $duplicates->take(5)->map(fn ($d) => "user_id={$d->user_id} reference={$d->reference} count={$d->c}")->implode('; ');
            throw new RuntimeException(
                "Cannot add a unique (user_id, reference) index — {$duplicates->count()} colliding pair(s) already exist. ".
                'Resolve these wallet_transactions rows first (do not delete money-movement records — disambiguate the '.
                "reference instead), then re-run: {$sample}"
            );
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unique(['user_id', 'reference'], 'wallet_transactions_user_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('wallet_transactions_user_reference_unique');
        });
    }
};
