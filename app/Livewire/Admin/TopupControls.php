<?php

namespace App\Livewire\Admin;

use App\Jobs\Topup\RequeryOrderJob;
use App\Models\Setting;
use App\Models\TopupOrder;
use App\Models\TopupProduct;
use App\Models\TopupProvider;
use App\Models\TopupProviderLog;
use App\Models\TopupRoute;
use App\Services\Topup\ProviderRouter;
use App\Services\Topup\TopupCatalogue;
use App\Services\Topup\TopupOps;
use App\Services\Topup\TopupOrderService;
use App\Services\Topup\TopupProviders;
use App\Models\TopupAutoReload;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\TopupHeroContent;
use App\Support\TopupLogos;
use App\Support\FeatureFlags;
use App\Support\TopupSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

/**
 * Admin > Top-ups: one place for everything about airtime and data. The master switch, suppliers (enable, float, test), routing, pricing,
 * limits, the live catalogue, every order with its timeline (manual requery / refund), supplier call logs and reconciliation.
 * Every change is audited; every limit is a setting, never a literal in code.
 */
#[Layout('components.layouts.admin')]
class TopupControls extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $tab = 'overview';

    /** Rates tab: the country the proof sheet is run for. */
    public string $rateCountry = 'NG';

    /** Live supplier rate sheet: airtime | data. The sheet itself is fetched on demand (it calls every supplier's API). */
    public string $liveType = 'airtime';

    public ?array $liveSheet = null;

    public bool $featureOn = false;

    /** @var array<string, array{enabled: bool, min_float: string}> */
    public array $suppliers = [];

    /** @var array<string, mixed> */
    public array $s = [];

    public string $overrides = '';

    public string $newRouteCountry = '';

    public string $newRouteType = '*';

    public string $newRouteProvider = 'zendit';

    public ?string $saved = null;

    public ?string $problem = null;

    public string $catalogueCountry = '';

    public string $catalogueSearch = '';

    public string $orderStatus = '';

    public string $orderSearch = '';

    public ?int $openOrder = null;

    public string $refundReason = '';

    public string $logProvider = '';

    // hero + banners
    public string $heroTitle = '';

    public string $heroDesc = '';

    public bool $heroEnabled = true;

    public bool $heroAutoplay = true;

    public int $heroInterval = 5;

    /** @var list<array{image: string, title: string, link: string}> */
    public array $slides = [];

    public $newBanners = [];

    // logos
    public $logoFile = [];

    public string $logoSearch = '';

    public function mount(): void
    {
        app(TopupOps::class)->seed();
        $this->load();
    }

    private function load(): void
    {
        $this->featureOn = FeatureFlags::adminEnabled('naara_topup');
        foreach (TopupProvider::all() as $p) {
            $this->suppliers[$p->code] = ['enabled' => (bool) $p->enabled, 'min_float' => (string) $p->min_float];
        }
        foreach (array_keys(TopupSettings::DEFAULTS) as $k) {
            $v = TopupSettings::get($k);
            $this->s[$k] = is_bool(TopupSettings::DEFAULTS[$k]) ? filter_var($v, FILTER_VALIDATE_BOOLEAN) : (string) $v;
        }
        $h = TopupHeroContent::current();
        $this->heroTitle = $h['title'];
        $this->heroDesc = $h['description'];
        $this->heroEnabled = $h['enabled'];
        $this->heroAutoplay = $h['autoplay'];
        $this->heroInterval = $h['interval'];
        $this->slides = $h['slides'];
        $this->overrides = collect((array) Setting::getValue('topup.markup_overrides', []))->map(fn ($v, $k) => "$k=$v")->implode("\n");
    }

    public function setTab(string $t): void
    {
        $this->tab = $t;
        $this->saved = $this->problem = null;
        $this->resetPage();
    }

    private function ok(string $msg): void
    {
        $this->saved = $msg;
        $this->problem = null;
    }

    public function toggleFeature(): void
    {
        FeatureFlags::setEnabled('naara_topup', ! FeatureFlags::adminEnabled('naara_topup'));
        $this->featureOn = FeatureFlags::adminEnabled('naara_topup');
        Auditor::log('topup.feature_toggled', null, null, ['on' => $this->featureOn]);
        $this->ok($this->featureOn ? 'Top-ups are switched on. Customers see them once a supplier below is enabled.' : 'Top-ups are switched off.');
    }

    public function saveSupplier(string $code): void
    {
        abort_unless(isset(TopupProviders::MAP[$code]), 404);
        $d = $this->suppliers[$code] ?? null;
        if (! $d) {
            return;
        }
        if ($d['enabled'] && ! TopupProviders::get($code)->configured()) {
            $this->suppliers[$code]['enabled'] = false;
            $this->problem = TopupProviders::META[$code]['name'].' has no keys saved yet. Add them under API keys first.';

            return;
        }
        TopupProvider::where('code', $code)->update(['enabled' => (bool) $d['enabled'], 'min_float' => max(0, (float) $d['min_float'])]);
        Auditor::log('topup.supplier_saved', null, null, ['code' => $code, 'enabled' => (bool) $d['enabled']]);
        $this->ok(TopupProviders::META[$code]['name'].' saved.');
    }

    public function testSupplier(string $code): void
    {
        abort_unless(isset(TopupProviders::MAP[$code]), 404);
        $p = TopupProviders::get($code);
        if (! $p->configured()) {
            $this->problem = $p->name().' has no keys saved yet.';

            return;
        }
        try {
            $b = $p->balance();
            TopupProvider::where('code', $code)->update(['last_balance' => $b['amount'], 'currency' => $b['currency'], 'last_balance_at' => now(), 'last_error' => null]);
            $this->ok($p->name().' answered. Float: '.$b['currency'].' '.number_format($b['amount'], 2));
        } catch (Throwable $e) {
            TopupProvider::where('code', $code)->update(['last_error' => substr($e->getMessage(), 0, 250)]);
            $this->problem = str_contains(strtolower($e->getMessage()), 'whitelist')
                ? $p->name().' refused this server: its IP is not on the supplier whitelist. Add this server\'s outbound IP '.(\App\Support\ServerEgressIp::get(true) ?: '(could not be detected, run "curl https://ifconfig.me" on the server)').' in the supplier dashboard (Paga: Developer Tools > IP Whitelist, Live environment), then test again.'
                : $p->name().' did not answer: '.substr($e->getMessage(), 0, 160);
        }
    }

    public function pollFloat(): void
    {
        app(TopupOps::class)->pollFloat();
        $this->ok('Float checked for every supplier with keys.');
    }

    public function addRoute(): void
    {
        $this->validate([
            'newRouteCountry' => ['nullable', 'regex:/^(\*|[A-Za-z]{2})?$/'], 'newRouteType' => ['required', 'in:*,airtime,data'],
            'newRouteProvider' => ['required', 'in:'.implode(',', TopupProviders::codes())],
        ]);
        $c = strtoupper(trim($this->newRouteCountry));
        $max = TopupRoute::where('country_iso', $c ?: null)->where('product_type', $this->newRouteType)->max('priority') ?? 0;
        TopupRoute::create(['country_iso' => $c === '' ? '*' : $c, 'product_type' => $this->newRouteType, 'provider_code' => $this->newRouteProvider, 'priority' => $max + 1, 'enabled' => true]);
        Auditor::log('topup.route_added', null, null, ['country' => $c ?: '*', 'provider' => $this->newRouteProvider]);
        $this->newRouteCountry = '';
        $this->ok('Route added.');
    }

    public function toggleRoute(int $id): void
    {
        $r = TopupRoute::findOrFail($id);
        $r->update(['enabled' => ! $r->enabled]);
    }

    public function moveRoute(int $id, int $dir): void
    {
        $r = TopupRoute::findOrFail($id);
        $r->update(['priority' => max(1, $r->priority + $dir)]);
    }

    public function deleteRoute(int $id): void
    {
        TopupRoute::whereKey($id)->delete();
        $this->ok('Route removed.');
    }

    public function seedDefaults(): void
    {
        app(TopupOps::class)->seed();
        $this->ok('Suppliers and default routes are in place.');
    }

    public function saveSettings(): void
    {
        $this->validate([
            's.markup_pct' => ['required', 'numeric', 'min:0', 'max:100'], 's.min_profit_usd' => ['required', 'numeric', 'min:0', 'max:50'],
            's.fx_buffer_pct' => ['required', 'numeric', 'min:0', 'max:20'], 's.quote_ttl_seconds' => ['required', 'integer', 'min:60', 'max:1800'],
            's.offer_cache_seconds' => ['required', 'integer', 'min:5', 'max:3600'], 's.min_order_usd' => ['required', 'numeric', 'min:0.1'],
            's.max_order_usd' => ['required', 'numeric', 'min:1'], 's.daily_cap_usd' => ['required', 'numeric', 'min:1'], 's.weekly_cap_usd' => ['required', 'numeric', 'min:1'],
            's.new_account_hours' => ['required', 'integer', 'min:0', 'max:720'], 's.new_account_daily_cap_usd' => ['required', 'numeric', 'min:1'],
            's.max_numbers_per_day' => ['required', 'integer', 'min:1', 'max:100'], 's.max_orders_per_hour' => ['required', 'integer', 'min:1', 'max:200'],
            's.escalate_after_minutes' => ['required', 'integer', 'min:5', 'max:1440'], 's.default_country' => ['required', 'alpha', 'size:2'],
            's.credit_profit_share_pct' => ['required', 'numeric', 'min:0', 'max:50'], 's.credit_max_discount_usd' => ['required', 'numeric', 'min:0', 'max:5'], 's.credit_min_profit_usd' => ['required', 'numeric', 'min:0', 'max:50'],
            's.catalogue_drop_alert_pct' => ['required', 'integer', 'min:5', 'max:90'], 's.max_autoreloads_per_user' => ['required', 'integer', 'min:1', 'max:50'], 's.log_retention_days' => ['required', 'integer', 'min:7', 'max:730'],
        ]);
        foreach ($this->s as $k => $v) {
            if (array_key_exists($k, TopupSettings::DEFAULTS)) {
                TopupSettings::set($k, is_bool(TopupSettings::DEFAULTS[$k]) ? (bool) $v : (($k === 'default_country') ? strtoupper((string) $v) : (is_numeric($v) ? $v + 0 : $v)));
            }
        }
        $ov = [];
        foreach (preg_split('/\R/', $this->overrides) ?: [] as $line) {
            if (preg_match('/^\s*([A-Za-z]{2}(?::[^=]+)?)\s*=\s*([\d.]+)\s*$/', $line, $m)) {
                $ov[strtolower(strlen($m[1]) === 2 ? strtoupper($m[1]) : strtoupper(substr($m[1], 0, 2)).':'.strtolower(substr($m[1], 3)))] = (float) $m[2];
            }
        }
        // Country keys are stored upper-case; operator keys are "CC:operator" lower-cased after the colon.
        $norm = [];
        foreach ($ov as $k => $v) {
            $norm[strlen($k) === 2 ? strtoupper($k) : strtolower($k)] = $v;
        }
        Setting::setValue('topup.markup_overrides', $norm, 'topup');
        Auditor::log('topup.settings_saved', null, null, ['markup' => $this->s['markup_pct']]);
        $this->load();
        $this->ok('Saved. New quotes use these numbers straight away.');
    }

    public function syncNow(): void
    {
        try {
            $c = app(TopupCatalogue::class)->syncAll();
            $this->ok('Catalogue refreshed: '.(collect($c)->map(fn ($n, $k) => "$k $n")->implode(', ') ?: 'nothing to refresh (enable a supplier first)').'.');
        } catch (Throwable $e) {
            $this->problem = 'Sync failed: '.substr($e->getMessage(), 0, 160);
        }
    }

    public function refreshCountry(): void
    {
        $iso = strtoupper(trim($this->catalogueCountry));
        if (strlen($iso) !== 2) {
            $this->problem = 'Enter a 2-letter country code.';

            return;
        }
        $answered = app(TopupCatalogue::class)->refresh($iso, force: true);
        $this->ok($answered ? "$iso refreshed live from ".implode(', ', $answered).'.' : "$iso: no supplier answered (check routes and keys).");
    }

    public function fetchLive(bool $force = true): void
    {
        $country = strtoupper($this->rateCountry);
        $faces = $this->liveType === 'data' ? [] : ($country === 'NG' ? [500, 1000, 2000, 5000] : [5, 10, 20, 50]);
        $this->liveSheet = app(\App\Services\Topup\TopupLiveRates::class)->run($country, $this->liveType, $faces, $force);
    }

    public function toggleProduct(int $id): void
    {
        $p = TopupProduct::findOrFail($id);
        $p->update(['active' => ! $p->active]);
    }

    public function open(int $id): void
    {
        $this->openOrder = $this->openOrder === $id ? null : $id;
        $this->refundReason = '';
    }

    public function requery(int $id): void
    {
        $o = TopupOrder::findOrFail($id);
        if ($o->isTerminal()) {
            $this->problem = 'That order is already closed.';

            return;
        }
        RequeryOrderJob::dispatchSync($o->id, 'admin');
        Auditor::log('topup.admin_requery', TopupOrder::class, $o->id);
        $this->ok('Asked the supplier. Status is now: '.$o->fresh()->status.'.');
    }

    public function refund(int $id): void
    {
        $o = TopupOrder::findOrFail($id);
        if (trim($this->refundReason) === '') {
            $this->problem = 'Write a reason for the refund first.';

            return;
        }
        try {
            app(TopupOrderService::class)->adminRefund($o, trim($this->refundReason));
            $this->ok('Refunded $'.number_format((float) $o->price_usd, 2).' to the customer wallet.');
        } catch (Throwable $e) {
            $this->problem = 'Could not refund: '.substr($e->getMessage(), 0, 140);
        }
    }

    public function saveHero(): void
    {
        $this->validate([
            'heroTitle' => ['required', 'string', 'max:70'], 'heroDesc' => ['required', 'string', 'max:200'], 'heroInterval' => ['required', 'integer', 'min:2', 'max:30'],
            'slides.*.title' => ['nullable', 'string', 'max:80'], 'slides.*.link' => ['nullable', 'string', 'max:300', 'regex:/^(\/|https:\/\/)?[^\s]*$/'],
        ]);
        TopupHeroContent::save([
            'title' => trim($this->heroTitle), 'description' => trim($this->heroDesc), 'enabled' => $this->heroEnabled, 'autoplay' => $this->heroAutoplay, 'interval' => $this->heroInterval,
            'slides' => array_values(array_slice(array_map(fn ($x) => ['image' => (string) $x['image'], 'title' => trim((string) ($x['title'] ?? '')), 'link' => trim((string) ($x['link'] ?? ''))], $this->slides), 0, TopupHeroContent::MAX_SLIDES)),
        ]);
        Auditor::log('topup.hero_saved');
        $this->ok('Hero and banners saved. Live now.');
    }

    public function updatedNewBanners(): void
    {
        $this->validate(['newBanners.*' => ['image', 'mimes:webp,jpg,jpeg,png', 'max:2048']], ['newBanners.*.max' => 'Keep each banner under 2 MB.', 'newBanners.*.mimes' => 'Banners must be WebP, JPG or PNG.']);
        foreach ((array) $this->newBanners as $f) {
            if (count($this->slides) >= TopupHeroContent::MAX_SLIDES) {
                $this->problem = 'Up to '.TopupHeroContent::MAX_SLIDES.' banners.';
                break;
            }
            $this->slides[] = ['image' => MediaStorage::storePublic($f, 'topup-banners'), 'title' => '', 'link' => ''];
        }
        $this->newBanners = [];
        $this->ok('Added. Write a caption or link if you like, then Save.');
    }

    public function removeSlide(int $i): void
    {
        unset($this->slides[$i]);
        $this->slides = array_values($this->slides);
    }

    public function moveSlide(int $i, int $dir): void
    {
        $j = $i + $dir;
        if (isset($this->slides[$i], $this->slides[$j])) {
            [$this->slides[$i], $this->slides[$j]] = [$this->slides[$j], $this->slides[$i]];
        }
    }

    public function resetBanners(): void
    {
        $this->slides = TopupHeroContent::DEFAULTS['slides'];
        $this->ok('Back to the shipped banners. Save to apply.');
    }

    public function saveLogo(string $key): void
    {
        $this->validate(["logoFile.$key" => ['required', 'image', 'mimes:webp,png,jpg,jpeg,svg', 'max:512']], ["logoFile.$key.max" => 'Keep the logo under 512 KB.']);
        $o = (array) Setting::getValue('topup.logo_overrides', []);
        $o[$key] = MediaStorage::storePublic($this->logoFile[$key], 'topup-logos');
        Setting::setValue('topup.logo_overrides', $o, 'topup');
        unset($this->logoFile[$key]);
        TopupLogos::forgetCache();
        Auditor::log('topup.logo_uploaded', null, null, ['key' => $key]);
        $this->ok('Logo saved.');
    }

    public function clearLogo(string $key): void
    {
        $o = (array) Setting::getValue('topup.logo_overrides', []);
        unset($o[$key]);
        Setting::setValue('topup.logo_overrides', $o, 'topup');
        $this->ok('Uploaded logo removed.');
    }

    public function reconcileNow(): void
    {
        $r = app(TopupOps::class)->reconcile();
        $this->ok("Reconciled {$r['checked']} orders: ".count($r['mismatches']).' mismatch(es).');
    }

    public function render()
    {
        $router = app(ProviderRouter::class);
        $providers = TopupProvider::orderBy('code')->get()->keyBy('code');
        $d1 = now()->subDay();
        $kpi = [
            'orders24' => TopupOrder::where('created_at', '>=', $d1)->count(),
            'delivered24' => TopupOrder::where('created_at', '>=', $d1)->where('status', TopupOrder::DELIVERED)->count(),
            'pending' => TopupOrder::whereIn('status', [TopupOrder::PENDING, TopupOrder::SUBMITTING, TopupOrder::FUNDS_HELD])->count(),
            'escalated' => TopupOrder::where('escalated', true)->whereNotIn('status', TopupOrder::TERMINAL)->count(),
            'refunded24' => TopupOrder::where('created_at', '>=', $d1)->whereIn('status', [TopupOrder::REFUNDED, TopupOrder::FAILED])->count(),
            'products' => TopupProduct::where('active', true)->count(),
            'autos' => TopupAutoReload::where('active', true)->count(),
        ];
        $data = ['providers' => $providers, 'kpi' => $kpi, 'live' => \App\Support\ComingSoon::isLive('naara_topup'), 'router' => $router];

        if ($this->tab === 'overview' || $this->tab === 'suppliers') {
            $data['margin'] = app(TopupOps::class)->margin(30);
            $data['reconcile'] = Setting::getValue('topup.last_reconcile');
            $data['configured'] = collect(TopupProviders::all())->map->configured()->all();
        }
        if ($this->tab === 'rates') {
            $data['liveLast'] = Setting::getValue('topup.live_rates_last');
            $data['rateCountries'] = TopupProduct::where('active', true)->where('type', 'airtime')->distinct()->orderBy('country_iso')->pluck('country_iso')->all();
            $data['rateCheck'] = app(\App\Services\Topup\TopupRateCheck::class)->airtime($this->rateCountry, $this->rateCountry === 'NG' ? [500, 1000, 2000, 5000] : [5, 10, 20, 50]);
        }
        if ($this->tab === 'routes') {
            $data['routes'] = TopupRoute::orderByRaw("CASE WHEN country_iso IN ('*','') OR country_iso IS NULL THEN 1 ELSE 0 END")->orderBy('country_iso')->orderBy('product_type')->orderBy('priority')->get();
        }
        if ($this->tab === 'catalogue') {
            $q = TopupProduct::query()->when($this->catalogueCountry !== '', fn ($q) => $q->where('country_iso', strtoupper($this->catalogueCountry)))
                ->when($this->catalogueSearch !== '', fn ($q) => $q->where(fn ($w) => $w->where('operator_name', 'like', '%'.$this->catalogueSearch.'%')->orWhere('name', 'like', '%'.$this->catalogueSearch.'%')));
            $data['products'] = $q->orderBy('country_iso')->orderBy('operator_name')->orderBy('fixed_amount')->paginate(25);
            $data['byCountry'] = TopupProduct::where('active', true)->selectRaw('country_iso, count(*) c')->groupBy('country_iso')->orderByDesc('c')->limit(12)->pluck('c', 'country_iso');
            $data['makeVisible'] = true;
        }
        if ($this->tab === 'orders') {
            $q = TopupOrder::with('user:id,name,email')->when($this->orderStatus !== '', fn ($q) => $q->where('status', $this->orderStatus))
                ->when($this->orderSearch !== '', fn ($q) => $q->where(fn ($w) => $w->where('phone_e164', 'like', '%'.$this->orderSearch.'%')->orWhere('uuid', 'like', $this->orderSearch.'%')->orWhere('reference', 'like', $this->orderSearch.'%')));
            $data['orders'] = $q->latest('id')->paginate(20);
            $data['detail'] = $this->openOrder ? TopupOrder::with('events', 'user:id,name,email')->find($this->openOrder) : null;
        }
        if ($this->tab === 'logos') {
            $ops = TopupProduct::where('active', true)->select('operator_name')->distinct()->pluck('operator_name')->map(fn ($n) => ['key' => TopupLogos::key($n), 'name' => preg_replace('/\s*\(.*?\)\s*/', '', $n)])->unique('key')->sortBy('name')->values();
            $ov = (array) Setting::getValue('topup.logo_overrides', []);
            $shipped = TopupLogos::index();
            $ops = $ops->map(fn ($o) => $o + ['url' => TopupLogos::url($o['name']), 'custom' => isset($ov[$o['key']])]);
            if ($this->logoSearch !== '') {
                $ops = $ops->filter(fn ($o) => str_contains(strtolower($o['name']), strtolower($this->logoSearch)))->values();
            }
            $data['logoOps'] = $ops;
            $data['logoMissing'] = $ops->filter(fn ($o) => ! $o['url'])->count();
        }
        if ($this->tab === 'logs') {
            $data['logs'] = TopupProviderLog::when($this->logProvider !== '', fn ($q) => $q->where('provider_code', $this->logProvider))->latest('id')->paginate(25);
        }

        return view('livewire.admin.topup-controls', $data);
    }
}
