<?php

namespace App\Livewire\Admin;

use App\Models\Post;
use App\Support\Auditor;
use App\Support\Blog\BlogReaderSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Blog reader: share channels + text, the end-of-article "Keep reading" prompt, header blog preview, and the reader analytics card. */
#[Layout('components.layouts.admin')]
class BlogReader extends Component
{
    public array $channels = [];

    public string $share_text = '';

    public string $share_hashtags = '';

    public bool $share_native = true;

    public bool $keep_enabled = true;

    public bool $keep_popup = true;

    public int $keep_count = 6;

    public int $keep_scroll_pct = 85;

    public int $keep_dwell_pct = 40;

    public array $exclude = [];

    public string $header_blog_layout = 'carousel';

    public int $header_blog_count = 4;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $s = BlogReaderSettings::all();
        $this->channels = $s['share_channels'];
        $this->share_text = $s['share_text'];
        $this->share_hashtags = $s['share_hashtags'];
        $this->share_native = $s['share_native'];
        $this->keep_enabled = $s['keep_enabled'];
        $this->keep_popup = $s['keep_popup'];
        $this->keep_count = $s['keep_count'];
        $this->keep_scroll_pct = $s['keep_scroll_pct'];
        $this->keep_dwell_pct = $s['keep_dwell_pct'];
        $this->exclude = $s['keep_exclude_categories'];
        $this->header_blog_layout = $s['header_blog_layout'];
        $this->header_blog_count = $s['header_blog_count'];
    }

    public function moveChannel(string $key, string $dir): void
    {
        $i = array_search($key, $this->channels, true);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= count($this->channels)) {
            return;
        }
        [$this->channels[$i], $this->channels[$j]] = [$this->channels[$j], $this->channels[$i]];
    }

    public function toggleChannel(string $key): void
    {
        $this->channels = in_array($key, $this->channels, true)
            ? array_values(array_diff($this->channels, [$key]))
            : [...$this->channels, $key];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'share_text' => ['nullable', 'string', 'max:200'], 'share_hashtags' => ['nullable', 'string', 'max:120'],
            'keep_count' => ['required', 'integer', 'min:3', 'max:8'], 'keep_scroll_pct' => ['required', 'integer', 'min:50', 'max:100'],
            'keep_dwell_pct' => ['required', 'integer', 'min:0', 'max:100'], 'header_blog_layout' => ['required', 'in:'.implode(',', BlogReaderSettings::LAYOUTS)],
            'header_blog_count' => ['required', 'integer', 'min:1', 'max:8'],
        ]);
        BlogReaderSettings::save([
            'share_channels' => $this->channels, 'share_text' => $this->share_text, 'share_hashtags' => $this->share_hashtags, 'share_native' => $this->share_native,
            'keep_enabled' => $this->keep_enabled, 'keep_popup' => $this->keep_popup, 'keep_count' => $this->keep_count, 'keep_scroll_pct' => $this->keep_scroll_pct,
            'keep_dwell_pct' => $this->keep_dwell_pct, 'keep_exclude_categories' => $this->exclude, 'header_blog_layout' => $this->header_blog_layout,
            'header_blog_count' => $this->header_blog_count,
        ]);
        Auditor::log('blog.reader_settings_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Saved.');
    }

    public function render()
    {
        $since = now()->subDays(30)->toDateString();
        $t = DB::table('blog_reader_events')->where('day', '>=', $since)->select('event', 'channel', DB::raw('SUM(total) as n'))->groupBy('event', 'channel')->get();
        $by = fn (string $e) => (int) $t->where('event', $e)->sum('n');
        $shown = $by('readnext_shown');

        return view('livewire.admin.blog-reader', [
            'all' => (array) config('blog_share.channels'),
            'categories' => Post::query()->distinct()->orderBy('category')->pluck('category'),
            'stats' => [
                'share_open' => $by('share_open'), 'share_click' => $by('share_click'), 'share_copy' => $by('share_copy'),
                'shown' => $shown, 'clicked' => $by('readnext_clicked'), 'dismissed' => $by('readnext_dismissed'), 'disabled' => $by('readnext_disabled'),
                'ctr' => $shown > 0 ? round($by('readnext_clicked') / $shown * 100, 1) : null,
                'dismiss_rate' => $shown > 0 ? round($by('readnext_dismissed') / $shown * 100, 1) : null,
            ],
            'channelClicks' => $t->where('event', 'share_click')->sortByDesc('n')->values(),
        ]);
    }
}
