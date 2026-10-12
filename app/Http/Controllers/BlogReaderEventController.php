<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Counts reader events (share + keep-reading) into daily totals. No user, IP or session is stored. */
class BlogReaderEventController extends Controller
{
    public const EVENTS = ['share_open', 'share_click', 'share_copy', 'readnext_shown', 'readnext_clicked', 'readnext_dismissed', 'readnext_disabled'];

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'event' => ['required', 'in:'.implode(',', self::EVENTS)],
            'channel' => ['nullable', 'string', 'max:24', 'regex:/^[a-z0-9_-]*$/'],
            'post' => ['nullable', 'integer'],
        ]);
        $channel = (string) ($d['channel'] ?? '');
        if ($channel !== '' && ! array_key_exists($channel, (array) config('blog_share.channels', [])) && $channel !== 'native' && $channel !== 'copy') {
            $channel = 'other';
        }
        $post = (int) ($d['post'] ?? 0);
        if ($post > 0 && ! Post::published()->whereKey($post)->exists()) {
            $post = 0;
        }

        $day = now()->toDateString();
        $key = ['day' => $day, 'event' => $d['event'], 'channel' => $channel, 'post_id' => $post];
        DB::table('blog_reader_events')->insertOrIgnore($key + ['total' => 0]);
        DB::table('blog_reader_events')->where($key)->increment('total');

        return response()->json(['ok' => true]);
    }
}
