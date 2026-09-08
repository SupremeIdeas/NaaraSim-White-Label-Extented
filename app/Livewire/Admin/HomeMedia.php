<?php

namespace App\Livewire\Admin;

use App\Models\HomeVideo;
use App\Models\Setting;
use App\Support\Auditor;
use App\Support\HomeStory;
use App\Support\HomeVideos;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Homepage media (BUILD-3 §8 + §9). One place to manage the homepage
 * video section (entries + heading) and the scroll-revealed story section.
 */
#[Layout('components.layouts.admin')]
class HomeMedia extends Component
{
    use WithFileUploads;

    // Story (§9).
    public bool $story_enabled = false;

    public string $story_eyebrow = '';

    public string $story_heading = '';

    public string $story_body = '';

    public string $story_transition = '';

    // Video section headings (§8).
    public string $vid_heading = '';

    public string $vid_subheading = '';

    // New-video form (§8).
    public string $v_title = '';

    public string $v_source = 'youtube';

    public string $v_youtube = '';

    public string $v_orientation = 'portrait';

    public $v_upload = null;   // self-hosted clip

    public $v_poster = null;   // poster image

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);

        $s = HomeStory::current();
        $this->story_enabled = $s['enabled'];
        $this->story_eyebrow = $s['eyebrow'];
        $this->story_heading = $s['heading'];
        $this->story_body = $s['body'];
        $this->story_transition = $s['transition'];

        $this->vid_heading = HomeVideos::heading();
        $this->vid_subheading = HomeVideos::subheading();
    }

    public function saveStory(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'story_eyebrow' => ['nullable', 'string', 'max:60'],
            'story_heading' => ['nullable', 'string', 'max:160'],
            'story_body' => ['nullable', 'string', 'max:2000'],
            'story_transition' => ['nullable', 'string', 'max:160'],
        ]);

        Setting::setValue(HomeStory::ENABLED_KEY, $this->story_enabled, 'home');
        Setting::setValue(HomeStory::EYEBROW_KEY, trim($this->story_eyebrow), 'home');
        Setting::setValue(HomeStory::HEADING_KEY, trim($this->story_heading), 'home');
        Setting::setValue(HomeStory::BODY_KEY, trim($this->story_body), 'home');
        Setting::setValue(HomeStory::TRANSITION_KEY, trim($this->story_transition), 'home');
        HomeStory::flush();

        Auditor::log('home.story_updated');
        $this->saved = 'Story section saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Story section saved.');
    }

    public function saveVideoHeading(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'vid_heading' => ['nullable', 'string', 'max:120'],
            'vid_subheading' => ['nullable', 'string', 'max:200'],
        ]);

        Setting::setValue(HomeVideos::HEADING_KEY, trim($this->vid_heading), 'home');
        Setting::setValue(HomeVideos::SUBHEADING_KEY, trim($this->vid_subheading), 'home');
        HomeVideos::flush();

        $this->saved = 'Video section heading saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Saved.');
    }

    public function addVideo(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $rules = [
            'v_title' => ['required', 'string', 'max:120'],
            'v_source' => ['required', 'in:youtube,upload'],
            'v_orientation' => ['required', 'in:portrait,landscape'],
            'v_poster' => ['nullable', ...MediaStorage::uploadRules()],
        ];
        if ($this->v_source === 'youtube') {
            $rules['v_youtube'] = ['required', 'string', 'max:255'];
        } else {
            $rules['v_upload'] = ['required', 'file', 'mimetypes:video/mp4,video/webm', 'max:'.MediaStorage::MAX_VIDEO_KB];
        }
        $this->validate($rules);

        $attrs = [
            'title' => trim($this->v_title),
            'source_type' => $this->v_source,
            'orientation' => $this->v_orientation,
            'is_active' => true,
            'sort_order' => (int) (HomeVideo::max('sort_order') + 1),
        ];

        if ($this->v_source === 'youtube') {
            $id = HomeVideo::parseYouTubeId($this->v_youtube);
            if ($id === null) {
                $this->addError('v_youtube', 'That doesn’t look like a YouTube link or video id.');

                return;
            }
            $attrs['youtube_id'] = $id;
        } else {
            $attrs['video_url'] = MediaStorage::storePublic($this->v_upload, 'home-videos');
        }

        if ($this->v_poster) {
            $attrs['poster_url'] = MediaStorage::storePublic($this->v_poster, 'home-videos');
        }

        HomeVideo::create($attrs);
        HomeVideos::flush();

        $this->reset(['v_title', 'v_youtube', 'v_upload', 'v_poster']);
        $this->v_source = 'youtube';
        $this->v_orientation = 'portrait';
        Auditor::log('home.video_added');
        $this->dispatch('nx-toast', type: 'success', message: 'Video added.');
    }

    public function toggleVideo(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $v = HomeVideo::find($id);
        if ($v) {
            $v->update(['is_active' => ! $v->is_active]);
            HomeVideos::flush();
        }
    }

    public function deleteVideo(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        HomeVideo::where('id', $id)->delete();
        HomeVideos::flush();
        $this->dispatch('nx-toast', type: 'success', message: 'Video removed.');
    }

    public function render()
    {
        return view('livewire.admin.home-media', [
            'videos' => HomeVideo::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}
