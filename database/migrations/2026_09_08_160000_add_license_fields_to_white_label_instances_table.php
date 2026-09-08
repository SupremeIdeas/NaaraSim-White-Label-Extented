<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Updater — Batch 6 (License Authority + API Key/Token System). Batch 4
 * defined `white_label_instances` as a registry with status/tier/review-trail
 * columns but nothing populated them; this batch turns it into a real license
 * authority, so the table gains the credential columns the license flow writes.
 *
 * Two deliberately separate credentials (the whole point of this batch):
 *   - `license_key` — the DURABLE enrolment credential the buyer receives at
 *     purchase. It proves entitlement + tier; it is NOT a bearer token and grants
 *     no API access on its own. A deployed fork exchanges it, once, at the public
 *     activate endpoint for a Sanctum API token.
 *   - the Sanctum token (stored by Sanctum itself; we keep only
 *     `api_token_last_four` here for display, exactly like ApiClient.token_last_four)
 *     — the ROTATABLE operational credential the fork actually calls the API with.
 *
 * `license_revoked_at` is the permanent kill switch: a revoked key can never be
 * exchanged for a token again, distinct from a `suspended` status (temporary, the
 * same key can re-activate once restored). `tier` was already a column — this
 * batch is what finally sets it to a real value at issuance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->string('license_key')->nullable()->unique()->after('tier');
            $table->string('api_token_last_four', 4)->nullable()->after('license_key');
            $table->timestamp('license_issued_at')->nullable()->after('api_token_last_four');
            $table->timestamp('license_revoked_at')->nullable()->after('license_issued_at');
            $table->text('registration_note')->nullable()->after('license_revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->dropColumn([
                'license_key',
                'api_token_last_four',
                'license_issued_at',
                'license_revoked_at',
                'registration_note',
            ]);
        });
    }
};
