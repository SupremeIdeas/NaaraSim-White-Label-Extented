<?php

namespace App\Livewire;

use App\Support\CountryPickerSources;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The ONE country-picking UI (blueprint Section 31) — a single reusable modal
 * shared across the whole app so there is never a second bespoke country picker.
 * Opened from anywhere via an `open-country-picker` event carrying the data
 * `source` (e.g. 'esim') plus any args, and a `for` token that is echoed back
 * with the pick so the opener knows the selection is theirs.
 *
 * Emits `country-picked` { code, name, for } and closes. The list (flag + name +
 * live plan count) comes from CountryPickerSources, so this component is a dumb,
 * accessible view — Numbers reuses it by adding a source case there.
 */
class CountryPicker extends Component
{
    public bool $open = false;

    public string $search = '';

    /** Which dataset to show (resolved by CountryPickerSources). */
    public string $source = '';

    /** Extra args for the source (e.g. ['has_voice' => true]). */
    public array $args = [];

    /** Opener token echoed back on pick, so multiple openers can share one modal. */
    public string $for = '';

    public ?string $title = null;

    #[On('open-country-picker')]
    public function openModal(string $source, array $args = [], string $for = '', ?string $title = null): void
    {
        $this->source = $source;
        $this->args = $args;
        $this->for = $for;
        $this->title = $title;
        $this->search = '';
        $this->open = true;
    }

    public function pick(string $code, string $name): void
    {
        $this->dispatch('country-picked', code: $code, name: $name, for: $this->for);
        $this->close();
    }

    /** Clear the opener's country filter (the "All countries" option). */
    public function clearPick(): void
    {
        $this->dispatch('country-picked', code: '', name: '', for: $this->for);
        $this->close();
    }

    public function close(): void
    {
        $this->open = false;
        $this->search = '';
    }

    public function render()
    {
        $options = $this->open ? CountryPickerSources::options($this->source, $this->args) : [];

        return view('livewire.country-picker', [
            'options' => $options,
        ]);
    }
}
