<?php

namespace Tests\Feature;

use App\Livewire\Admin\Account;
use App\Models\User;
use App\Support\SecurityQuestions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → My Account (owner request — fix_admin.md Part 2). A panel user manages
 * their own name/email/password and recovery questions; every mutation is
 * scoped to them and audited.
 */
class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true, 'password' => Hash::make('current-pass-1')]);
        $u->assignRole('admin');

        return $u->fresh();
    }

    public function test_admin_can_change_their_name(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Account::class)
            ->set('name', 'Frank C. Ebubedike')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertSame('Frank C. Ebubedike', $admin->fresh()->name);
    }

    public function test_changing_email_requires_the_password_and_re_verifies(): void
    {
        $admin = $this->admin();

        // Wrong password → rejected.
        Livewire::actingAs($admin)->test(Account::class)
            ->set('new_email', 'new@example.com')
            ->set('email_password', 'wrong')
            ->call('updateEmail')
            ->assertHasErrors('email_password');

        // Correct password → email changes and verification is reset.
        Livewire::actingAs($admin)->test(Account::class)
            ->set('new_email', 'new@example.com')
            ->set('email_password', 'current-pass-1')
            ->call('updateEmail')
            ->assertHasNoErrors();

        $admin->refresh();
        $this->assertSame('new@example.com', $admin->email);
        $this->assertNull($admin->email_verified_at);
    }

    public function test_admin_can_change_password_with_current_password(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Account::class)
            ->set('current_password', 'current-pass-1')
            ->set('password', 'a-new-password-2')
            ->set('password_confirmation', 'a-new-password-2')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('a-new-password-2', $admin->fresh()->password));
    }

    public function test_security_questions_are_hashed_and_verify_case_insensitively(): void
    {
        $admin = $this->admin();
        $qs = SecurityQuestions::PRESETS;

        Livewire::actingAs($admin)->test(Account::class)
            ->set('questions', [
                ['question' => $qs[0], 'answer' => 'Rex'],
                ['question' => $qs[1], 'answer' => 'Onitsha'],
                ['question' => $qs[2], 'answer' => 'Nokia'],
            ])
            ->call('saveSecurityQuestions')
            ->assertHasNoErrors();

        $admin->refresh();
        $this->assertTrue(SecurityQuestions::configured($admin));
        // Stored hashed (bcrypt), never plaintext. Check each answer_hash is a
        // real hash rather than scanning for a substring — a bcrypt digest can
        // randomly contain any 3-char sequence, which made the old scan flaky.
        foreach ($admin->security_questions as $q) {
            $this->assertStringStartsWith('$2y$', $q['answer_hash']);
            $this->assertNotSame('Rex', $q['answer_hash']);
        }
        // Case/space-insensitive verification of all three.
        $this->assertTrue(SecurityQuestions::verify($admin, [
            $qs[0] => '  rex ', $qs[1] => 'ONITSHA', $qs[2] => 'nokia',
        ]));
        $this->assertFalse(SecurityQuestions::verify($admin, [
            $qs[0] => 'rex', $qs[1] => 'wrong', $qs[2] => 'nokia',
        ]));
    }

    public function test_duplicate_questions_are_rejected(): void
    {
        $admin = $this->admin();
        $q = SecurityQuestions::PRESETS[0];

        Livewire::actingAs($admin)->test(Account::class)
            ->set('questions', [
                ['question' => $q, 'answer' => 'a'],
                ['question' => $q, 'answer' => 'b'],
                ['question' => $q, 'answer' => 'c'],
            ])
            ->call('saveSecurityQuestions');

        $this->assertFalse(SecurityQuestions::configured($admin->fresh()));
    }
}
