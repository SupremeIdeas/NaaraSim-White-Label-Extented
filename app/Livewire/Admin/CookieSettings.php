<?php

namespace App\Livewire\Admin;

use App\Models\CookieConsentLog;
use App\Models\Setting;
use App\Support\Auditor;
use App\Support\CookieConsent\CookieConsent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Cookies: the banner's wording and look (with a live preview), how it behaves, what it controls, the consent log and an export. Generic platform feature. */
#[Layout('components.layouts.admin')]
class CookieSettings extends Component
{
    public bool $enabled = true;

    public string $title = '';

    public string $body = '';

    public string $accept = '';

    public string $reject = '';

    public string $customise = '';

    public string $layout = 'card';

    public string $position = 'right';

    public string $theme = 'skin';

    /** @var array{bg:string,text:string,button:string,button_text:string} */
    public array $custom = ['bg' => '#0D1B2A', 'text' => '#F1F5F9', 'button' => '#84CC16', 'button_text' => '#0D1B2A'];

    public string $renewDays = '180';

    public string $retentionMonths = '36';

    public bool $respectGpc = true;

    public bool $showReopen = true;

    public string $filter = 'all';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $d = CookieConsent::design();
        $this->enabled = CookieConsent::enabled();
        foreach (['title', 'body', 'accept', 'reject', 'customise', 'layout', 'position', 'theme', 'custom'] as $k) {
            $this->$k = $d[$k];
        }
        $this->renewDays = (string) $d['renew_days'];
        $this->retentionMonths = (string) CookieConsent::retentionMonths();
        $this->respectGpc = $d['respect_gpc'];
        $this->showReopen = $d['show_reopen'];
    }

    public function save(): void
    {
        $this->guard();
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $this->validate([
            'title' => ['required', 'string', 'max:80'], 'body' => ['required', 'string', 'max:400'],
            'accept' => ['required', 'string', 'max:24'], 'reject' => ['required', 'string', 'max:24'], 'customise' => ['required', 'string', 'max:24'],
            'layout' => ['required', 'in:'.implode(',', array_keys(CookieConsent::LAYOUTS))], 'position' => ['required', 'in:left,right'], 'theme' => ['required', 'in:'.implode(',', array_keys(CookieConsent::THEMES))],
            'custom.bg' => $hex, 'custom.text' => $hex, 'custom.button' => $hex, 'custom.button_text' => $hex,
            'renewDays' => ['required', 'integer', 'min:30', 'max:395'], 'retentionMonths' => ['required', 'integer', 'min:6', 'max:60'],
        ]);
        foreach (['title', 'body', 'accept', 'reject', 'customise', 'layout', 'position', 'theme'] as $k) {
            Setting::setValue('cookies.'.$k, trim(strip_tags($this->$k)), 'cookies');
        }
        Setting::setValue('cookies.custom', array_map('strtoupper', $this->custom), 'cookies');
        Setting::setValue('cookies.enabled', $this->enabled, 'cookies');
        Setting::setValue('cookies.renew_days', (int) $this->renewDays, 'cookies');
        Setting::setValue('cookies.retention_months', (int) $this->retentionMonths, 'cookies');
        Setting::setValue('cookies.respect_gpc', $this->respectGpc, 'cookies');
        Setting::setValue('cookies.show_reopen', $this->showReopen, 'cookies');
        Auditor::log('cookies.settings_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Saved. The banner updates on the next page load.');
    }

    /** Ask every visitor again (after a policy or service change). */
    public function askAgain(): void
    {
        $this->guard();
        $v = CookieConsent::bumpVersion();
        Auditor::log('cookies.policy_version_bumped', Setting::class, null, ['version' => $v]);
        $this->dispatch('nx-toast', type: 'success', message: 'Everyone will be asked again on their next visit.');
    }

    public function resetDesign(): void
    {
        $this->guard();
        foreach (['title', 'body', 'accept', 'reject', 'customise', 'layout', 'position', 'theme', 'custom'] as $k) {
            Setting::query()->where('key', 'cookies.'.$k)->delete();
        }
        $this->mount();
    }

    private function guard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function render()
    {
        $preview = ['theme' => $this->theme, 'custom' => $this->custom];
        $safe = array_map(fn ($v) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1 ? strtoupper($v) : '#000000', $this->custom);
        $logs = CookieConsentLog::query()->when($this->filter !== 'all', fn ($q) => $q->where('action', $this->filter))->latest('id')->limit(40)->get();

        return view('livewire.admin.cookie-settings', [
            'previewVars' => CookieConsent::cssVars(['theme' => in_array($this->theme, array_keys(CookieConsent::THEMES), true) ? $this->theme : 'skin', 'custom' => $safe]),
            'stats' => CookieConsent::stats(30), 'services' => CookieConsent::services(), 'logs' => $logs, 'version' => CookieConsent::version(),
            'categories' => (array) config('cookie_consent.categories'),
        ]);
    }
}
