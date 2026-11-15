<?php

namespace Tests\Feature\Crypto;

use App\Livewire\CryptoTopup;
use App\Models\Setting;
use App\Models\User;
use App\Services\Crypto\Link\SignatureVerifier;
use Database\Seeders\CryptoChainsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CryptoTopupPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new CryptoChainsSeeder)->run();
        foreach ([1, 137, 56] as $cid) {
            \App\Models\Crypto\CryptoDepositAddress::firstOrCreate(['chain_id' => $cid, 'address' => '0x2ccf54deed6f5ab61f527d987ddc1bb97ff10b86'], ['label' => 'Test', 'status' => 'active']);
        }
        \App\Models\Crypto\CryptoDepositAddress::firstOrCreate(['chain_id' => 56, 'address' => '0x163af1fd68631cf397b8878d754c058b5427c1ac'], ['label' => 'Test', 'status' => 'active']);
        \App\Models\Crypto\CryptoChain::whereIn('chain_id', [1, 137])->update(['enabled' => true, 'credit_enabled' => true]);
        \App\Models\Setting::setValue('crypto.enabled', 1, 'crypto');

    }

    private function turnOn(): void
    {
        // Ships ON: nothing to do. (Kept so each test reads as "when on".)
    }

    private function turnOff(): void
    {
        Setting::setValue('crypto.enabled', 0, 'crypto');
    }

    public function test_while_off_a_member_gets_a_plain_404(): void
    {
        $this->turnOff();
        $this->actingAs(User::factory()->create(['is_active' => true]))->get('/wallet/crypto')->assertNotFound();
    }

    public function test_when_on_the_member_sees_the_official_address_and_accepted_tokens(): void
    {
        $this->turnOn();
        $this->actingAs(User::factory()->create(['is_active' => true]))->get('/wallet/crypto')->assertOk()
            ->assertSee('0x2ccf54deed6f5ab61f527d987ddc1bb97ff10b86')->assertSee('USDC')->assertSee('Polygon PoS')->assertSee('Connect your wallet')->assertSee('data-crypto-address', false);
    }

    public function test_linking_starts_with_a_server_written_challenge_for_this_member(): void
    {
        if (! SignatureVerifier::available()) {
            $this->markTestSkipped('ext-gmp not loaded');
        }
        $this->turnOn();
        $u = User::factory()->create(['is_active' => true]);
        Livewire::actingAs($u)->test(CryptoTopup::class)->call('startLink', '0x'.str_repeat('a1', 20))->assertDispatched('crypto-sign')->assertSet('problem', null);
        Livewire::actingAs($u)->test(CryptoTopup::class)->call('startLink', 'nope')->assertSet('problem', 'That is not a valid wallet address.');
    }
}

