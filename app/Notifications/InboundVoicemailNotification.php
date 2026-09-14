<?php

namespace App\Notifications;

use App\Models\InboundMessage;
use Illuminate\Notifications\Notification;

/**
 * A new voicemail landed on a user's Naara Line (Prompt 11). Database channel
 * only — surfaced in the existing NotificationCenter bell + the Messages
 * unread badge, mirroring InboundSmsNotification. No transcript/PII in the
 * title (the transcript may not even exist yet when this fires).
 */
class InboundVoicemailNotification extends Notification
{
    public function __construct(public InboundMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inbound_voicemail',
            'title' => 'New voicemail',
            'message' => 'You have a new voicemail from '.$this->message->from_number.'.',
            'url' => route('numbers.messages'),
            'icon' => 'phone-forwarded',
        ];
    }
}
