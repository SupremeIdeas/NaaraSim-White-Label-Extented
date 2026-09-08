<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\TaxRates as TaxRatesSupport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Tax rates (BUILD-7 §2). Set a VAT/tax percent per country ISO. Ships
 * empty; a rate only applies where the operator explicitly adds one. This is a
 * support tool, not a tax-compliance decision — see docs/PLATFORM-STATE.md.
 */
#[Layout('components.layouts.admin')]
class TaxRates extends Component
{
    /** @var array<int, array{country:string, rate:float|string}> */
    public array $rows = [];

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        foreach (TaxRatesSupport::all() as $country => $rate) {
            $this->rows[] = ['country' => $country, 'rate' => $rate];
        }
        if ($this->rows === []) {
            $this->rows[] = ['country' => '', 'rate' => ''];
        }
    }

    public function addRow(): void
    {
        $this->rows[] = ['country' => '', 'rate' => ''];
    }

    public function removeRow(int $i): void
    {
        unset($this->rows[$i]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $rates = [];
        foreach ($this->rows as $row) {
            $c = strtoupper(trim((string) ($row['country'] ?? '')));
            if ($c !== '') {
                $rates[$c] = (float) ($row['rate'] ?? 0);
            }
        }
        TaxRatesSupport::save($rates);
        Auditor::log('tax.rates_updated', payload: ['countries' => array_keys($rates)]);
        $this->saved = 'Tax rates saved. Tax applies only to the countries listed here.';
    }

    public function render()
    {
        return view('livewire.admin.tax-rates');
    }
}
