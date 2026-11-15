<?php

namespace Tests\Feature\Crypto;

use App\Livewire\Admin\CryptoManage;
use App\Models\User;
use App\Services\Crypto\Support\MathExtensionManager;
use Database\Seeders\CryptoChainsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MathExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_reports_the_extension_state_and_always_offers_manual_steps(): void
    {
        $st = app(MathExtensionManager::class)->status();
        $this->assertSame(extension_loaded('gmp') || extension_loaded('bcmath'), $st['ok']);
        $this->assertMatchesRegularExpression('/^\d\.\d{1,2}$/', $st['php']);
        $this->assertNotEmpty($st['steps']);
        $this->assertStringContainsString('cPanel', implode(' ', $st['steps']));
        $this->assertSame($st['blocker'] === null, $st['can_install']);
    }

    public function test_the_admin_page_shows_the_card_and_a_blocked_host_never_starts_an_install(): void
    {
        $this->seed(RoleSeeder::class);
        (new CryptoChainsSeeder)->run();
        $a = User::factory()->create(['is_active' => true]);
        $a->assignRole('admin');
        $c = Livewire::actingAs($a)->test(CryptoManage::class)->assertSee('Wallet verification support');
        if (! app(MathExtensionManager::class)->status()['can_install']) {
            $c->call('mathInstall')->assertSet('mathRunning', false);
        }
        $this->assertTrue(true);
    }
}
