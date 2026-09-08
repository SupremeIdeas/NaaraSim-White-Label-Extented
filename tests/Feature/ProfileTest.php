<?php

namespace Tests\Feature;

use App\Livewire\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Extended self-service profile (owner request). Users build a fuller profile;
 * a completeness meter reflects how much they've filled in. Nothing here touches
 * money, auth, or KYC.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_fill_in_their_profile(): void
    {
        $user = User::factory()->create(['name' => 'Ada']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('name', 'Ada Obi')
            ->set('phone', '+2348012345678')
            ->set('bio', 'Digital nomad, always connected.')
            ->set('city', 'Onitsha')
            ->set('countryCode', 'ng')
            ->set('dateOfBirth', '1995-04-20')
            ->set('displayCurrency', 'NGN')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Ada Obi', $user->name);
        $this->assertSame('Onitsha', $user->city);
        $this->assertSame('NG', $user->country_code); // upper-cased
        $this->assertSame('NGN', $user->display_currency);
        $this->assertSame('Digital nomad, always connected.', $user->bio);
    }

    public function test_the_completeness_meter_grows_as_fields_fill(): void
    {
        $sparse = User::factory()->create([
            'name' => 'A', 'phone' => null, 'city' => null, 'bio' => null,
            'avatar' => null, 'date_of_birth' => null, 'address_line' => null, 'country_code' => null,
        ]);
        $rich = User::factory()->create([
            'name' => 'B', 'phone' => '+100', 'city' => 'Lagos', 'bio' => 'hi',
            'avatar' => 'a.png', 'date_of_birth' => '1990-01-01', 'address_line' => '1 Rd', 'country_code' => 'NG',
        ]);

        $this->assertLessThan($rich->profileCompleteness(), $sparse->profileCompleteness());
        $this->assertSame(100, $rich->profileCompleteness());
    }

    public function test_an_avatar_upload_is_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Profile::class)
            ->set('avatar', UploadedFile::fake()->image('me.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull($user->fresh()->avatar);
    }

    public function test_a_future_date_of_birth_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Profile::class)
            ->set('dateOfBirth', now()->addYear()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors('dateOfBirth');
    }
}
