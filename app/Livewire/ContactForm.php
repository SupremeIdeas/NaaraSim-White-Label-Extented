<?php

namespace App\Livewire;

use App\Models\SupportConversation;
use App\Notifications\ContactMessageNotification;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Public contact form (Module 27). Two delivery paths:
 *  - Signed-in customer -> an ESCALATED support conversation, so it lands
 *    straight in the staff Tickets queue (reuses the Module 25 pipeline).
 *  - Guest -> a branded, queued email to the operator's support address (only
 *    possible once outgoing mail is configured; otherwise the form points to
 *    the direct channels instead of pretending to send).
 * Rate-limited to blunt spam; honeypot field for bots.
 */
class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $subject = 'Before I buy';

    public string $message = '';

    /** Honeypot — humans never fill this. */
    public string $website = '';

    public bool $sent = false;

    public const SUBJECTS = [
        'Before I buy', 'Setup help', 'Billing question',
        'Technical issue', 'Partnership', 'Something else',
    ];

    public function mount(): void
    {
        if ($user = Auth::user()) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    /** Whether the form can actually deliver for the current visitor. */
    public function getCanDeliverProperty(): bool
    {
        return Auth::check() || MailSettings::isConfigured();
    }

    public function send(): void
    {
        if ($this->website !== '') {
            $this->sent = true; // silently drop bots

            return;
        }

        $this->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email|max:255',
            'subject' => 'required|in:'.implode(',', self::SUBJECTS),
            'message' => 'required|string|min:10|max:5000',
        ]);

        $key = 'contact:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('message', 'Too many messages — please wait a few minutes and try again.');

            return;
        }
        RateLimiter::hit($key, 600);

        if ($user = Auth::user()) {
            // Straight into the staff ticket queue.
            $conversation = SupportConversation::create([
                'user_id' => $user->id,
                'title' => 'Contact: '.$this->subject,
                'escalated' => true,
                'escalation_reason' => $this->subject,
                'escalated_at' => now(),
            ]);
            $conversation->messages()->create(['role' => 'user', 'body' => $this->message]);
        } else {
            Notification::route('mail', config('naara.support.email'))
                ->notify(new ContactMessageNotification($this->name, $this->email, $this->subject, $this->message));
        }

        $this->reset('message');
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.contact-form', ['subjects' => self::SUBJECTS]);
    }
}
