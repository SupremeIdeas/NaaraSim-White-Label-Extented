<?php

namespace App\Livewire\Admin;

use App\Models\Post;
use App\Support\Auditor;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Admin → Blog (Module 30). List, create and edit posts: title/slug, category,
 * excerpt, body (safe light markup), WebP/JPEG cover, SEO title/description,
 * and draft/publish. Publishing stamps published_at; only published + past-dated
 * posts are ever public.
 */
#[Layout('components.layouts.admin')]
class Posts extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $title = '';

    public string $slug = '';

    public string $category = 'News';

    public string $excerpt = '';

    public string $body = '';

    public $cover = null;

    public ?string $coverUrl = null;

    public string $meta_title = '';

    public string $meta_description = '';

    public string $accent_color = '';

    public string $status = 'draft';

    // Blog hero (4-image reveal) — admin-managed via BlogSettings.
    public string $heroTitle = '';
    public string $heroSubtitle = '';
    public array $heroImages = [];
    public $heroUpload = null;

    public ?string $saved = null;

    // --- Claude assist ---
    public ?array $suggestion = null;

    public string $aiImagePrompt = '';

    public ?string $aiError = null;

    private function assistant(): \App\Services\Blog\BlogArticleAssistant
    {
        return app(\App\Services\Blog\BlogArticleAssistant::class);
    }

    /** Suggest the next most-important article based on existing posts. */
    public function suggestTopic(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->aiError = null;
        try {
            $this->suggestion = $this->assistant()->suggestTopic();
        } catch (\Throwable $e) {
            $this->aiError = 'Claude could not suggest a topic right now.';
        }
    }

    /** Accept the suggestion into a fresh draft form. */
    public function useSuggestion(): void
    {
        if (! $this->suggestion) {
            return;
        }
        $this->resetForm();
        $this->title = $this->suggestion['title'] ?? '';
        $this->category = $this->suggestion['category'] ?? 'Guides';
        $this->slug = Str::slug($this->title);
        $this->showForm = true;
    }

    /** Generate the full article body + SEO fields for the current title. */
    public function generateDraft(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['title' => 'required|string|max:160']);
        $this->aiError = null;
        try {
            $angle = $this->suggestion['angle'] ?? '';
            $draft = $this->assistant()->generateDraft($this->title, $this->category, $angle);
            $this->body = $draft['body'];
            $this->excerpt = $draft['excerpt'];
            $this->meta_title = $draft['meta_title'];
            $this->meta_description = $draft['meta_description'];
            $this->dispatch('nx-toast', type: 'success', message: 'Draft generated — review, then add a cover image.');
        } catch (\Throwable $e) {
            $this->aiError = 'Claude could not generate the draft right now.';
        }
    }

    /** Produce a cover-image prompt for the admin to copy + generate elsewhere. */
    public function getImagePrompt(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->aiError = null;
        try {
            $this->aiImagePrompt = $this->assistant()->imagePrompt($this->title, $this->body);
        } catch (\Throwable $e) {
            $this->aiError = 'Claude could not produce an image prompt right now.';
        }
    }

    /** Reformat the current body for clean, consistent reading. */
    public function reformatBody(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        if (trim($this->body) === '') {
            return;
        }
        $this->aiError = null;
        try {
            $this->body = $this->assistant()->reformat($this->body);
            $this->dispatch('nx-toast', type: 'success', message: 'Reformatted for readability.');
        } catch (\Throwable $e) {
            $this->aiError = 'Claude could not reformat right now.';
        }
    }

    public function mount(): void
    {
        $hero = \App\Support\BlogSettings::all();
        $this->heroTitle = (string) $hero['title'];
        $this->heroSubtitle = (string) $hero['subtitle'];
        $this->heroImages = \App\Support\BlogSettings::images();
    }

    public function addHeroImage(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['heroUpload' => 'required|image|mimes:webp,jpg,jpeg,png|max:3000']);
        $this->heroImages[] = MediaStorage::storePublic($this->heroUpload, 'blog-hero');
        $this->heroImages = array_slice($this->heroImages, 0, 4);
        $this->heroUpload = null;
    }

    public function removeHeroImage(int $i): void
    {
        unset($this->heroImages[$i]);
        $this->heroImages = array_values($this->heroImages);
    }

    public function saveHero(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'heroTitle' => 'required|string|max:120',
            'heroSubtitle' => 'nullable|string|max:300',
        ]);
        \App\Support\BlogSettings::save([
            'title' => trim($this->heroTitle),
            'subtitle' => trim($this->heroSubtitle),
            'images' => $this->heroImages,
        ]);
        $this->dispatch('nx-toast', type: 'success', message: 'Blog hero saved.');
    }

    public function newPost(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $post = Post::findOrFail($id);
        $this->editingId = $post->id;
        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->category = $post->category;
        $this->excerpt = (string) $post->excerpt;
        $this->body = $post->body;
        $this->coverUrl = $post->cover_image_url;
        $this->cover = null;
        $this->accent_color = (string) $post->accent_color;
        $this->meta_title = (string) $post->meta_title;
        $this->meta_description = (string) $post->meta_description;
        $this->status = $post->status;
        $this->showForm = true;
        $this->saved = null;
    }

    public function updatedTitle(): void
    {
        // Auto-suggest a slug only for a brand-new post the user hasn't slugged.
        if ($this->editingId === null && $this->slug === '') {
            $this->slug = Str::slug($this->title);
        }
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'title' => 'required|string|max:160',
            'slug' => 'nullable|string|max:180|regex:/^[a-z0-9\-]*$/',
            'category' => 'required|string|max:40',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string|max:60000',
            'cover' => 'nullable|file|mimes:webp,jpg,jpeg,png|max:2048',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:300',
            'accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'status' => 'required|in:draft,published',
        ]);

        $slug = $this->slug !== '' ? Str::slug($this->slug) : $this->title;
        $slug = Post::uniqueSlug($slug, $this->editingId);

        $data = [
            'title' => trim($this->title),
            'slug' => $slug,
            'category' => trim($this->category),
            'excerpt' => trim($this->excerpt) ?: null,
            'body' => trim($this->body),
            'meta_title' => trim($this->meta_title) ?: null,
            'meta_description' => trim($this->meta_description) ?: null,
            'accent_color' => trim($this->accent_color) ?: null,
            'status' => $this->status,
        ];

        if ($this->cover) {
            $data['cover_image_url'] = MediaStorage::storePublic($this->cover, 'blog');
        }

        if ($this->editingId) {
            $post = Post::findOrFail($this->editingId);
            // Stamp published_at the first time it goes live.
            if ($this->status === 'published' && ! $post->published_at) {
                $data['published_at'] = now();
            }
            if ($this->status === 'draft') {
                $data['published_at'] = null;
            }
            $post->update($data);
        } else {
            $data['author_id'] = Auth::id();
            $data['published_at'] = $this->status === 'published' ? now() : null;
            $post = Post::create($data);
        }

        Auditor::log('post.saved', Post::class, $post->id, ['status' => $post->status]);
        $this->saved = "Post “{$post->title}” saved ({$post->status}).";
        $this->dispatch('nx-toast', type: 'success', message: 'Post saved.');
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $post = Post::findOrFail($id);
        Auditor::log('post.deleted', Post::class, $post->id);
        $post->delete();
        $this->saved = 'Post deleted.';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'showForm', 'title', 'slug', 'category', 'excerpt', 'body', 'cover', 'coverUrl', 'accent_color', 'meta_title', 'meta_description', 'status']);
        $this->category = 'News';
        $this->status = 'draft';
    }

    public function render()
    {
        return view('livewire.admin.posts', [
            'posts' => Post::latest()->paginate(10),
            'aiEnabled' => $this->assistant()->enabled(),
        ]);
    }
}
