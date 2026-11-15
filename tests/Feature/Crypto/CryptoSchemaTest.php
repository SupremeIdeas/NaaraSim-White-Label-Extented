<?php

namespace Tests\Feature\Crypto;

use App\Models\Crypto\CryptoChain;
use App\Models\Crypto\CryptoDeposit;
use App\Models\Crypto\CryptoDepositEvent;
use App\Models\Crypto\CryptoLinkedWallet;
use App\Models\Crypto\CryptoLinkNonce;
use App\Models\Crypto\CryptoToken;
use App\Models\User;
use App\Services\Crypto\Support\DepositStatus;
use Database\Seeders\CryptoChainsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CryptoSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function deposit(array $over = []): CryptoDeposit
    {
        $tok = CryptoToken::create(['chain_id' => 137, 'symbol' => 'USDC', 'contract' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', 'decimals' => 6]);

        return CryptoDeposit::create($over + ['chain_id' => 137, 'tx_hash' => '0x'.str_repeat('a', 64), 'log_index' => 3, 'block_number' => 100, 'block_hash' => '0x'.str_repeat('b', 64),
            'token_id' => $tok->id, 'from_address' => '0x'.str_repeat('C', 40), 'to_address' => '0x'.str_repeat('d', 40), 'raw_amount' => '12500000', 'first_seen_at' => now()]);
    }

    public function test_every_table_exists_and_a_fresh_install_has_chains_only_all_switched_off(): void
    {
        foreach (['chains', 'rpc_endpoints', 'tokens', 'deposit_addresses', 'linked_wallets', 'link_nonces', 'deposits', 'deposit_events', 'scan_cursors'] as $t) {
            $this->assertTrue(Schema::hasTable('crypto_'.$t), $t);
        }
        $this->seed(CryptoChainsSeeder::class);
        $this->seed(CryptoChainsSeeder::class);   // idempotent
        $this->assertSame(3, CryptoChain::count());
        $this->assertSame([], CryptoChain::where('enabled', true)->pluck('chain_id')->all(), 'every network ships off');
        $this->assertSame(5, CryptoToken::count(), 'only issuer-published tokens plus the two disabled BNB ones ship');
        $this->assertSame(0, CryptoToken::where('chain_id', 56)->where('enabled', true)->count(), 'bridged BNB tokens ship disabled');
        $this->assertSame(0, \App\Models\Crypto\CryptoDepositAddress::count(), 'no receiving address ships: each install adds its own');
        $this->assertSame(4, \App\Models\Crypto\CryptoRpcEndpoint::count(), 'two keyless public endpoints per live chain');
        $this->assertSame('0', (string) \App\Models\Setting::getValue('crypto.enabled'), 'the feature ships OFF');
    }

    public function test_every_index_and_foreign_key_name_in_every_migration_fits_mysql_64_characters(): void
    {
        $bad = [];
        foreach (glob(database_path('migrations/*.php')) as $file) {
            $src = file_get_contents($file);
            // explicit names: ->index(..., 'name'), ->unique(..., 'name'), ->foreign(..., 'name'), ->primary(..., 'name')
            preg_match_all("/->(?:index|unique|foreign|primary|fullText|spatialIndex)\\([^;]*?,\\s*'([A-Za-z0-9_]+)'\\)/s", $src, $m);
            foreach ($m[1] as $name) {
                if (strlen($name) > 64) {
                    $bad[] = basename($file).': '.$name;
                }
            }
        }
        $this->assertSame([], $bad, 'identifier longer than 64 characters');
        // auto-generated names for the crypto tables would be table_column_type: prove each one explicitly named stays short
        $src = file_get_contents(database_path('migrations/2027_03_02_100000_create_crypto_tables.php'));
        $this->assertDoesNotMatchRegularExpression("/->(?:index|unique)\\('[a-z_]+'\\);/", $src, 'every crypto index must carry an explicit short name');
    }

    public function test_a_chain_event_is_unique_and_the_hash_is_stored_lower_case(): void
    {
        $d = $this->deposit();
        $this->assertSame('0x'.str_repeat('c', 40), $d->from_address);
        $this->expectException(QueryException::class);
        $this->deposit(['raw_amount' => '1']);   // same (chain, tx, log)
    }

    public function test_the_wallet_reference_comes_only_from_the_chain_event(): void
    {
        $this->assertSame('topup:crypto:137:0x'.str_repeat('a', 64).':3', CryptoDeposit::referenceFor(137, '0x'.strtoupper(str_repeat('a', 64)), 3));
    }

    public function test_transitions_follow_the_whitelist_and_each_writes_one_event(): void
    {
        $d = $this->deposit();
        $d = $d->transition(DepositStatus::Confirming, 'system', 'waiting');
        $d = $d->transition(DepositStatus::Verified);
        $this->assertSame('verified', $d->status);
        $this->assertSame(2, CryptoDepositEvent::where('deposit_id', $d->id)->count());
        $this->expectException(\DomainException::class);
        $d->transition(DepositStatus::Credited);   // cannot skip credit_pending
    }

    public function test_events_are_append_only(): void
    {
        $d = $this->deposit()->transition(DepositStatus::Confirming);
        $e = CryptoDepositEvent::first();
        $this->expectException(\LogicException::class);
        $e->update(['note' => 'edited']);
    }

    public function test_attribution_is_set_once_and_never_moves_to_someone_else(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $d = $this->deposit()->attributeTo($a->id);
        $this->assertSame($a->id, (int) $d->attributed_user_id);
        $d->attributeTo($a->id);   // same owner again is a no-op
        $this->expectException(\DomainException::class);
        $d->attributeTo($b->id);
    }

    public function test_one_active_owner_per_address_and_a_revoked_link_frees_the_address(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $w = CryptoLinkedWallet::create(['user_id' => $a->id, 'address' => '0xAbC'.str_repeat('1', 37), 'verified_at' => now()]);
        $this->assertSame('1', $w->active_key);
        try {
            CryptoLinkedWallet::create(['user_id' => $b->id, 'address' => '0xabc'.str_repeat('1', 37), 'verified_at' => now()]);
            $this->fail('a second active owner must be refused');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $w->revoke();
        $this->assertNotNull(CryptoLinkedWallet::create(['user_id' => $b->id, 'address' => '0xabc'.str_repeat('1', 37), 'verified_at' => now()]));
    }

    public function test_a_nonce_can_be_consumed_exactly_once_and_never_after_expiry(): void
    {
        $u = User::factory()->create();
        $n = CryptoLinkNonce::create(['user_id' => $u->id, 'nonce' => str_repeat('a', 32), 'address' => '0x'.str_repeat('1', 40), 'chain_id' => 137, 'message_hash' => str_repeat('0', 64), 'expires_at' => now()->addMinutes(10)]);
        $this->assertTrue($n->consume());
        $this->assertFalse($n->consume(), 'a replay must fail');
        $old = CryptoLinkNonce::create(['user_id' => $u->id, 'nonce' => str_repeat('b', 32), 'address' => '0x'.str_repeat('1', 40), 'chain_id' => 137, 'message_hash' => str_repeat('0', 64), 'expires_at' => now()->subMinute()]);
        $this->assertFalse($old->consume());
    }

    public function test_a_token_cannot_be_enabled_without_the_issuer_url_and_its_contract_is_lower_cased(): void
    {
        $t = CryptoToken::create(['chain_id' => 1, 'symbol' => 'USDC', 'contract' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 'decimals' => 6]);
        $this->assertSame('0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', $t->contract);
        try {
            $t->update(['enabled' => true]);
            $this->fail('enabling without a source must be refused');
        } catch (\DomainException) {
            $this->addToAssertionCount(1);
        }
        $t->update(['enabled' => true, 'verified_source_note' => 'https://developers.circle.com/stablecoins/usdc-contract-addresses']);
        $this->assertTrue($t->fresh()->enabled);
    }

    public function test_an_rpc_url_is_encrypted_at_rest(): void
    {
        $e = \App\Models\Crypto\CryptoRpcEndpoint::create(['chain_id' => 137, 'role' => 'primary', 'url_encrypted' => 'https://polygon.example.com/v2/SECRETKEY']);
        $this->assertStringNotContainsString('SECRETKEY', (string) \Illuminate\Support\Facades\DB::table('crypto_rpc_endpoints')->where('id', $e->id)->value('url_encrypted'));
        $this->assertSame('polygon.example.com/***', $e->fresh()->maskedUrl());
    }
}
