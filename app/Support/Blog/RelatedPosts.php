<?php

namespace App\Support\Blog;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

/**
 * "Keep reading" candidates for an article: same category first, then most recent, never the current post, never an excluded category,
 * back-filled with the latest posts so the carousel is not empty. Fewer than 3 candidates hides the feature. Cached per post and busted
 * on every post save/delete (Post::booted) and settings save.
 */
final class RelatedPosts
{
    private const VER = 'blog.related.ver';

    public static function min(): int
    {
        return 3;
    }

    /** @return list<array<string,mixed>> plain arrays (cache-safe) */
    public static function for(Post $post, ?int $limit = null): array
    {
        $limit ??= (int) BlogReaderSettings::get('keep_count', 6);
        $ver = (int) Cache::get(self::VER, 1);
        $rows = Cache::remember("blog.related.$ver.{$post->id}.$limit", 600, function () use ($post, $limit) {
            $exclude = (array) BlogReaderSettings::get('keep_exclude_categories', []);
            $base = fn () => Post::published()->where('id', '!=', $post->id)->when($exclude, fn ($q) => $q->whereNotIn('category', $exclude));
            $same = $base()->where('category', $post->category)->orderByDesc('published_at')->limit($limit)->get();
            $rest = $same->count() < $limit
                ? $base()->whereNotIn('id', $same->pluck('id'))->orderByDesc('published_at')->limit($limit - $same->count())->get()
                : collect();

            return $same->concat($rest)->map(fn (Post $p) => [
                'id' => $p->id, 'slug' => $p->slug, 'title' => $p->title, 'category' => $p->category,
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) $p->excerpt), 110),
                'cover' => $p->cover_image_url, 'minutes' => max(1, (int) ceil(str_word_count(strip_tags((string) $p->body)) / 220)),
                'url' => route('blog.show', $p->slug),
            ])->all();
        });

        return count($rows) >= self::min() ? $rows : [];
    }

    /** @return list<array<string,mixed>> the newest posts for the header menu preview (cached, busted on publish) */
    public static function latest(int $count): array
    {
        $ver = (int) Cache::get(self::VER, 1);

        return Cache::remember("blog.latest.$ver.$count", 600, fn () => Post::published()->orderByDesc('published_at')->limit($count)->get()->map(fn (Post $p) => [
            'id' => $p->id, 'slug' => $p->slug, 'title' => $p->title, 'category' => $p->category,
            'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) $p->excerpt), 90),
            'cover' => $p->cover_image_url, 'minutes' => max(1, (int) ceil(str_word_count(strip_tags((string) $p->body)) / 220)),
            'url' => route('blog.show', $p->slug),
        ])->all());
    }

    public static function flush(): void
    {
        try {
            Cache::put(self::VER, (int) Cache::get(self::VER, 1) + 1, 86400 * 30);
        } catch (\Throwable) {
        }
    }
}
