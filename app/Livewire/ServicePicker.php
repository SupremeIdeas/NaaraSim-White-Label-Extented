<?php

namespace App\Livewire;

use App\Support\ServicePickerSources;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The ONE service-picking UI (Numbers V6 §0) — a single reusable modal shared by
 * Naara Verify and Naara Rent so there is never a second bespoke service picker.
 * Opened via an `open-service-picker` event carrying a `for` token that is echoed
 * back with the pick, so multiple openers can share one modal.
 *
 * Emits `service-picked` { slug, name, for } and closes. Rows (icon + name +
 * favourite star) come from ServicePickerSources; favourites are a per-device
 * preference kept in localStorage (no schema change), starred first.
 */
class ServicePicker extends Component
{
    public bool $open = false;

    public string $for = '';

    public ?string $title = null;

    #[On('open-service-picker')]
    public function openModal(string $for = '', ?string $title = null): void
    {
        $this->for = $for;
        $this->title = $title;
        $this->open = true;
    }

    public function pick(string $slug, string $name): void
    {
        $this->dispatch('service-picked', slug: $slug, name: $name, for: $this->for);
        $this->close();
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function render()
    {
        return view('livewire.service-picker', [
            'options' => $this->open ? ServicePickerSources::options() : [],
        ]);
    }
}
