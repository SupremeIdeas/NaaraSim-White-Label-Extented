<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\HtmlSanitizer;
use App\Support\MediaStorage;
use App\Support\UserGuides;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → User guides. Edit the intro, sections (heading + rich body + image) and
 * the agreement for each audience (user / merchant / merchant V2 / developer).
 * Bodies + agreement are allowlist-sanitized on save. Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class Guides extends Component
{
    use WithFileUploads;

    public string $audience = 'user';

    public string $title = '';
    public string $intro = '';
    public array $sections = [];
    public string $agreement = '';

    public $sectionImage = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->loadAudience();
    }

    public function selectAudience(string $audience): void
    {
        $this->audience = in_array($audience, UserGuides::AUDIENCES, true) ? $audience : 'user';
        $this->loadAudience();
    }

    private function loadAudience(): void
    {
        $guide = UserGuides::for($this->audience);
        $this->title = $guide->title;
        $this->intro = (string) $guide->intro;
        $this->sections = array_values((array) ($guide->sections ?? []));
        $this->agreement = (string) $guide->agreement;
    }

    public function addSection(): void
    {
        $this->sections[] = ['heading' => 'New section', 'body' => '', 'image' => ''];
    }

    public function removeSection(int $i): void
    {
        unset($this->sections[$i]);
        $this->sections = array_values($this->sections);
    }

    public function uploadSectionImage(int $i): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['sectionImage' => 'required|image|mimes:webp,jpg,jpeg,png|max:3000']);
        $url = MediaStorage::storePublic($this->sectionImage, 'guides');
        $this->sectionImage = null;
        if (isset($this->sections[$i])) {
            $this->sections[$i]['image'] = $url;
        }
    }

    public function clearSectionImage(int $i): void
    {
        if (isset($this->sections[$i])) {
            $this->sections[$i]['image'] = '';
        }
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'title' => 'required|string|max:120',
            'intro' => 'nullable|string|max:500',
        ]);

        $sections = collect($this->sections)
            ->filter(fn ($s) => is_array($s) && trim((string) ($s['heading'] ?? '')) !== '')
            ->map(fn ($s) => [
                'heading' => mb_substr(trim((string) $s['heading']), 0, 120),
                'body' => HtmlSanitizer::clean($s['body'] ?? ''),
                'image' => (string) ($s['image'] ?? ''),
            ])->values()->all();

        UserGuides::for($this->audience)->update([
            'title' => trim($this->title),
            'intro' => trim($this->intro) ?: null,
            'sections' => $sections,
            'agreement' => HtmlSanitizer::clean($this->agreement),
        ]);

        Auditor::log('guide.saved', \App\Models\UserGuide::class, null, ['audience' => $this->audience]);
        $this->dispatch('nx-toast', type: 'success', message: 'Guide saved.');
    }

    public function render()
    {
        return view('livewire.admin.guides', [
            'audiences' => UserGuides::AUDIENCES,
        ]);
    }
}
