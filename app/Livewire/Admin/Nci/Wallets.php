<?php

namespace App\Livewire\Admin\Nci;

use App\Models\ProviderRegistry;
use App\Support\OperationsCenter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * NAARA-BUILD-17 §1.5 — Wallets. Extends the per-provider balance already probed
 * by ProviderHealth into a table: balance, a volume-based burn estimate from
 * recent provider_outcomes (not a separate tracker), and the low-balance flag the
 * health probe already sets (status = 'low').
 */
#[Layout('components.layouts.admin')]
class Wallets extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('nci.view'), 403);
    }

    public function render()
    {
        // Only providers that actually carry a wallet balance concept.
        $rows = ProviderRegistry::whereNotNull('balance')->orderBy('provider_key')->get()
            ->map(fn (ProviderRegistry $r) => [
                'provider_key' => $r->provider_key,
                'stack' => $r->stack,
                'balance' => $r->balance,
                'low' => $r->status === 'low',
                'burn' => OperationsCenter::burnPerDay($r->provider_key),
            ]);

        return view('livewire.admin.nci.wallets', ['rows' => $rows]);
    }
}
