<?php

namespace Tests\Feature;

use App\Livewire\GetNumber;
use App\Models\SmsOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * IDOR on the number-result view (security audit). GetNumber/Wizard render the
 * phone number and OTP code of the order in a PUBLIC (attacker-settable)
 * Livewire property. An unscoped lookup would let anyone read another user's SMS
 * verification code — account-takeover grade — so the lookup is owner-scoped.
 */
class NumberOrderIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_view_another_users_number_or_otp_by_id(): void
    {
        $victim = User::factory()->create();
        $order = SmsOrder::create([
            'user_id' => $victim->id,
            'provider' => 'fivesim',
            'service_name' => 'whatsapp',
            'country' => 'nigeria',
            'type' => 'otp',
            'phone_number' => '+2348099999999',
            'otp_code' => '778899',
            'status' => 'completed',
        ]);

        $attacker = User::factory()->create();

        // The attacker sets the public property to the victim's order id.
        Livewire::actingAs($attacker)->test(GetNumber::class)
            ->set('orderId', $order->id)
            ->assertDontSee('+2348099999999')
            ->assertDontSee('778899');
    }

    public function test_the_owner_still_sees_their_own_number_and_otp(): void
    {
        $user = User::factory()->create();
        $order = SmsOrder::create([
            'user_id' => $user->id,
            'provider' => 'fivesim',
            'service_name' => 'whatsapp',
            'country' => 'nigeria',
            'type' => 'otp',
            'phone_number' => '+2348011112222',
            'otp_code' => '445566',
            'status' => 'completed',
        ]);

        Livewire::actingAs($user)->test(GetNumber::class)
            ->set('orderId', $order->id)
            ->assertSee('+2348011112222')
            ->assertSee('445566');
    }
}
