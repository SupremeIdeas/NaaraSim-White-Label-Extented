<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\BrandSettings;
use App\Support\InstanceIdentity;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > Activation and branding. The first screen a licensee uses: it says whose platform this is. The name, the brand word swapped into
 * product names, the tagline, the company that is credited, support contacts and social links all live here and reach every page, email
 * and notification through BrandSettings and the brand word filter. Logos, colours and fonts stay on the Branding page; this page links
 * to it and shows one checklist for the whole setup.
 */
#[Layout('components.layouts.admin')]
class ActivationBranding extends Component
{
    public string $brand_name = '';

    public string $brand_word = '';

    public string $tagline = '';

    public string $legal_name = '';

    public string $agency_name = '';

    public string $agency_url = '';

    public string $website = '';

    public string $support_email = '';

    public string $support_phone = '';

    public string $address = '';

    public string $social_x = '';

    public string $social_instagram = '';

    public string $social_facebook = '';

    public string $social_linkedin = '';

    public string $social_youtube = '';

    public string $social_tiktok = '';

    public ?string $saved = null;

    /** The profile fields saved as brand.* settings. */
    private const PROFILE = ['tagline', 'legal_name', 'agency_name', 'agency_url', 'website', 'support_email', 'support_phone', 'address', 'social_x', 'social_instagram', 'social_facebook', 'social_linkedin', 'social_youtube', 'social_tiktok'];

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->brand_name = Setting::getValue('brand.name') ? BrandSettings::name() : '';
        $this->brand_word = Setting::getValue('brand.word') ? BrandSettings::word() : '';
        foreach (self::PROFILE as $f) {
            $this->{$f} = BrandSettings::profile($f);
        }
    }

    private function authorizeAdmin(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(['admin', 'super_admin']), 403);
    }

    protected function rules(): array
    {
        $url = ['nullable', 'url:http,https', 'max:255'];

        return [
            'brand_name' => ['required', 'string', 'min:2', 'max:40'],
            'brand_word' => ['required', 'string', 'min:2', 'max:24', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} \-]*$/u'],
            'tagline' => ['nullable', 'string', 'max:90'],
            'legal_name' => ['nullable', 'string', 'max:120'],
            'agency_name' => ['nullable', 'string', 'max:80'],
            'agency_url' => $url,
            'website' => $url,
            'support_email' => ['nullable', 'email', 'max:120'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:240'],
            'social_x' => $url, 'social_instagram' => $url, 'social_facebook' => $url,
            'social_linkedin' => $url, 'social_youtube' => $url, 'social_tiktok' => $url,
        ];
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        $this->validate();

        Setting::setValue('brand.name', trim($this->brand_name), 'brand');
        Setting::setValue('brand.word', trim($this->brand_word), 'brand');
        foreach (self::PROFILE as $f) {
            Setting::setValue('brand.'.$f, trim((string) $this->{$f}), 'brand');
        }
        Setting::setValue('brand.setup_done', $this->checklist()['done'] === $this->checklist()['total'] ? now()->toIso8601String() : '', 'brand');
        BrandSettings::flush();
        Auditor::log('brand.activation.updated', Setting::class, null, ['name' => $this->brand_name, 'word' => $this->brand_word]);
        $this->saved = 'Saved. Your name now shows across the site, emails and notifications.';
    }

    /** @return array{items: list<array{label: string, done: bool, hint: string, route: ?string}>, done: int, total: int} */
    public function checklist(): array
    {
        $b = BrandSettings::current();
        $items = [
            ['label' => 'Brand name and word', 'done' => (bool) Setting::getValue('brand.name') && (bool) Setting::getValue('brand.word'), 'hint' => 'Shown in the header, titles, emails and every product name.', 'route' => null],
            ['label' => 'Logo (light and dark)', 'done' => ($b['product_light'] ?? '') !== '' || ($b['product_dark'] ?? '') !== '', 'hint' => 'Upload on the Branding page. Until then a clean wordmark is shown.', 'route' => 'admin.branding'],
            ['label' => 'Favicon and app icon', 'done' => ($b['favicon'] ?? '') !== '', 'hint' => 'Upload on the Branding page. Until then your first letter is used.', 'route' => 'admin.branding'],
            ['label' => 'Brand colours', 'done' => ($b['color_primary'] ?? '') !== '' || ($b['color_accent'] ?? '') !== '', 'hint' => 'Pick a palette or your own on the Branding page.', 'route' => 'admin.branding'],
            ['label' => 'Support contact', 'done' => $this->support_email !== '' || BrandSettings::profile('support_email') !== '', 'hint' => 'Email shown on the contact page and in emails.', 'route' => null],
            ['label' => 'Company and legal pages', 'done' => BrandSettings::profile('legal_name') !== '', 'hint' => 'Company name used in the footer and the legal pages.', 'route' => 'admin.legal'],
        ];
        $done = count(array_filter($items, fn ($i) => $i['done']));

        return ['items' => $items, 'done' => $done, 'total' => count($items)];
    }

    public function render()
    {
        return view('livewire.admin.activation-branding', [
            'checklist' => $this->checklist(),
            'instanceId' => InstanceIdentity::id(),
            'edition' => InstanceIdentity::edition(),
            'dbState' => InstanceIdentity::databaseState(),
            'previewName' => trim($this->brand_name) !== '' ? trim($this->brand_name) : BrandSettings::name(),
            'previewWord' => trim($this->brand_word) !== '' ? trim($this->brand_word) : BrandSettings::word(),
        ]);
    }
}
