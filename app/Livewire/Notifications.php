<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The full notification history (owner request). Everything the bell previews,
 * paginated, with filters and read controls. Scoped to the signed-in user by
 * the notifiable relationship — a user only ever sees their own.
 */
#[Layout('components.layouts.customer')]
class Notifications extends Component
{
    use WithPagination;

    /** '' = all, or unread, or a category (order/wallet/support/security/offer/system). */
    public string $filter = '';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function markAsRead(string $id): void
    {
        Auth::user()?->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        Auth::user()?->unreadNotifications->markAsRead();
    }

    public function go(string $id): void
    {
        $note = Auth::user()?->notifications()->whereKey($id)->first();
        if (! $note) {
            return;
        }
        $note->markAsRead();
        if ($url = $note->data['action_url'] ?? null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function render()
    {
        $query = Auth::user()->notifications()->getQuery();

        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($this->filter !== '') {
            // Category filter — data is JSON text; a LIKE is index-free but fine
            // at a single user's notification volume.
            $query->where('data', 'like', '%"category":"'.$this->filter.'"%');
        }

        return view('livewire.notifications', [
            'notifications' => $query->latest()->paginate(20),
            'unread' => Auth::user()->unreadNotifications()->count(),
        ]);
    }
}
