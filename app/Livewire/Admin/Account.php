<?php

namespace App\Livewire\Admin;

use App\Actions\Fortify\UpdateUserPassword;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\SecurityQuestions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → My Account (owner request — fix_admin.md Part 2). A panel user manages
 * THEIR OWN account: name, avatar, email (re-verified), password, and security
 * questions for recovery. Distinct from Admin → Security, which governs
 * site-wide protections (super_admin only). 2FA stays on the Security page
 * (Fortify actions) — linked from here.
 *
 * Every mutation is audit-logged. All actions are scoped to Auth::user().
 */
#[Layout('components.layouts.admin')]
class Account extends Component
{
    use WithFileUploads;

    public string $name = '';

    public $avatar = null;

    // Email change
    public string $new_email = '';

    public string $email_password = '';

    // Password change
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Security questions (question text => answer)
    /** @var array<int, array{question: string, answer: string}> */
    public array $questions = [];

    public ?string $flash = null;

    public ?string $flashType = 'success';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = (string) $user->name;
        $this->new_email = (string) $user->email;

        // Prefill the question slots (answers never surface — write-only).
        $existing = SecurityQuestions::questionsFor($user);
        for ($i = 0; $i < SecurityQuestions::REQUIRED; $i++) {
            $this->questions[] = ['question' => $existing[$i] ?? SecurityQuestions::PRESETS[$i], 'answer' => ''];
        }
    }

    private function banner(string $msg, string $type = 'success'): void
    {
        $this->flash = $msg;
        $this->flashType = $type;
    }

    public function updateProfile(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = Auth::user();
        $data = ['name' => trim($this->name)];
        if ($this->avatar) {
            $data['avatar'] = MediaStorage::storePublic($this->avatar, 'avatars');
        }
        $user->forceFill($data)->save();

        $this->reset('avatar');
        Auditor::log('admin.profile_updated');
        $this->banner('Profile updated.');
    }

    public function updateEmail(): void
    {
        $user = Auth::user();

        $this->validate([
            'new_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'email_password' => ['required', 'current_password:web'],
        ], [
            'email_password.current_password' => 'That password is incorrect.',
        ]);

        if ($this->new_email === $user->email) {
            $this->banner('That is already your email.', 'error');

            return;
        }

        $user->forceFill(['email' => $this->new_email, 'email_verified_at' => null])->save();
        $user->sendEmailVerificationNotification();

        $this->reset('email_password');
        Auditor::log('admin.email_changed');
        $this->banner('Email changed. Confirm it from the link we just sent.');
    }

    public function updatePassword(UpdateUserPassword $updater): void
    {
        $updater->update(Auth::user(), [
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        Auditor::log('admin.password_changed');
        $this->banner('Password updated.');
    }

    public function saveSecurityQuestions(): void
    {
        $this->validate([
            'questions' => ['array', 'size:'.SecurityQuestions::REQUIRED],
            'questions.*.question' => ['required', 'string', 'max:150'],
            'questions.*.answer' => ['required', 'string', 'min:2', 'max:120'],
        ], [
            'questions.*.answer.required' => 'Answer every question.',
        ]);

        // Questions must be distinct so a single answer can't cover several.
        $texts = array_map(fn ($q) => trim($q['question']), $this->questions);
        if (count(array_unique($texts)) !== count($texts)) {
            $this->banner('Please choose three different questions.', 'error');

            return;
        }

        $pairs = [];
        foreach ($this->questions as $q) {
            $pairs[trim($q['question'])] = $q['answer'];
        }

        Auth::user()->forceFill(['security_questions' => SecurityQuestions::build($pairs)])->save();

        foreach ($this->questions as $i => $q) {
            $this->questions[$i]['answer'] = '';
        }
        Auditor::log('admin.security_questions_updated');
        $this->banner('Security questions saved. You can use them to recover your account.');
    }

    public function render()
    {
        return view('livewire.admin.account', [
            'presets' => SecurityQuestions::PRESETS,
            'questionsConfigured' => SecurityQuestions::configured(Auth::user()),
            'user' => Auth::user(),
        ]);
    }
}
