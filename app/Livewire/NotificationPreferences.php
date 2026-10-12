<?php

namespace App\Livewire;

use App\Support\Notifications\NotificationPrefs;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Account > Notification settings: which channels each kind of message may use, and quiet hours. Critical categories cannot be silenced in-app. */
#[Layout('components.layouts.customer')]
class NotificationPreferences extends Component
{
    /** @var array<string, array<string, bool>> */
    public array $channels = [];

    public ?string $quiet_start = null;

    public ?string $quiet_end = null;

    public function mount(): void
    {
        $user = Auth::user();
        $saved = NotificationPrefs::for($user);
        foreach ($this->categories() as $cat => $meta) {
            foreach (NotificationPrefs::CHANNELS as $ch) {
                $this->channels[$cat][$ch] = NotificationPrefs::wants($user, $cat, $ch);
            }
        }
        $this->quiet_start = $saved['quiet_start'];
        $this->quiet_end = $saved['quiet_end'];
    }

    /** Operator-only and white-label lifecycle categories are not a member's to set. */
    public function categories(): array
    {
        return array_diff_key((array) config('notification_catalog.categories', []), ['admin' => 1, 'white_label' => 1]);
    }

    public function save(): void
    {
        $this->validate([
            'quiet_start' => ['nullable', 'date_format:H:i'],
            'quiet_end' => ['nullable', 'date_format:H:i'],
        ]);
        foreach ($this->categories() as $cat => $meta) {
            if (! empty($meta['critical'])) {
                $this->channels[$cat]['in_app'] = true;   // security and billing notices always land in the bell
            }
        }
        NotificationPrefs::save(Auth::user(), $this->channels, $this->quiet_start, $this->quiet_end);
        $this->dispatch('nx-toast', type: 'success', message: 'Notification settings saved.');
    }

    public function render()
    {
        return view('livewire.notification-preferences', ['categories' => $this->categories(), 'tz' => Auth::user()->timezone ?: 'UTC']);
    }
}
