<?php

namespace App\Livewire\Admin;

use App\Livewire\Account\Appearance;
use Livewire\Attributes\Layout;

/** An admin's OWN appearance, shown inside the admin panel. Same preference and same write path as any member. */
#[Layout('components.layouts.admin')]
class MyAppearance extends Appearance
{
    protected function backRoute(): string
    {
        return route('admin.account', ['adminGateway' => request()->route('adminGateway')]);
    }
}
