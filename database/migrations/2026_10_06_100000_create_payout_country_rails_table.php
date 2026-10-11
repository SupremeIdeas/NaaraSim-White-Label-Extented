<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rail Guide (Addendum B) G1: the SINGLE source of truth for "which rail covers which
 * country". The guide's matrix and the money path (bank resolvers, router) read the
 * same rows, so what the guide promises can never disagree with what the engine does.
 * `we_enabled` is NOT stored as truth: it is derived from the enabled corridors at read
 * time (a promise the router cannot keep is never shown as available).
 *
 * Seeded here from the constants the resolvers have always used, so nothing changes for
 * existing countries. Stripe/global coverage is seeded by `payouts:guide-seed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_country_rails', function (Blueprint $table) {
            $table->id();
            $table->char('country', 2);
            $table->string('rail');                                  // paystack|flutterwave|stripe_connect|paypal|cryptomus|global
            $table->boolean('provider_supports')->default(false);   // the provider itself covers this country
            $table->boolean('we_enabled')->default(false);          // informational snapshot; effective value is derived
            $table->json('methods')->nullable();                    // ["bank","mobile_money","wallet"]
            $table->char('currency', 3)->nullable();
            $table->unsignedSmallInteger('eta_min_hours')->nullable();
            $table->unsignedSmallInteger('eta_max_hours')->nullable();
            $table->string('note_key')->nullable();                 // lang key for a country-specific caution
            $table->string('source_url')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by')->nullable();              // "api" | "seed" | admin:{id}
            $table->string('admin_override', 12)->default('none');  // none|force_on|force_off
            $table->timestamps();

            $table->unique(['country', 'rail']);
            $table->index('rail');
        });

        $paystack = ['NG', 'GH', 'ZA', 'KE', 'CI', 'EG'];
        $flutterwave = ['NG', 'GH', 'KE', 'UG', 'TZ', 'ZA', 'RW', 'ZM', 'CI', 'SN', 'CM', 'EG'];
        $now = now();
        foreach (['paystack' => $paystack, 'flutterwave' => $flutterwave] as $rail => $countries) {
            foreach ($countries as $c) {
                DB::table('payout_country_rails')->insertOrIgnore([
                    'country' => $c, 'rail' => $rail, 'provider_supports' => true, 'methods' => json_encode(['bank']),
                    'verified_by' => 'seed', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_country_rails');
    }
};
