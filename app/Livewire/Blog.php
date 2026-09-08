<?php

namespace App\Livewire;

use App\Models\Post;
use App\Support\BlogSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public blog (Blog overhaul). A single component that serves both the public
 * marketing site and, via the admin-assignable floating nav, signed-in visitors
 * — same data, same look. 4-image reveal hero, a swelling "recents" carousel,
 * and an INFINITE-SCROLL main feed (standard pagination that simply stops with a
 * "you're all caught up" note once the archive is exhausted — the felt-right
 * choice over looping, which reads as broken for real articles). The per-post
 * accent colour drives a scroll-tied background tint (handled client-side).
 */
#[Layout('components.layouts.marketing')]
class Blog extends Component
{
    #[Url(as: 'category')]
    public ?string $category = null;

    public int $perPage = 9;

    public function updatingCategory(): void
    {
        $this->perPage = 9;
    }

    public function loadMore(): void
    {
        $this->perPage += 6;
    }

    public function render()
    {
        $base = Post::published()
            ->when($this->category, fn ($q) => $q->where('category', $this->category));

        $total = (clone $base)->count();
        $posts = $base->orderByDesc('published_at')->limit($this->perPage)->get();

        return view('livewire.blog', [
            'hero' => BlogSettings::all(),
            // Recents mirrors the active filter, so a category view stays coherent.
            'featured' => Post::published()
                ->when($this->category, fn ($q) => $q->where('category', $this->category))
                ->orderByDesc('published_at')->limit(8)->get(),
            'posts' => $posts,
            'hasMore' => $total > $this->perPage,
            'categories' => Post::published()->distinct()->orderBy('category')->pluck('category'),
        ]);
    }
}
