<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Branded, queued welcome email (Module 22), sent once a new account is created.
 */
class WelcomeNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\Concerns\InApp;
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function inApp(object $notifiable): array
    {
        return [
            'category' => 'system',
            'icon' => 'gift',
            'title' => 'Welcome to '.config('app.name').'!',
            'body' => 'Stay connected across 190+ countries — data plans and numbers in one place.',
            'action_url' => url('/catalogue'),
            'action_label' => 'Explore plans',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(\App\Support\MailTemplates::subject('welcome', 'Welcome to '.config('app.name')))
            ->view('emails.welcome', [
                'name' => $notifiable->name ?? null,
                'url' => url('/dashboard'),
            ]);
    }
}
