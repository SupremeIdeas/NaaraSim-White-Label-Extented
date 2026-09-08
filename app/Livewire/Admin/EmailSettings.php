<?php

namespace App\Livewire\Admin;

use App\Notifications\TestMailNotification;
use App\Support\Auditor;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Email settings (Module 22). Super-admin only. Configure the outgoing
 * mailer + SMTP credentials + the "from" identity from the panel — no .env
 * editing — and send a test email to confirm it works. The stored SMTP password
 * is never sent back to the browser (masked preview; blank input keeps it).
 */
#[Layout('components.layouts.admin')]
class EmailSettings extends Component
{
    public string $mailer = 'log';

    public string $smtp_host = '';

    public string $smtp_port = '';

    public string $smtp_username = '';

    public string $smtp_password = ''; // blank = keep stored

    public string $smtp_scheme = ''; // '' = STARTTLS/auto, 'smtps' = SSL/TLS

    public string $from_address = '';

    public string $from_name = '';

    public ?string $saved = null;

    public ?string $testResult = null;

    public ?string $testError = null;

    /** Email-verification enforcement (NAARA-BUILD-20 §2): off | soft | hard. */
    public string $verificationMode = 'soft';

    public function mount(): void
    {
        $this->mailer = (string) MailSettings::get('mailer', config('mail.default', 'log'));
        $this->smtp_host = (string) MailSettings::get('smtp_host', '');
        $this->smtp_port = (string) MailSettings::get('smtp_port', '');
        $this->smtp_username = (string) MailSettings::get('smtp_username', '');
        $this->smtp_scheme = (string) MailSettings::get('smtp_scheme', '');
        $this->from_address = (string) MailSettings::get('from_address', config('mail.from.address', ''));
        $this->from_name = (string) MailSettings::get('from_name', config('mail.from.name', config('app.name')));
        $this->verificationMode = MailSettings::verificationMode();
        // smtp_password intentionally left blank — never echo the secret.
    }

    protected function rules(): array
    {
        return [
            'mailer' => 'required|in:log,smtp,sendmail',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|numeric',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_scheme' => 'nullable|in:,smtps',
            'from_address' => 'required|email',
            'from_name' => 'required|string|max:100',
            'verificationMode' => 'required|in:off,soft,hard',
        ];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
        $this->validate();

        MailSettings::save([
            'mailer' => $this->mailer,
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'smtp_username' => $this->smtp_username,
            'smtp_password' => $this->smtp_password, // blank -> kept
            'smtp_scheme' => $this->smtp_scheme,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
        ]);

        MailSettings::setVerificationMode($this->verificationMode);

        $this->smtp_password = '';
        $this->saved = 'Email settings saved. They apply immediately.';
        $this->testResult = $this->testError = null;
        $this->dispatch('nx-toast', type: 'success', message: 'Email settings saved.');

        Auditor::log('mail.settings_updated', null, null, ['mailer' => $this->mailer]);
    }

    /**
     * Send a test email to the current admin — synchronously so any SMTP/auth
     * error is shown right here rather than swallowed by a failed queue job.
     */
    public function sendTest(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $this->testResult = $this->testError = null;
        $admin = Auth::user();

        try {
            Notification::sendNow($admin, new TestMailNotification);
            $this->testResult = 'Test email sent to '.$admin->email
                .($this->mailer === 'log' ? ' (mailer is "log" — check storage/logs, nothing was actually emailed).' : '. Check your inbox.');
            Auditor::log('mail.test_sent');
        } catch (\Throwable $e) {
            $this->testError = 'Could not send: '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.admin.email-settings', [
            'passwordPreview' => MailSettings::passwordPreview(),
        ]);
    }
}
