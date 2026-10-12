<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\Trustpilot\TrustpilotSettings as TP;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Trustpilot (Trustpilot blueprint Phase 1, display layer): master switch, profile, per-widget template ids, placements, independent testimonial switch, reviews page, CSP origins. */
#[Layout('components.layouts.admin')]
class TrustpilotPanel extends Component
{
    public bool $enabled = false;

    public string $businessUnitId = '';

    public string $profileUrl = '';

    public string $integrationKey = '';

    public string $locale = 'en-US';

    /** @var array<string, string> */
    public array $templates = [];

    /** @var array<string, bool> */
    public array $placements = [];

    public bool $defaultTestimonials = true;

    public bool $pageEnabled = false;

    public string $pageTitle = '';

    public string $pageIntro = '';

    public bool $clickToLoad = false;

    /** @var array<string, string> directive => space separated origins */
    public array $csp = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->enabled = TP::enabled();
        $this->businessUnitId = TP::businessUnitId();
        $this->profileUrl = TP::profileUrl();
        $this->integrationKey = TP::integrationKey();
        $this->locale = TP::locale();
        foreach (array_keys(TP::WIDGET_TYPES) as $t) {
            $this->templates[$t] = TP::templateId($t);
        }
        foreach (array_keys(TP::PLACEMENTS) as $k) {
            $this->placements[$k] = TP::placementOn($k);
        }
        $this->defaultTestimonials = TP::defaultTestimonialsOn();
        $this->pageEnabled = (bool) \App\Models\Setting::getValue('trustpilot.page_enabled', false);
        $this->pageTitle = (string) \App\Models\Setting::getValue('trustpilot.page_title', '');
        $this->pageIntro = (string) \App\Models\Setting::getValue('trustpilot.page_intro', '');
        $this->clickToLoad = TP::clickToLoad();
        foreach (TP::cspHosts() as $dir => $list) {
            $this->csp[$dir] = implode(' ', $list);
        }
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'businessUnitId' => ['nullable', 'regex:/^[0-9a-f]{20,40}$/i'],
            'profileUrl' => ['nullable', 'url', 'max:200', 'regex:#^https://([a-z0-9-]+\.)?trustpilot\.com/#i'],
            'integrationKey' => ['nullable', 'regex:/^[A-Za-z0-9]{8,40}$/'],
            'locale' => ['required', 'regex:/^[a-z]{2}-[A-Z]{2}$/'],
            'templates.*' => ['nullable', 'regex:/^[0-9a-f]{20,40}$/i'],
            'pageTitle' => ['nullable', 'max:80'],
            'pageIntro' => ['nullable', 'max:300'],
        ], ['businessUnitId.regex' => 'Paste the Business Unit ID exactly as Trustpilot shows it (letters and digits only).', 'profileUrl.regex' => 'Use your https://www.trustpilot.com/review/... address.', 'templates.*.regex' => 'Paste the template ID from the TrustBox snippet Trustpilot gives you.']);
        $before = ['enabled' => TP::enabled(), 'page' => TP::dedicatedPageOn(), 'default' => TP::defaultTestimonialsOn()];
        TP::put('enabled', $this->enabled);
        TP::put('business_unit_id', trim($this->businessUnitId));
        TP::put('profile_url', trim($this->profileUrl));
        TP::put('integration_key', trim($this->integrationKey));
        TP::put('locale', $this->locale);
        TP::put('templates', array_map('trim', $this->templates));
        TP::put('placements', array_map('boolval', $this->placements));
        TP::put('default_testimonials_on', $this->defaultTestimonials);
        TP::put('page_enabled', $this->pageEnabled);
        TP::put('page_title', trim($this->pageTitle));
        TP::put('page_intro', trim($this->pageIntro));
        TP::put('click_to_load', $this->clickToLoad);
        TP::put('csp_hosts', array_map(fn ($v) => array_values(array_filter(preg_split('/\s+/', trim($v)) ?: [])), $this->csp));
        Auditor::log('trustpilot.settings_saved', null, null, ['before' => $before, 'enabled' => $this->enabled, 'page' => $this->pageEnabled, 'default_testimonials' => $this->defaultTestimonials]);
        \Illuminate\Support\Facades\Cache::forget('home.sections');
        $this->dispatch('nx-toast', type: 'success', message: 'Saved.');
    }

    /** Suggestion only (never silent): enough Trustpilot content that the admin may want to retire the default testimonials. */
    public function render()
    {
        return view('livewire.admin.trustpilot-panel', [
            'types' => TP::WIDGET_TYPES, 'places' => TP::PLACEMENTS,
            'live' => TP::active(), 'missingTemplates' => collect(TP::PLACEMENTS)->filter(fn ($c, $k) => ($this->placements[$k] ?? false) && TP::templateId($c['type']) === '' && $this->templates[$c['type']] === '')->keys()->all(),
        ]);
    }
}
