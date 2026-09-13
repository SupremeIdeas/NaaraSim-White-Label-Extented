<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\SiteChrome;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Auth & Footer (Module 28). Configures the two-column auth media panel
 * (WebP/JPEG image or a short muted video + headline/subtext) and the
 * admin-assignable footer (link columns + legal row). Stored via Setting; the
 * SiteChrome cache is flushed automatically on save.
 */
#[Layout('components.layouts.admin')]
class SiteChromePage extends Component
{
    use WithFileUploads;

    // Auth panel
    public string $login_style = 'auto';   // auto | webgl | image

    public string $media_type = 'image';

    public $media = null;         // new upload (image or video)

    public $poster = null;        // optional video poster image

    public string $headline = '';

    public string $subtext = '';

    // Footer — arrays of ['heading' => , 'links' => [['label','url'], ...]]
    public array $columns = [];

    public array $legal = [];

    // Footer credit phrasing (theme-integrity blueprint §2).
    public string $credit_phrasing = SiteChrome::CREDIT_PRODUCT_OF;

    public string $credit_custom_text = '';

    public ?string $saved = null;

    public function mount(): void
    {
        $auth = SiteChrome::authPanel();
        $this->login_style = $auth['style'] ?? 'auto';
        $this->media_type = $auth['media_type'];
        $this->headline = $auth['headline'];
        $this->subtext = $auth['subtext'];
        $this->columns = SiteChrome::footerColumns();
        $this->legal = SiteChrome::footerLegal();
        $this->credit_phrasing = SiteChrome::footerCreditPhrasing();
        $this->credit_custom_text = SiteChrome::footerCreditCustomText();
    }

    // ---- footer editing -----------------------------------------------------

    public function addColumn(): void
    {
        $this->columns[] = ['heading' => 'New section', 'links' => [['label' => '', 'url' => '']]];
    }

    public function removeColumn(int $i): void
    {
        unset($this->columns[$i]);
        $this->columns = array_values($this->columns);
    }

    public function addLink(int $col): void
    {
        $this->columns[$col]['links'][] = ['label' => '', 'url' => ''];
    }

    public function removeLink(int $col, int $i): void
    {
        unset($this->columns[$col]['links'][$i]);
        $this->columns[$col]['links'] = array_values($this->columns[$col]['links']);
    }

    public function addLegal(): void
    {
        $this->legal[] = ['label' => '', 'url' => ''];
    }

    public function removeLegal(int $i): void
    {
        unset($this->legal[$i]);
        $this->legal = array_values($this->legal);
    }

    // ---- save ---------------------------------------------------------------

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'media_type' => 'required|in:image,video',
            'media' => 'nullable|file|max:8192|mimes:jpg,jpeg,webp,png,mp4,webm',
            'poster' => 'nullable|file|max:2048|mimes:jpg,jpeg,webp,png',
            'headline' => 'required|string|max:120',
            'subtext' => 'required|string|max:280',
            'columns' => 'array|max:4',
            'columns.*.heading' => 'required|string|max:40',
            'columns.*.links' => 'array|max:8',
            'columns.*.links.*.label' => 'required|string|max:40',
            'columns.*.links.*.url' => ['required', 'string', 'max:300', 'regex:#^(/|https?://)#i'],
            'legal' => 'array|max:6',
            'legal.*.label' => 'required|string|max:30',
            'legal.*.url' => ['required', 'string', 'max:300', 'regex:#^(/|https?://)#i'],
            'credit_phrasing' => ['required', 'string', 'in:'.implode(',', SiteChrome::CREDIT_PHRASINGS)],
            'credit_custom_text' => [
                'nullable', 'string', 'max:160',
                'required_if:credit_phrasing,'.SiteChrome::CREDIT_CUSTOM,
                function ($attribute, $value, $fail) {
                    if ($this->credit_phrasing === SiteChrome::CREDIT_CUSTOM && ! str_contains((string) $value, '{agency}')) {
                        $fail('Custom text must contain the {agency} placeholder — that\'s where the Supreme Ideas Agency link is inserted.');
                    }
                },
            ],
        ], [
            'columns.*.links.*.url.regex' => 'Each link must be an in-app path (/…) or a full https:// URL.',
            'legal.*.url.regex' => 'Each link must be an in-app path (/…) or a full https:// URL.',
        ]);

        Setting::setValue('site.auth.style', in_array($this->login_style, ['auto', 'webgl', 'image'], true) ? $this->login_style : 'auto', 'site');
        Setting::setValue('site.auth.media_type', $this->media_type, 'site');
        if ($this->media) {
            Setting::setValue('site.auth.media_url', MediaStorage::storePublic($this->media, 'auth'), 'site');
        }
        if ($this->poster) {
            Setting::setValue('site.auth.poster_url', MediaStorage::storePublic($this->poster, 'auth'), 'site');
        }
        Setting::setValue('site.auth.headline', trim($this->headline), 'site');
        Setting::setValue('site.auth.subtext', trim($this->subtext), 'site');
        Setting::setValue('site.footer.columns', $this->columns, 'site');
        Setting::setValue('site.footer.legal', $this->legal, 'site');
        Setting::setValue('site.footer.credit_phrasing', $this->credit_phrasing, 'site');
        Setting::setValue('site.footer.credit_custom_text', trim($this->credit_custom_text), 'site');

        SiteChrome::flush();
        Auditor::log('site.chrome.updated', Setting::class, null, ['media_type' => $this->media_type]);
        $this->reset('media', 'poster');
        $this->saved = 'Saved. Your auth panel and footer are live across the site.';
        $this->dispatch('nx-toast', type: 'success', message: 'Auth & footer saved.');
    }

    public function render()
    {
        return view('livewire.admin.site-chrome', [
            'hasMedia' => SiteChrome::hasAuthMedia(),
            'currentMedia' => SiteChrome::authPanel()['media_url'],
        ]);
    }
}
