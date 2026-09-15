<?php

namespace App\Livewire\Admin;

use App\Models\AppBuild;
use App\Services\AppExport\BuildDispatcher;
use App\Support\AppExport;
use App\Support\AppStudio;
use App\Support\Auditor;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Admin → App Builder (App Export prompt §1). One place for Frank to configure
 * the exported Android/iOS app — name, colours, splash, icon, version — upload
 * signing credentials, trigger builds, watch their real status, and toggle where
 * the "Download the app" CTA appears. Honest-state throughout: a compiled build
 * is never presented as "published". Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class AppBuilder extends Component
{
    use WithFileUploads;
    use WithPagination;

    public array $form = [];

    /** placements[key] => ['active' => bool, 'label' => string] */
    public array $placements = [];

    public $splash = null;
    public $icon = null;
    public $keystore = null;
    public $iosCert = null;
    public $iosProvisioningProfile = null;

    /** id => last-seen status, used by pollBuilds() to detect a queued/building -> ready transition. */
    public array $lastBuildStatuses = [];

    /* -------- App Studio (native config surface, NAARA-BUILD-21) ---------- */
    public array $studio = [];

    /** @var array<int, array{pattern:string, action:string}> */
    public array $linkRules = [];

    /** @var array<string, array{label:string, enabled:bool, description:string}> */
    public array $permissions = [];

    public $offlineFile = null;      // "Upload from a file"
    public string $offlineUrl = '';  // "Update from a URL"

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $all = AppExport::all();
        $this->form = collect($all)->except('placements')->all();

        foreach (AppExport::PLACEMENTS as $key => $label) {
            $this->placements[$key] = [
                'active' => (bool) ($all['placements'][$key]['active'] ?? false),
                'label' => (string) ($all['placements'][$key]['label'] ?? ''),
            ];
        }

        $this->loadStudio();
        $this->lastBuildStatuses = AppBuild::query()->pluck('status', 'id')->all();
    }

    private function loadStudio(): void
    {
        $s = AppStudio::all();
        // Raw stored values for the overridable "blank = auto" fields (the view
        // shows the resolved default as a placeholder); resolved values for the
        // set-once package IDs so they're visible and lockable.
        $this->studio = [
            'initial_url' => (string) $s['initial_url'],
            'app_display_name' => (string) $s['app_display_name'],
            'android_package_name' => AppStudio::androidPackage(),
            'ios_bundle_id' => AppStudio::iosBundle(),
            'icon_source' => (string) $s['icon_source'],
            'splash_style' => (string) $s['splash_style'],
            'offline_style' => (string) $s['offline_style'],
            'offline_timeout' => (int) $s['offline_timeout'],
            'offline_html' => (string) $s['offline_html'],
            'pull_to_refresh' => (bool) $s['pull_to_refresh'],
            'refresh_button' => (bool) $s['refresh_button'],
            'deep_link_domain' => (string) $s['deep_link_domain'],
            'disallow_insecure_http' => (bool) $s['disallow_insecure_http'],
            'bridge_restrict_own_domain' => (bool) $s['bridge_restrict_own_domain'],
        ];
        $this->linkRules = AppStudio::linkRules();
        $this->permissions = AppStudio::permissions();
    }

    public function addLinkRule(): void
    {
        $this->linkRules[] = ['pattern' => '', 'action' => 'external'];
    }

    public function removeLinkRule(int $i): void
    {
        unset($this->linkRules[$i]);
        $this->linkRules = array_values($this->linkRules);
    }

    public function moveLinkRule(int $i, string $dir): void
    {
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if (isset($this->linkRules[$i], $this->linkRules[$j])) {
            [$this->linkRules[$i], $this->linkRules[$j]] = [$this->linkRules[$j], $this->linkRules[$i]];
        }
    }

    public function resetOffline(): void
    {
        $this->studio['offline_style'] = 'default';
        $this->studio['offline_html'] = '';
        $this->dispatch('nx-toast', type: 'success', message: 'Offline page reset to the branded default.');
    }

    public function uploadOfflineFile(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['offlineFile' => 'required|file|mimetypes:text/html,text/plain|max:512']);
        $html = file_get_contents($this->offlineFile->getRealPath());
        $this->studio['offline_html'] = (string) $html;
        $this->studio['offline_style'] = 'custom';
        $this->offlineFile = null;
        $this->dispatch('nx-toast', type: 'success', message: 'Offline page loaded from file.');
    }

    public function fetchOfflineUrl(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        // Pentest finding (2026-09-16): this was the one place in the codebase
        // that fetched an admin-supplied URL with no SSRF check at all, despite
        // App\Rules\PublicUrl / App\Support\Security\SsrfGuard existing
        // specifically for this ("any time the server fetches a URL that could
        // be influenced by user input") — an admin could point it at a cloud
        // metadata endpoint or an internal service and have the response body
        // stored + shipped as the app's offline page.
        $this->validate(['offlineUrl' => ['required', 'url', 'max:500', new \App\Rules\PublicUrl]]);
        try {
            $res = Http::timeout(10)->get($this->offlineUrl);
            abort_unless($res->ok(), 422);
            $this->studio['offline_html'] = (string) $res->body();
            $this->studio['offline_style'] = 'custom';
            $this->dispatch('nx-toast', type: 'success', message: 'Offline page fetched from URL.');
        } catch (\Throwable) {
            $this->addError('offlineUrl', 'Could not fetch that URL.');
        }
    }

    public function saveStudio(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $data = $this->validate([
            'studio.initial_url' => 'nullable|url|max:300',
            'studio.app_display_name' => 'nullable|string|max:60',
            'studio.android_package_name' => 'required|string|max:120|regex:/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/',
            'studio.ios_bundle_id' => 'required|string|max:120|regex:/^[A-Za-z][A-Za-z0-9-]*(\.[A-Za-z0-9-]+)+$/',
            'studio.icon_source' => 'required|in:brand,custom',
            'studio.splash_style' => 'required|in:icon,full',
            'studio.offline_style' => 'required|in:default,custom',
            'studio.offline_timeout' => 'required|integer|min:3|max:120',
            'studio.offline_html' => 'nullable|string|max:100000',
            'studio.pull_to_refresh' => 'boolean',
            'studio.refresh_button' => 'boolean',
            'studio.deep_link_domain' => 'nullable|string|max:190',
            'studio.disallow_insecure_http' => 'boolean',
            'studio.bridge_restrict_own_domain' => 'boolean',
            'linkRules.*.pattern' => 'nullable|string|max:190',
            'linkRules.*.action' => 'required|in:'.implode(',', AppStudio::LINK_ACTIONS),
            'permissions.*.enabled' => 'boolean',
            'permissions.*.description' => 'nullable|string|max:300',
        ]);

        $payload = $data['studio'];
        // Keep only rules with a pattern; store the ordered list as-is.
        $payload['link_rules'] = array_values(array_filter(
            $this->linkRules,
            fn ($r) => trim((string) ($r['pattern'] ?? '')) !== '',
        ));
        // Persist only enabled + (trimmed) description per permission.
        $perms = [];
        foreach ($this->permissions as $key => $p) {
            $perms[$key] = [
                'enabled' => (bool) ($p['enabled'] ?? false),
                'description' => trim((string) ($p['description'] ?? '')),
            ];
        }
        $payload['permissions'] = $perms;

        AppStudio::save($payload);
        $this->loadStudio();
        Auditor::log('appstudio.settings_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'App Studio settings saved.');
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $data = $this->validate([
            'form.app_name' => 'required|string|max:60',
            'form.short_name' => 'required|string|max:24',
            'form.theme_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'form.background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'form.version' => 'required|string|regex:/^\d+\.\d+\.\d+$/',
            'form.build_number' => 'required|integer|min:1',
            'form.changelog' => 'nullable|string|max:2000',
            'form.preloader' => 'required|in:'.implode(',', AppExport::PRELOADERS),
            'form.download_enabled' => 'boolean',
            'form.android_store_live' => 'boolean',
            'form.android_store_url' => 'nullable|url|max:300',
            'form.ios_store_live' => 'boolean',
            'form.ios_store_url' => 'nullable|url|max:300',
            'form.ci_provider' => 'required|in:generic,codemagic',
            'form.ci_webhook_url' => 'nullable|url|max:300',
            'form.codemagic_app_id' => 'nullable|string|max:100',
            'form.codemagic_ios_workflow_id' => 'nullable|string|max:100',
            'form.codemagic_branch' => 'nullable|string|max:190',
            'form.github_repo' => 'nullable|regex:/^[\w.-]+\/[\w.-]+$/|max:190',
            'form.github_branch' => 'nullable|string|max:190',
            'form.ios_signing_on_provider' => 'boolean',
            'form.ios_gift_cards_enabled' => 'boolean',
            'form.privacy_policy_url' => 'nullable|url|max:300',
            'form.support_email' => 'nullable|email|max:190',
            'form.support_url' => 'nullable|url|max:300',
            'form.account_deletion_url' => 'nullable|string|max:300',
            'form.category' => 'nullable|string|max:60',
            'form.content_rating' => 'nullable|string|max:60',
            'form.short_description' => 'nullable|string|max:200',
            'form.full_description' => 'nullable|string|max:4000',
            'form.keywords' => 'nullable|string|max:200',
            'form.data_safety' => 'nullable|string|max:2000',
            'form.min_android_target_api' => 'nullable|integer|min:30|max:40',
            'form.permissions_note' => 'nullable|string|max:1000',
            'form.onboarding_enabled' => 'boolean',
            'splash' => 'nullable|image|mimes:webp,png,jpg,jpeg|max:3000',
            'icon' => 'nullable|image|mimes:webp,png,jpg,jpeg|max:2000',
        ]);

        // Persist the full form (incl. onboarding slides array) — AppExport::save
        // keeps only known keys, so unvalidated extras can't sneak in.
        $payload = array_merge($this->form, $data['form']);

        if ($this->splash) {
            $payload['splash_url'] = MediaStorage::storePublic($this->splash, 'app-export');
            $this->splash = null;
        }
        if ($this->icon) {
            $payload['icon_url'] = MediaStorage::storePublic($this->icon, 'app-export');
            $this->icon = null;
        }

        // A store listing can't be "live" without a URL — guard the honest-state rule.
        if (($payload['android_store_live'] ?? false) && empty($payload['android_store_url'])) {
            $this->addError('form.android_store_url', 'Add the Play Store URL before marking it live.');

            return;
        }
        if (($payload['ios_store_live'] ?? false) && empty($payload['ios_store_url'])) {
            $this->addError('form.ios_store_url', 'Add the App Store URL before marking it live.');

            return;
        }

        // Placements — normalise to the known keys only.
        $placements = [];
        foreach (AppExport::PLACEMENTS as $key => $label) {
            $placements[$key] = [
                'active' => (bool) ($this->placements[$key]['active'] ?? false),
                'label' => trim((string) ($this->placements[$key]['label'] ?? '')),
            ];
        }
        $payload['placements'] = $placements;

        AppExport::save($payload);
        $this->form = collect(AppExport::all())->except('placements')->all();
        Auditor::log('appexport.settings_saved', null, null, ['version' => $payload['version']]);
        $this->dispatch('nx-toast', type: 'success', message: 'App settings saved.');
    }

    /** Onboarding slide upload (portrait first-run images). */
    public $slideImage = null;

    public function addSlide(): void
    {
        $slides = array_values((array) ($this->form['onboarding_slides'] ?? []));
        $slides[] = ['image' => '', 'title' => '', 'subtitle' => ''];
        $this->form['onboarding_slides'] = array_slice($slides, 0, 5);
    }

    public function removeSlide(int $index): void
    {
        $slides = array_values((array) ($this->form['onboarding_slides'] ?? []));
        unset($slides[$index]);
        $this->form['onboarding_slides'] = array_values($slides);
    }

    public function uploadSlideImage(int $index): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['slideImage' => 'required|image|mimes:webp,png,jpg,jpeg|max:3000']);
        $url = MediaStorage::storePublic($this->slideImage, 'app-onboarding');
        $this->slideImage = null;

        $slides = array_values((array) ($this->form['onboarding_slides'] ?? []));
        if (isset($slides[$index])) {
            $slides[$index]['image'] = $url;
            $this->form['onboarding_slides'] = $slides;
        }
    }

    public function uploadKeystore(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['keystore' => 'required|file|max:2000']);

        $base64 = base64_encode(file_get_contents($this->keystore->getRealPath()));
        AppExport::storeCredential('android_keystore', $base64, $this->keystore->getClientOriginalName());
        $this->keystore = null;
        Auditor::log('appexport.keystore_uploaded');
        $this->dispatch('nx-toast', type: 'success', message: 'Keystore stored (encrypted). Back it up now — see the warning.');
    }

    public function acknowledgeBackup(): void
    {
        AppExport::save(['keystore_backed_up' => true]);
        $this->form['keystore_backed_up'] = true;
        Auditor::log('appexport.keystore_backup_ack');
    }

    /**
     * §3.5 of the App Export audit — the iOS-credential-storage gap. Mirrors
     * uploadKeystore() exactly: the file is base64'd into the same encrypted
     * `appexport.secure` Setting row, just under its own slot. Whether your CI
     * provider's API can actually consume a pushed cert/profile (vs. requiring
     * dashboard upload, common for App Store Connect API keys specifically) is
     * provider-specific — storing it here at least means the gap is never
     * silent, and it's ready to wire into the trigger payload if it does.
     */
    public function uploadIosCert(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['iosCert' => 'required|file|max:2000']);

        $base64 = base64_encode(file_get_contents($this->iosCert->getRealPath()));
        AppExport::storeCredential('ios_cert', $base64, $this->iosCert->getClientOriginalName());
        $this->iosCert = null;
        Auditor::log('appexport.ios_cert_uploaded');
        $this->dispatch('nx-toast', type: 'success', message: 'iOS distribution certificate stored (encrypted).');
    }

    public function uploadIosProvisioningProfile(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['iosProvisioningProfile' => 'required|file|max:2000']);

        $base64 = base64_encode(file_get_contents($this->iosProvisioningProfile->getRealPath()));
        AppExport::storeCredential('ios_provisioning_profile', $base64, $this->iosProvisioningProfile->getClientOriginalName());
        $this->iosProvisioningProfile = null;
        Auditor::log('appexport.ios_provisioning_profile_uploaded');
        $this->dispatch('nx-toast', type: 'success', message: 'iOS provisioning profile stored (encrypted).');
    }

    public function toggleIosSigningOnProvider(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $value = ! (bool) ($this->form['ios_signing_on_provider'] ?? false);
        AppExport::save(['ios_signing_on_provider' => $value]);
        $this->form['ios_signing_on_provider'] = $value;
        Auditor::log('appexport.ios_signing_on_provider_toggled', null, null, ['value' => $value]);
    }

    public function generateBuild(string $platform, string $artifactType): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        app(BuildDispatcher::class)->create($platform, $artifactType, Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($platform).' build queued.');
    }

    /**
     * Live status (App Export audit §3.3/§3.4) — bound to wire:poll on the
     * build-history card, only while a non-terminal build exists (see
     * `hasActiveBuild` in render()), so this never polls forever. Detects a
     * queued/building -> ready TRANSITION (never "is ready" on every tick,
     * which would silently re-trigger the download on each poll) and fires a
     * one-shot browser event the view uses to auto-download the artifact.
     */
    public function pollBuilds(): void
    {
        $current = AppBuild::query()->pluck('status', 'id');

        foreach ($current as $id => $status) {
            $previous = $this->lastBuildStatuses[$id] ?? null;
            if ($status === AppBuild::STATUS_READY && $previous !== AppBuild::STATUS_READY) {
                $build = AppBuild::find($id);
                if ($build && $build->artifact_url) {
                    $this->dispatch(
                        'appbuild-ready',
                        url: $build->artifact_url,
                        label: strtoupper($build->platform).' '.strtoupper($build->artifact_type).' v'.$build->version,
                    );
                }
            }
        }

        $this->lastBuildStatuses = $current->all();
    }

    public function render()
    {
        // Live offline-page preview: custom HTML if the admin set it, else the
        // branded default — so the phone-frame always shows something real.
        $offlinePreview = trim((string) $this->studio['offline_html']) !== '' && $this->studio['offline_style'] === 'custom'
            ? $this->studio['offline_html']
            : AppStudio::defaultOfflineHtml();

        $builds = AppBuild::latest('id')->paginate(8);

        return view('livewire.admin.app-builder', [
            'placementLabels' => AppExport::PLACEMENTS,
            'preloaders' => AppExport::PRELOADERS,
            'hasKeystore' => AppExport::hasCredential('android_keystore'),
            'keystoreMeta' => AppExport::credentialMeta('android_keystore'),
            'hasIosCert' => AppExport::hasCredential('ios_cert'),
            'iosCertMeta' => AppExport::credentialMeta('ios_cert'),
            'hasIosProvisioningProfile' => AppExport::hasCredential('ios_provisioning_profile'),
            'iosProvisioningProfileMeta' => AppExport::credentialMeta('ios_provisioning_profile'),
            'builds' => $builds,
            'hasActiveBuild' => $builds->getCollection()->contains(fn (AppBuild $b) => ! $b->isTerminal()),
            'androidCiConfigured' => AppExport::androidCiConfigured(),
            'iosCiConfigured' => AppExport::iosCiConfigured(),
            'checklist' => AppExport::publishChecklist(),
            'score' => AppExport::readinessScore(),
            // App Studio (NAARA-BUILD-21)
            'linkActions' => AppStudio::LINK_ACTIONS,
            'offlinePreview' => $offlinePreview,
            'pushStatus' => AppStudio::pushStatus(),
            'sidebarMenu' => AppStudio::sidebarMenu(),
            'canGenerateWithDefaults' => AppStudio::canGenerateWithDefaults(),
        ]);
    }
}
