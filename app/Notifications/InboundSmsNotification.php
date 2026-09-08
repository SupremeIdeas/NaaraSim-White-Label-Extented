<?php

namespace App\Notifications;

use App\Models\InboundMessage;
use Illuminate\Notifications\Notification;

/**
 * A new inbound SMS landed on a user's Naara Line (Numbers overhaul §1). Database
 * channel only — surfaced in the existing NotificationCenter bell + the Messages
 * unread badge. No message body/PII is placed in the title.
 */
class InboundSmsNotification extends Notification
{
    public function __construct(public InboundMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inbound_sms',
            'title' => 'New message',
            'message' => 'You have a new SMS from '.$this->message->from_number.'.',
            'url' => route('numbers.messages'),
            'icon' => 'message-circle',
        ];
    }
}
