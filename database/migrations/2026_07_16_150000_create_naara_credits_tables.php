<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NaaraCredits loyalty currency + rewards (loyalty module).
 * Credits are a separate currency from the money wallet (default 100 = $1),
 * earned via safe tasks (signup, daily check-in, first purchase) and
 * postback-verified rewarded ads, spendable at checkout — always margin-guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Credit balance lives beside the money wallet but is NOT money.
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->decimal('naara_credits', 14, 2)->default(0)->after('total_spent');
            $table->timestamp('last_checkin_at')->nullable()->after('naara_credits');
        });

        // Every credit movement — the audit trail (mirrors wallet_transactions
        // but for credits, kept separate so money accounting stays clean).
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);                  // earn | spend | adjust
            $table->string('source', 32);                // signup | checkin | first_purchase | ad_reward | admin | redeem
            $table->decimal('amount', 14, 2);            // always positive; direction from type
            $table->decimal('balance_after', 14, 2);
            $table->string('reference')->nullable();     // idempotency key per source event
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'reference']);
            $table->index(['user_id', 'created_at']);
        });

        // Postback-verified rewarded-ad / offerwall completions. The reward is
        // credited ONLY when the ad network's server confirms a real view here.
        Schema::create('ad_reward_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('offerwall');
            $table->string('external_txn_id')->unique();  // network's txn id — postback idempotency
            $table->decimal('credits', 14, 2);
            $table->decimal('payout_usd', 12, 4)->nullable(); // what the network paid us (admin insight; never shown to users)
            $table->string('status', 12)->default('credited'); // credited | rejected
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_reward_views');
        Schema::dropIfExists('credit_ledger');
        Schema::table('user_wallets', function (Blueprint $table) {
            $table->dropColumn(['naara_credits', 'last_checkin_at']);
        });
    }
};
