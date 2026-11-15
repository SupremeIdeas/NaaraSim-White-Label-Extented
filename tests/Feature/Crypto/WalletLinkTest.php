<?php

namespace Tests\Feature\Crypto;

use App\Models\Crypto\CryptoLinkedWallet;
use App\Models\User;
use App\Services\Crypto\Link\SignatureVerifier;
use App\Services\Crypto\Link\WalletLinkService;
use Elliptic\EC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use kornrunner\Keccak;
use Tests\TestCase;

class WalletLinkTest extends TestCase
{
    use RefreshDatabase;

    private function key(): array
    {
        $ec = new EC('secp256k1');
        $k = $ec->genKeyPair();
        $pub = substr($k->getPublic('hex'), 2);

        return [$k, '0x'.substr(Keccak::hash(hex2bin($pub), 256), 24)];
    }

    private function sign($k, string $message): string
    {
        $hash = Keccak::hash("\x19Ethereum Signed Message:\n".strlen($message).$message, 256);
        $sig = $k->sign($hash, ['canonical' => true]);

        return '0x'.str_pad($sig->r->toString(16), 64, '0', STR_PAD_LEFT).str_pad($sig->s->toString(16), 64, '0', STR_PAD_LEFT).dechex(27 + $sig->recoveryParam);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (! SignatureVerifier::available()) {
            $this->markTestSkipped('ext-gmp not loaded');
        }
    }

    public function test_a_real_signature_recovers_the_signing_address(): void
    {
        [$k, $addr] = $this->key();
        $this->assertSame($addr, SignatureVerifier::recover('hello', $this->sign($k, 'hello')));
        $this->assertNotSame($addr, SignatureVerifier::recover('hello!', $this->sign($k, 'hello')), 'a different message recovers a different address');
        $this->assertNull(SignatureVerifier::recover('hello', '0x1234'));
    }

    public function test_linking_needs_the_wallet_to_sign_the_challenge_and_each_challenge_works_once(): void
    {
        [$k, $addr] = $this->key();
        $u = User::factory()->create();
        $svc = app(WalletLinkService::class);
        $c = $svc->challenge($u, $addr, 137);
        $w = $svc->link($u, $c['id'], $this->sign($k, $c['message']));
        $this->assertSame($addr, $w->address);
        $this->assertSame(1, CryptoLinkedWallet::active()->count());

        $this->expectException(\DomainException::class);
        $svc->link($u, $c['id'], $this->sign($k, $c['message']));   // replay of a used challenge
    }

    public function test_someone_elses_wallet_cannot_be_claimed_with_a_signature_from_another_key(): void
    {
        [, $victim] = $this->key();
        [$attackerKey] = $this->key();
        $u = User::factory()->create();
        $svc = app(WalletLinkService::class);
        $c = $svc->challenge($u, $victim, 137);
        try {
            $svc->link($u, $c['id'], $this->sign($attackerKey, $c['message']));
            $this->fail('must not link');
        } catch (\DomainException) {
            $this->assertSame(0, CryptoLinkedWallet::count());
        }
    }

    public function test_one_wallet_belongs_to_one_member_at_a_time(): void
    {
        [$k, $addr] = $this->key();
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $svc = app(WalletLinkService::class);
        $c = $svc->challenge($a, $addr, 137);
        $svc->link($a, $c['id'], $this->sign($k, $c['message']));
        $c2 = $svc->challenge($b, $addr, 137);
        $this->expectException(\DomainException::class);
        $svc->link($b, $c2['id'], $this->sign($k, $c2['message']));
    }

    public function test_a_challenge_belongs_to_the_member_who_asked_for_it(): void
    {
        [$k, $addr] = $this->key();
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $svc = app(WalletLinkService::class);
        $c = $svc->challenge($a, $addr, 137);
        $this->expectException(\DomainException::class);
        $svc->link($b, $c['id'], $this->sign($k, $c['message']));
    }
}
