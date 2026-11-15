<?php

namespace Tests\Feature\Crypto;

use App\Livewire\Admin\CryptoManage;
use App\Models\Crypto\CryptoChain;
use App\Models\Crypto\CryptoDepositAddress;
use App\Models\Crypto\CryptoRpcEndpoint;
use App\Models\User;
use Database\Seeders\CryptoChainsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CryptoAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        (new CryptoChainsSeeder)->run();
        foreach ([1, 137, 56] as $cid) {
            \App\Models\Crypto\CryptoDepositAddress::firstOrCreate(['chain_id' => $cid, 'address' => '0x2ccf54deed6f5ab61f527d987ddc1bb97ff10b86'], ['label' => 'Test', 'status' => 'active']);
        }
        \App\Models\Crypto\CryptoDepositAddress::firstOrCreate(['chain_id' => 56, 'address' => '0x163af1fd68631cf397b8878d754c058b5427c1ac'], ['label' => 'Test', 'status' => 'active']);
        \App\Models\Crypto\CryptoChain::whereIn('chain_id', [1, 137])->update(['enabled' => true, 'credit_enabled' => true]);
        \App\Models\Setting::setValue('crypto.enabled', 1, 'crypto');

    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u->fresh();
    }

    public function test_the_page_shows_the_official_addresses_and_is_closed_to_members(): void
    {
        $this->actingAs($this->admin())->get('/adminmaster/crypto-topup')->assertOk()->assertSee('0x2ccf54deed6f5ab61f527d987ddc1bb97ff10b86');
        $this->actingAs(User::factory()->create(['is_active' => true]))->get('/adminmaster/crypto-topup')->assertStatus(404);
    }

    public function test_an_admin_can_add_an_address_per_network_and_a_bad_one_is_refused(): void
    {
        $new = '0x'.str_repeat('ab', 20);
        Livewire::actingAs($this->admin())->test(CryptoManage::class)
            ->set('newAddress.137', 'not-an-address')->call('addAddress', 137)->assertSee('not a valid EVM address')
            ->set('newAddress.137', $new)->set('newLabel.137', 'Treasury 2')->call('addAddress', 137)->assertSee('Address saved');
        $this->assertSame('active', CryptoDepositAddress::where('chain_id', 137)->where('address', $new)->value('status'));
    }

    public function test_the_last_active_address_cannot_be_retired(): void
    {
        $row = CryptoDepositAddress::where('chain_id', 137)->firstOrFail();
        Livewire::actingAs($this->admin())->test(CryptoManage::class)->call('retireAddress', $row->id)->assertSee('at least one active address');
        $this->assertSame('active', $row->fresh()->status);
    }

    public function test_a_chain_cannot_be_switched_on_without_a_verified_endpoint_and_the_endpoint_must_answer_the_right_chain(): void
    {
        CryptoRpcEndpoint::query()->delete();
        CryptoChain::where('chain_id', 137)->update(['enabled' => false, 'credit_enabled' => false]);
        $c = Livewire::actingAs($this->admin())->test(CryptoManage::class);
        $c->call('toggleChain', 137, 'enabled')->assertSee('Add and test an RPC endpoint');
        $this->assertFalse(CryptoChain::where('chain_id', 137)->value('enabled'));

        Http::fake(['https://eth.example.com/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x1'])]);   // that is Ethereum, not Polygon
        $c->set('primaryUrl.137', 'https://eth.example.com/KEY')->call('saveRpc', 137, 'primary')->assertSee('not 137');
        $this->assertSame(0, CryptoRpcEndpoint::where('chain_id', 137)->count());

        Http::fake(['https://poly.example.com/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x89'])]);
        $c->set('primaryUrl.137', 'https://poly.example.com/KEY')->call('saveRpc', 137, 'primary')->assertSee('verified');
        $c->call('toggleChain', 137, 'enabled');
        $this->assertTrue((bool) CryptoChain::where('chain_id', 137)->value('enabled'));
    }

    public function test_the_reown_project_id_is_validated_and_saved(): void
    {
        Livewire::actingAs($this->admin())->test(CryptoManage::class)
            ->set('reownId', 'not-a-project-id')->call('saveReown')->assertSee('32 letters and numbers')
            ->set('reownId', str_repeat('a1', 16))->call('saveReown')->assertSee('WalletConnect project saved');
        $this->assertSame(str_repeat('a1', 16), \App\Models\Setting::getValue('crypto.reown_project_id'));
    }

    public function test_limits_are_validated_and_saved_and_the_checklist_and_guide_are_on_the_page(): void
    {
        $tok = \App\Models\Crypto\CryptoToken::where('chain_id', 137)->where('symbol', 'USDC')->firstOrFail();
        $c = Livewire::actingAs($this->admin())->test(CryptoManage::class)->assertSee('Setup checklist')->assertSee('Setup guide')->assertSee('Your first real test');
        $c->call('editLimits', $tok->id)->set("limits.{$tok->id}.min", '50')->set("limits.{$tok->id}.max", '10')->call('saveLimits', $tok->id)->assertSee('Check the limits');
        $c->set("limits.{$tok->id}.min", '10')->set("limits.{$tok->id}.max", '3000')->set("limits.{$tok->id}.review", '1000')->set("limits.{$tok->id}.daily", '8000')->call('saveLimits', $tok->id)->assertSee('limits saved');
        $this->assertEquals(3000.0, (float) $tok->fresh()->max_single_usd);
        $this->assertEquals(10.0, (float) $tok->fresh()->min_usd);
    }
}
