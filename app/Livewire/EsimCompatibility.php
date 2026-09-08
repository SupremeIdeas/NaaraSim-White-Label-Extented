<?php

namespace App\Livewire;

use App\Models\EsimCompatibleDevice;
use App\Support\Niche\DeviceCompat;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * eSIM device compatibility modal (esim_upgrade Part 2). Search across all
 * devices, Apple / Android / Others pill tabs, and per-category accordions —
 * all backed by the normalised esim_compatible_devices table. Includes the
 * *#06# / EID guidance so a user can confirm eSIM support before buying.
 *
 * Opened from anywhere via a dispatched `open-compatibility` event (one modal,
 * reused), matching the app's existing single-modal pattern.
 */
class EsimCompatibility extends Component
{
    public bool $open = false;

    public string $os = 'apple';

    public string $search = '';

    /** Best-effort auto-detect (BUILD-8 §6.2) — advisory only, never a gate. */
    public string $detectedName = '';

    /** '' | yes | no | unknown | ios (iOS reveals no specific model). */
    public string $detectedResult = '';

    #[On('open-compatibility')]
    public function openModal(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->search = '';
        $this->detectedName = '';
        $this->detectedResult = '';
    }

    /**
     * Receive the browser's best-effort device guess (User-Agent / Client Hints,
     * computed client-side) and pre-select it in the SAME list the user can still
     * browse freely (§6.3). This never unlocks a purchase — the authoritative
     * DeviceCompat::check() gate at checkout is unchanged (§6.4); here it only
     * decides which advisory banner to show.
     */
    public function detected(string $os, string $name = ''): void
    {
        if (in_array($os, ['apple', 'android', 'others'], true)) {
            $this->os = $os;
        }

        $name = trim($name);

        // iOS Safari's UA only ever says "iPhone" — no specific model (§6.2).
        // Be honest about that instead of faking a model result.
        if ($name === '' || strcasecmp($name, 'iphone') === 0) {
            if ($os === 'apple') {
                $this->detectedResult = 'ios';
                $this->detectedName = 'iPhone';
            }

            return;
        }

        $this->detectedName = $name;
        $this->search = $name; // pre-filter the list to the detected device
        $this->detectedResult = match (DeviceCompat::check($name)) {
            true => 'yes',
            false => 'no',
            default => 'unknown',
        };
    }

    public function setOs(string $os): void
    {
        if (in_array($os, ['apple', 'android', 'others'], true)) {
            $this->os = $os;
        }
    }

    public function render()
    {
        // Group the active tab's devices by category → brand, honouring search.
        $devices = EsimCompatibleDevice::query()
            ->where('os_group', $this->os)
            ->when($this->search !== '', fn ($q) => $q->where('device_name', 'like', '%'.$this->search.'%'))
            ->orderBy('category')->orderBy('brand')->orderBy('device_name')
            ->get()
            ->groupBy('category');

        return view('livewire.esim-compatibility', [
            'grouped' => $devices,
            'categoryLabels' => [
                'phone' => 'Phones', 'tablet' => 'Tablets', 'watch' => 'Smart Watches', 'laptop' => 'Laptops',
            ],
        ]);
    }
}
