<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

/**
 * Public blog (Module 30). Lists published posts (optionally by category) and
 * renders a single post; drafts and future-dated posts are never shown.
 */
class BlogController extends Controller
{
    public function index(Request $request)
    {
        $activeCategory = $request->query('category');

        $posts = Post::published()
            ->when($activeCategory, fn ($q) => $q->where('category', $activeCategory))
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        $categories = Post::published()
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('blog.index', compact('posts', 'categories', 'activeCategory'));
    }

    public function show(Post $post)
    {
        // A draft or future-dated post is not public — 404 unless it's live, or
        // an admin is previewing it.
        $live = $post->status === 'published' && $post->published_at && $post->published_at->isPast();
        abort_unless($live || (auth()->check() && auth()->user()->hasAnyRole(['super_admin', 'admin'])), 404);

        return view('blog.show', ['post' => $post->load('author')]);
    }
}
