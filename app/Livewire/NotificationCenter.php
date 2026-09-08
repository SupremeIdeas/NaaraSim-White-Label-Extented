<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * In-app notification bell (owner request). Lives in the customer header: shows
 * an unread count, a dropdown of the most recent notifications, and lets the
 * user act on or dismiss them. Self-hosted on Laravel's database notifications
 * — no third-party push service.
 *
 * Near-real-time without websockets: the unread badge refreshes on a light poll
 * (works on VPS and shared cPanel alike). Everything here is scoped to the
 * signed-in user by the notifiable relationship — a user can only ever see,
 * read, or clear their OWN notifications.
 */
class NotificationCenter extends Component
{
    /** How many rows the dropdown shows (the full history lives on /notifications). */
    private const PREVIEW = 8;

    public bool $open = false;

    public function unreadCount(): int
    {
        return Auth::user()?->unreadNotifications()->count() ?? 0;
    }

    public function markAsRead(string $id): void
    {
        $note = Auth::user()?->notifications()->whereKey($id)->first();
        $note?->markAsRead();
    }

    public function markAllRead(): void
    {
        Auth::user()?->unreadNotifications->markAsRead();
    }

    /**
     * Mark one read and hand back its destination so the front-end can navigate.
     * Returning the URL (rather than redirecting) keeps the dropdown snappy and
     * lets a notification with no action simply close.
     */
    public function go(string $id): void
    {
        $note = Auth::user()?->notifications()->whereKey($id)->first();
        if (! $note) {
            return;
        }
        $note->markAsRead();

        $url = $note->data['action_url'] ?? null;
        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    public function render()
    {
        $user = Auth::user();
        $recent = $user
            ? $user->notifications()->latest()->limit(self::PREVIEW)->get()
            : collect();

        return view('livewire.notification-center', [
            'recent' => $recent,
            'unread' => $this->unreadCount(),
        ]);
    }
}
