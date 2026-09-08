<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\Reaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Post reactions surface (component-library batch 2 §5). One reaction per user
 * per post, toggled in place. Guests are sent to log in rather than shown a dead
 * control. Reaction types + glyphs come from the Reaction model — no emoji.
 */
class PostReactions extends Component
{
    public Post $post;

    /** The type the current user holds (mirrored client-side for instant feedback). */
    public ?string $mine = null;

    public function mount(Post $post): void
    {
        $this->post = $post;
        $this->mine = Auth::check() ? $post->userReaction(Auth::user()) : null;
    }

    public function react(string $type): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if (! Reaction::isValidType($type)) {
            return;
        }

        $this->mine = $this->post->react(Auth::user(), $type);
        unset($this->counts); // recompute
    }

    #[Computed]
    public function counts(): array
    {
        return $this->post->reactionCounts();
    }

    public function render()
    {
        return view('livewire.post-reactions', [
            'types' => Reaction::TYPES,
            'counts' => $this->counts,
            'total' => array_sum($this->counts),
        ]);
    }
}
