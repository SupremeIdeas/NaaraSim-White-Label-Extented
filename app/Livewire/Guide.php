<?php

namespace App\Livewire;

use App\Support\UserGuides;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The in-app user guide + agreement, planted in the customer area. Defaults to
 * the audience that fits the signed-in user (normal user / merchant / merchant
 * V2), and any audience can be viewed explicitly (e.g. the developer guide).
 */
#[Layout('components.layouts.customer')]
class Guide extends Component
{
    #[Url]
    public ?string $audience = null;

    public function mount(): void
    {
        if (! in_array($this->audience, UserGuides::AUDIENCES, true)) {
            $this->audience = UserGuides::audienceFor(Auth::user());
        }
    }

    public function render()
    {
        $guide = UserGuides::for($this->audience);

        return view('livewire.guide', [
            'guide' => $guide,
            'audience' => $this->audience,
        ]);
    }
}
