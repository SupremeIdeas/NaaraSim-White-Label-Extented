<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crypto top-up through WalletConnect. Additive and idempotent: every table is guarded, every index and foreign key has an explicit
 * short name (MySQL allows 64 characters; the migration-name test enforces it), and nothing existing is touched. Money is stored as strings or decimals,
 * never floats; chain amounts are decimal strings because a uint256 does not fit a 64-bit integer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crypto_chains')) {
            Schema::create('crypto_chains', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('chain_id');
                $t->string('name', 60);
                $t->string('family', 12)->default('evm');
                $t->unsignedSmallInteger('confirmations')->default(64);
                $t->string('finality_mode', 12)->default('finalized');   // finalized | depth
                $t->unsignedInteger('max_log_range')->default(2000);
                $t->boolean('enabled')->default(false);
                $t->boolean('credit_enabled')->default(false);
                $t->string('explorer_tx_url', 190)->nullable();
                $t->timestamps();
                $t->unique('chain_id', 'onc_chain_id_uq');
            });
        }
        if (! Schema::hasTable('crypto_rpc_endpoints')) {
            Schema::create('crypto_rpc_endpoints', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('chain_id');
                $t->string('role', 10);                        // primary | secondary
                $t->text('url_encrypted');                     // RPC URLs carry API keys: encrypted at rest
                $t->boolean('enabled')->default(true);
                $t->timestamp('last_ok_at')->nullable();
                $t->text('last_error')->nullable();
                $t->unsignedBigInteger('last_head')->nullable();
                $t->timestamps();
                $t->index('chain_id', 'onrpc_chain_idx');
            });
        }
        if (! Schema::hasTable('crypto_tokens')) {
            Schema::create('crypto_tokens', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('chain_id');
                $t->string('symbol', 8);                       // USDT | USDC
                $t->char('contract', 42);                      // lower-case 0x address
                $t->unsignedTinyInteger('decimals');
                $t->decimal('peg_multiplier', 10, 4)->default(1.0000);
                $t->decimal('min_usd', 14, 4)->default(5);
                $t->decimal('max_single_usd', 14, 4)->default(2000);
                $t->decimal('user_daily_cap_usd', 14, 4)->default(5000);
                $t->decimal('review_above_usd', 14, 4)->default(500);
                $t->boolean('enabled')->default(false);
                $t->timestamp('verified_by_admin_at')->nullable();
                $t->text('verified_source_note')->nullable();  // must carry the issuer-page URL
                $t->timestamps();
                $t->unique(['chain_id', 'contract'], 'ont_chain_contract_uq');
            });
        }
        if (! Schema::hasTable('crypto_deposit_addresses')) {
            Schema::create('crypto_deposit_addresses', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('chain_id');
                $t->char('address', 42);
                $t->string('label', 80)->nullable();
                $t->string('status', 12)->default('proposed');  // proposed | active | retiring | retired
                $t->unsignedBigInteger('active_from_block')->nullable();
                $t->unsignedBigInteger('proposed_by')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamp('activate_after')->nullable();
                $t->timestamp('test_confirmed_at')->nullable();
                $t->char('fingerprint', 64);
                $t->timestamps();
                $t->unique(['chain_id', 'address'], 'onda_chain_addr_uq');
            });
        }
        if (! Schema::hasTable('crypto_linked_wallets')) {
            Schema::create('crypto_linked_wallets', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->string('family', 12)->default('evm');
                $t->char('address', 42);
                $t->string('active_key', 24)->nullable();       // '1' while active, r plus the row id once revoked: lets a plain unique index mean "one active owner per address" on MySQL 5.7
                $t->timestamp('verified_at')->nullable();
                $t->timestamp('revoked_at')->nullable();
                $t->unsignedBigInteger('last_nonce_id')->nullable();
                $t->char('ip_hash', 64)->nullable();
                $t->char('ua_hash', 64)->nullable();
                $t->timestamps();
                $t->unique(['address', 'active_key'], 'onlw_addr_active_uq');
                $t->index('user_id', 'onlw_user_idx');
                $t->foreign('user_id', 'onlw_user_fk')->references('id')->on('users')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('crypto_link_nonces')) {
            Schema::create('crypto_link_nonces', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->char('nonce', 32);
                $t->char('address', 42);
                $t->unsignedBigInteger('chain_id');
                $t->char('message_hash', 64);
                $t->timestamp('expires_at');
                $t->timestamp('consumed_at')->nullable();
                $t->timestamps();
                $t->unique('nonce', 'onln_nonce_uq');
                $t->index('user_id', 'onln_user_idx');
            });
        }
        if (! Schema::hasTable('crypto_deposits')) {
            Schema::create('crypto_deposits', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('chain_id');
                $t->char('tx_hash', 66);
                $t->unsignedInteger('log_index');
                $t->unsignedBigInteger('block_number');
                $t->char('block_hash', 66);
                $t->unsignedBigInteger('token_id');
                $t->char('from_address', 42);
                $t->char('to_address', 42);
                $t->string('raw_amount', 80);                   // uint256 as a decimal string
                $t->string('dust_raw', 80)->default('0');
                $t->decimal('usd_amount', 20, 4)->nullable();
                $t->string('status', 20)->default('detected');
                $t->unsignedBigInteger('attributed_user_id')->nullable();
                $t->timestamp('attributed_at')->nullable();
                $t->unsignedBigInteger('credited_wallet_txn_id')->nullable();
                $t->string('credit_reference', 120)->nullable();
                $t->timestamp('first_seen_at')->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->timestamp('credited_at')->nullable();
                $t->string('hold_reason', 60)->nullable();
                $t->timestamps();
                $t->unique(['chain_id', 'tx_hash', 'log_index'], 'ond_chain_tx_log_uq');
                $t->unique('credit_reference', 'ond_credit_ref_uq');
                $t->index('status', 'ond_status_idx');
                $t->index(['chain_id', 'from_address'], 'ond_from_idx');
                $t->index(['attributed_user_id', 'status'], 'ond_user_idx');
                $t->foreign('attributed_user_id', 'ond_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }
        if (! Schema::hasTable('crypto_deposit_events')) {
            Schema::create('crypto_deposit_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('deposit_id');
                $t->string('from_status', 20)->nullable();
                $t->string('to_status', 20);
                $t->string('actor', 40)->default('system');     // system | admin:{id} | user:{id}
                $t->text('note')->nullable();
                $t->json('payload')->nullable();
                $t->timestamp('created_at')->useCurrent();     // append-only: there is no updated_at
                $t->index(['deposit_id', 'id'], 'ondev_dep_idx');
            });
        }
        if (! Schema::hasTable('crypto_scan_cursors')) {
            Schema::create('crypto_scan_cursors', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('chain_id');
                $t->unsignedBigInteger('last_scanned_block')->default(0);
                $t->unsignedBigInteger('last_head')->default(0);
                $t->unsignedBigInteger('lag_blocks')->default(0);
                $t->timestamp('updated_at')->nullable();
                $t->unique('chain_id', 'onsc_chain_uq');
            });
        }
    }

    public function down(): void
    {
        foreach (['crypto_scan_cursors', 'crypto_deposit_events', 'crypto_deposits', 'crypto_link_nonces', 'crypto_linked_wallets', 'crypto_deposit_addresses', 'crypto_tokens', 'crypto_rpc_endpoints', 'crypto_chains'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
