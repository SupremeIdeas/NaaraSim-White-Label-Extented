<?php

namespace App\Livewire;

use App\Models\WalletGroupMember;
use App\Services\Wallet\WalletGroupService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
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
 *
 * Tier 5 #11 Phase B piggybacks on that same poll: a shared-wallet invite that
 * lands while the user is actively browsing surfaces as a live hero toast with
 * inline Accept/Decline, instead of waiting for them to notice the bell/email/
 * push. No new broadcasting infrastructure — this reuses the poll that was
 * already here for the unread badge.
 */
class NotificationCenter extends Component
{
    /** How many rows the dropdown shows (the full history lives on /notifications). */
    private const PREVIEW = 8;

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

    /**
     * Fired on every poll tick. Surfaces at most one not-yet-shown pending
     * shared-wallet invite as a live hero toast. `toast_shown_at` (rather
     * than a per-instance property) is the source of truth, so the header's
     * separate mobile/desktop instances — each polling independently —
     * never both toast the same invite.
     */
    public function checkForWalletInvites(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $invite = WalletGroupMember::with('walletGroup.owner')
            ->where('user_id', $user->id)
            ->whereNull('accepted_at')
            ->whereNull('toast_shown_at')
            ->oldest('invited_at')
            ->first();

        if (! $invite) {
            return;
        }

        $invite->forceFill(['toast_shown_at' => now()])->save();

        $ownerName = $invite->walletGroup->owner->name ?: 'A Naara user';
        $this->dispatch(
            'nx-toast',
            variant: 'hero',
            type: 'info',
            sticky: true,
            title: 'Shared wallet invite',
            message: "{$ownerName} invited you to spend from their wallet.",
            actions: [
                ['label' => 'Accept', 'event' => 'wallet-invite-respond', 'payload' => ['memberId' => $invite->id, 'accept' => true]],
                ['label' => 'Decline', 'event' => 'wallet-invite-respond', 'payload' => ['memberId' => $invite->id, 'accept' => false]],
            ],
        );
    }

    /** The Accept/Decline buttons inside the toast call back into this via `Livewire.dispatch()`. */
    #[On('wallet-invite-respond')]
    public function respondToWalletInvite(int $memberId, bool $accept, WalletGroupService $groups): void
    {
        $member = WalletGroupMember::where('user_id', Auth::id())->find($memberId);
        if (! $member) {
            return;
        }

        if ($accept) {
            $groups->accept($member);
            $this->dispatch('nx-toast', type: 'success', message: 'You joined the shared plan.');
        } else {
            $groups->decline($member);
            $this->dispatch('nx-toast', type: 'info', message: 'Invite declined.');
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
