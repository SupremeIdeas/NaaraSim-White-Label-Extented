<?php

namespace App\Livewire\Admin;

use App\Support\ApiGuide;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The one API-guide modal (blueprint Section 15.1). Every <x-admin-help-icon>
 * dispatches `open-api-guide` with a provider + field; this listens, loads the
 * content, and shows it. Closes on ESC / backdrop (handled in the view via
 * Alpine). One modal engine — no per-field modals.
 */
class ApiGuideModal extends Component
{
    public bool $open = false;

    public ?array $content = null;

    public string $title = '';

    #[On('open-api-guide')]
    public function openGuide(string $provider, string $field): void
    {
        $content = ApiGuide::for($provider, $field);
        if ($content === null) {
            return;
        }

        $this->content = $content + ['provider' => $provider, 'field' => $field];
        $this->title = ucfirst($provider).' — '.$content['label'];
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function render()
    {
        return view('livewire.admin.api-guide-modal');
    }
}
