<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\IconPacks\IconPacks;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Icon packs: choose the menu icon colourway, or let it follow the site theme. */
#[Layout('components.layouts.admin')]
class IconPacksPage extends Component
{
    public string $pack = 'follow-theme';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->pack = IconPacks::choice();
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['pack' => 'required|string']);
        IconPacks::setChoice($this->pack);
        Auditor::log('icon_pack.set', null, null, ['pack' => $this->pack]);
        $this->dispatch('nx-toast', type: 'success', message: 'Icon pack saved. Every menu now uses it.');
    }

    public function render()
    {
        return view('livewire.admin.icon-packs');
    }
}
