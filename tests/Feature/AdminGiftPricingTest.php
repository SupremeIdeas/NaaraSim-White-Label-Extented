<?php

namespace Tests\Feature;

use App\Livewire\Admin\Pricing;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminGiftPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_sets_the_gift_card_markup_and_floor_and_the_engine_uses_them(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Pricing::class)
            ->set('giftcard_markup_pct', 12)
            ->set('giftcard_min_profit', 0.40)
            ->call('saveGlobal')
            ->assertHasNoErrors();

        $this->assertEquals(12.0, (float) Setting::getValue('pricing.giftcard_markup_pct'));
        $engine = app(PricingEngine::class);
        $this->assertSame(11.2, $engine->giftCardRetail(10.0, 'zendit', false));   // 10 + 12%
        $this->assertSame(1.40, $engine->giftCardRetail(1.0, 'zendit', false));    // floor: cost + 0.40 beats 12%
    }

    public function test_an_absurd_markup_is_rejected(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Pricing::class)
            ->set('giftcard_markup_pct', 500)
            ->call('saveGlobal')
            ->assertHasErrors(['giftcard_markup_pct']);
    }
}
