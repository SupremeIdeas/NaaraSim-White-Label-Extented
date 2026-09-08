<?php

namespace Tests\Feature;

use App\Livewire\Admin\RecoverPassword;
use App\Models\User;
use App\Support\SecurityQuestions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin password recovery via security questions (fix_admin.md Part 2). A
 * locked-out admin can self-recover by answering their questions — rate-limited
 * and audited, no account enumeration.
 */
class AdminRecoveryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        RateLimiter::clear('admin-recover:admin@example.com|127.0.0.1');
    }

    private function adminWithQuestions(): User
    {
        $u = User::factory()->create(['email' => 'admin@example.com', 'password' => Hash::make('old'), 'is_active' => true]);
        $u->assignRole('admin');
        $qs = SecurityQuestions::PRESETS;
        $u->forceFill(['security_questions' => SecurityQuestions::build([
            $qs[0] => 'Rex', $qs[1] => 'Onitsha', $qs[2] => 'Nokia',
        ])])->save();

        return $u->fresh();
    }

    public function test_correct_answers_let_the_admin_set_a_new_password(): void
    {
        $admin = $this->adminWithQuestions();
        $qs = SecurityQuestions::PRESETS;

        Livewire::test(RecoverPassword::class)
            ->set('email', 'admin@example.com')
            ->call('lookup')
            ->assertSet('step', 2)
            ->set('answers', [$qs[0] => 'rex', $qs[1] => 'onitsha', $qs[2] => 'nokia'])
            ->call('verify')
            ->assertSet('step', 3)
            ->set('password', 'brand-new-pass-1')
            ->set('password_confirmation', 'brand-new-pass-1')
            ->call('resetPassword')
            ->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('brand-new-pass-1', $admin->fresh()->password));
    }

    public function test_wrong_answers_do_not_advance(): void
    {
        $this->adminWithQuestions();
        $qs = SecurityQuestions::PRESETS;

        Livewire::test(RecoverPassword::class)
            ->set('email', 'admin@example.com')
            ->call('lookup')
            ->set('answers', [$qs[0] => 'rex', $qs[1] => 'WRONG', $qs[2] => 'nokia'])
            ->call('verify')
            ->assertSet('step', 2)
            ->assertSet('error', 'Those answers did not match. Please try again.');
    }

    public function test_an_unknown_or_non_admin_email_does_not_reveal_anything(): void
    {
        // A plain user with no questions — must not advance, generic message.
        $plain = User::factory()->create(['email' => 'plain@example.com']);
        $plain->assignRole('user');

        Livewire::test(RecoverPassword::class)
            ->set('email', 'plain@example.com')
            ->call('lookup')
            ->assertSet('step', 1);

        Livewire::test(RecoverPassword::class)
            ->set('email', 'nobody@example.com')
            ->call('lookup')
            ->assertSet('step', 1);
    }
}
