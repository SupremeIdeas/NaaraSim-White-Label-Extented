<?php

namespace App\Livewire;

use App\Models\TopupAutoReload;
use App\Models\TopupBeneficiary;
use App\Models\TopupProduct;
use App\Models\TopupQuote;
use App\Services\Topup\NumberResolver;
use App\Services\Topup\ProviderRouter;
use App\Services\Topup\TopupAutoReloadService;
use App\Services\Topup\QuoteService;
use App\Services\Topup\TopupCatalogue;
use App\Services\Topup\TopupException;
use App\Services\Topup\TopupOrderService;
use App\Services\Topup\TopupProviders;
use App\Support\ComingSoon;
use App\Support\CountryNames;
use App\Support\FeatureEntitlements;
use App\Support\FeatureLocks;
use App\Support\TopupSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Throwable;

/**
 * The Top-Up section: country -> number -> confirm the operator -> pick airtime or a data bundle -> locked price -> pay from the
 * wallet. Offers come LIVE from the routed supplier(s) (App\Services\Topup\TopupCatalogue). Provider identity and our cost never reach
 * this view: a product is shown with what the recipient gets and what the customer pays.
 */
#[Layout('components.layouts.customer')]
class Topup extends Component
{
    #[Url]
    public string $country = '';

    public string $phone = '';

    public string $e164 = '';

    public ?string $phoneError = null;

    #[Url]
    public string $type = 'airtime';

    public ?string $operator = null;

    public ?string $detected = null;

    public string $detectedSource = 'none';

    public ?string $carrier = null;

    public ?int $productId = null;

    public string $amount = '';

    public ?int $quoteId = null;

    public ?string $error = null;

    public string $idem = '';

    public string $search = '';

    public bool $showAll = false;

    /** Data bundle window filter: all | daily | weekly | monthly (like the daily / weekly / monthly tabs people know from bank apps). */
    public string $window = 'all';

    /** Use credit for the small data discount when the admin has it on (see TopupCredit). */
    public bool $useCredits = true;

    public bool $saveBeneficiary = false;

    public string $beneficiaryName = '';

    public bool $autoOn = false;

    public string $freq = 'monthly';

    public int $weekday = 1;

    public int $monthday = 1;

    public int $hour = 9;

    public function booted(): void
    {
        ComingSoon::abortIfOff('naara_topup');
        abort_if(FeatureEntitlements::locked(FeatureLocks::F_TOPUP), 404);
    }

    public function mount(): void
    {
        $this->idem = (string) Str::uuid();
        if ($this->country === '') {
            $cc = strtoupper((string) (Auth::user()?->country ?? ''));
            $this->country = strlen($cc) === 2 ? $cc : strtoupper((string) TopupSettings::get('default_country'));
        }
        $this->country = strtoupper($this->country);
        if ($ben = (int) request()->query('ben')) {
            try {
                $this->useBeneficiary($ben);
            } catch (\Throwable) {
            }
        }
    }

    public function updatedCountry(): void
    {
        $this->country = strtoupper($this->country);
        $this->resetFrom('phone');
    }

    public function updatedPhone(): void
    {
        $this->resetFrom('operator');
        $this->validatePhone();
    }

    public function setType(string $t): void
    {
        $this->type = $t === 'data' ? 'data' : 'airtime';
        $this->updatedType();
    }

    public function updatedType(): void
    {
        $this->showAll = false;
        $this->productId = null;
        $this->quoteId = null;
        $this->amount = '';
    }

    private function resetFrom(string $what): void
    {
        $this->error = null;
        $this->quoteId = null;
        $this->productId = null;
        $this->amount = '';
        $this->idem = (string) Str::uuid();
        if ($what === 'phone') {
            $this->phone = '';
            $this->e164 = '';
            $this->phoneError = null;
        }
        $this->operator = null;
        $this->detected = null;
    }

    /** Resolve whatever was typed: valid E.164, the country (read from the number itself when it starts with +), the network and how sure we are. */
    public function validatePhone(): void
    {
        $this->phoneError = null;
        $this->e164 = '';
        $this->detected = null;
        $this->carrier = null;
        $this->detectedSource = 'none';
        $r = app(NumberResolver::class)->resolve($this->phone, $this->country ?: null, Auth::id());
        if ($r['country'] && $r['valid'] && $r['country'] !== $this->country) {
            $this->country = $r['country'];                      // "+234…" selects Nigeria by itself
        }
        if (! $r['valid']) {
            $this->phoneError = $r['error'];

            return;
        }
        $this->e164 = $r['e164'];
        $this->carrier = $r['carrier'];
        $this->detectedSource = $r['source'];
        if ($r['operator']) {
            $this->detected = $r['operator'];
            $this->operator = $r['operator'];
            $this->productId = null;
            $this->quoteId = null;
        }
        if ($this->beneficiaryName === '') {
            $this->beneficiaryName = TopupBeneficiary::where('user_id', Auth::id())->where('phone_e164', $this->e164)->value('name') ?? '';
        }
    }

    public function useBeneficiary(int $id): void
    {
        $b = TopupBeneficiary::where('user_id', Auth::id())->findOrFail($id);
        $this->resetFrom('phone');
        $this->country = $b->country_iso;
        $this->phone = $b->phone_e164;
        $this->beneficiaryName = $b->name;
        $this->validatePhone();
        if ($b->operator_name && ! $this->operator) {
            $this->operator = $b->operator_name;
        }
    }

    public function setWindow(string $w): void
    {
        $this->window = in_array($w, ['all', 'daily', 'weekly', 'monthly'], true) ? $w : 'all';
        $this->showAll = false;
    }

    public function toggleAll(): void
    {
        $this->showAll = ! $this->showAll;
    }

    public function pickOperator(string $name): void
    {
        $this->showAll = false;
        $this->operator = $name;
        $this->productId = null;
        $this->quoteId = null;
        $this->amount = '';
        $this->error = null;
        $this->window = 'all';
    }

    public function pickProduct(int $id): void
    {
        $this->error = null;
        $this->quoteId = null;
        $p = TopupProduct::where('active', true)->find($id);
        if (! $p) {
            return;
        }
        $this->productId = $p->id;
        $this->amount = $p->is_fixed ? '' : (string) ($this->amount !== '' ? $this->amount : '');
        if ($p->is_fixed) {
            $this->makeQuote();
        }
    }

    // ── One-tap offers: the sheet has its own state so it never disturbs the widget behind it ─────────────
    /** The country whose offers the strip shows (its own choice; it starts from the customer's country). */
    public string $offerCountry = '';

    public ?int $offerId = null;

    public string $offerPhone = '';

    public string $offerE164 = '';

    public ?string $offerError = null;

    public ?int $offerQuoteId = null;

    public string $offerIdem = '';

    public function setOfferCountry(string $iso): void
    {
        $iso = strtoupper(trim($iso));
        $this->offerCountry = preg_match('/^[A-Z]{2}$/', $iso) ? $iso : '';
    }

    /** Tap Claim: open the buy sheet for that bundle, number prefilled from the last top-up in that country. */
    public function openOffer(int $id): void
    {
        $p = TopupProduct::where('active', true)->where('type', 'data')->find($id);
        if (! $p) {
            return;
        }
        $this->offerId = $p->id;
        $this->offerError = null;
        $this->offerQuoteId = null;
        $this->offerE164 = '';
        $this->offerIdem = (string) Str::uuid();
        $last = \App\Models\TopupOrder::where('user_id', Auth::id())->where('country_iso', $p->country_iso)->latest('id')->value('phone_e164');
        $this->offerPhone = (string) ($last ?? '');
        if ($this->offerPhone !== '') {
            $this->updatedOfferPhone();
        }
    }

    public function closeOffer(): void
    {
        $this->offerId = null;
        $this->offerPhone = $this->offerE164 = '';
        $this->offerError = null;
        $this->offerQuoteId = null;
    }

    /** Validate the number against the bundle's country and network, then price it (same engine, same quote, same margin floor as the widget). */
    public function updatedOfferPhone(): void
    {
        $this->offerError = null;
        $this->offerQuoteId = null;
        $this->offerE164 = '';
        $p = $this->offerId ? TopupProduct::where('active', true)->find($this->offerId) : null;
        if (! $p || trim($this->offerPhone) === '') {
            return;
        }
        $r = app(NumberResolver::class)->resolve($this->offerPhone, $p->country_iso, Auth::id());
        if (! $r['valid']) {
            $this->offerError = $r['error'] ?: 'Check the phone number.';

            return;
        }
        if ($r['country'] && strtoupper($r['country']) !== strtoupper($p->country_iso)) {
            $this->offerError = 'This offer is for numbers in '.\App\Support\CountryNames::name($p->country_iso).'.';

            return;
        }
        if ($r['operator'] && ! $this->sameNetwork($r['operator'], $p->operator_name)) {
            $this->offerError = 'That number is on '.$r['operator'].', and this bundle is for '.$p->operator_name.'.';

            return;
        }
        try {
            $q = app(QuoteService::class)->create(Auth::user(), $p, $r['e164'], (float) $p->fixed_amount, true, offer: true);
            $this->offerE164 = $r['e164'];
            $this->offerQuoteId = $q->id;
        } catch (TopupException $e) {
            $this->offerError = $e->getMessage();
        } catch (Throwable) {
            $this->offerError = 'We could not price that right now. Please try again.';
        }
    }

    private function sameNetwork(string $a, string $b): bool
    {
        $n = fn (string $x) => preg_replace('/[^a-z0-9]/', '', strtolower(preg_replace('/\s*\(.*?\)\s*/', '', $x) ?? $x));
        [$a, $b] = [$n($a), $n($b)];

        return $a === $b || str_contains($a, $b) || str_contains($b, $a);
    }

    /** Pay the offer from the wallet: the same order service and idempotency as the widget, so a double tap can never charge twice. */
    public function payOffer()
    {
        $this->offerError = null;
        try {
            $quote = TopupQuote::where('user_id', Auth::id())->findOrFail($this->offerQuoteId);
            if ($quote->expired()) {
                $this->updatedOfferPhone();
                $this->offerError = 'The price timed out, so we refreshed it. Please confirm again.';

                return null;
            }
            $order = app(TopupOrderService::class)->place(Auth::user(), $quote, $this->offerIdem);

            return $this->redirectRoute('topup.order', ['uuid' => $order->uuid], navigate: true);
        } catch (TopupException $e) {
            $this->offerError = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $this->offerError = 'Something went wrong. You have not been charged twice: check My top-ups before trying again.';
        }

        return null;
    }

    public function updatedUseCredits(): void
    {
        if ($this->quoteId) {
            $this->makeQuote();
        }
    }

    public function quickAmount(string $a): void
    {
        $this->amount = $a;
        $this->makeQuote();
    }

    public function makeQuote(): void
    {
        $this->error = null;
        $this->quoteId = null;
        try {
            if ($this->e164 === '') {
                throw new TopupException($this->phoneError ?: 'Enter the phone number first.');
            }
            $p = TopupProduct::where('active', true)->findOrFail($this->productId);
            $q = app(QuoteService::class)->create(Auth::user(), $p, $this->e164, (float) str_replace(',', '', $this->amount ?: '0'), $this->useCredits);
            $this->quoteId = $q->id;
        } catch (TopupException $e) {
            $this->error = $e->getMessage();
        } catch (Throwable) {
            $this->error = 'We could not price that right now. Please try again.';
        }
    }

    public function pay()
    {
        $this->error = null;
        try {
            $quote = TopupQuote::where('user_id', Auth::id())->findOrFail($this->quoteId);
            if ($quote->expired()) {
                $this->makeQuote();
                $this->error = 'The price timed out, so we refreshed it. Please confirm again.';

                return null;
            }
            $order = app(TopupOrderService::class)->place(Auth::user(), $quote, $this->idem);
            $this->rememberAfterPay($quote, $order);

            return $this->redirectRoute('topup.order', ['uuid' => $order->uuid], navigate: true);
        } catch (TopupException $e) {
            $this->error = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $this->error = 'Something went wrong. You have not been charged twice: check My top-ups before trying again.';
        }

        return null;
    }

    /** After the order is placed: save the beneficiary and/or the auto-reload the customer asked for. Never blocks or fails the payment. */
    private function rememberAfterPay(TopupQuote $quote, \App\Models\TopupOrder $order): void
    {
        try {
            if (! $this->saveBeneficiary && ! $this->autoOn) {
                return;
            }
            $p = $quote->product;
            $b = TopupBeneficiary::updateOrCreate(['user_id' => Auth::id(), 'phone_e164' => $quote->phone_e164], [
                'name' => trim($this->beneficiaryName) !== '' ? mb_substr(trim($this->beneficiaryName), 0, 60) : $quote->phone_e164,
                'country_iso' => $p->country_iso, 'operator_name' => $p->operator_name, 'last_used_at' => now(),
            ]);
            if ($this->autoOn && TopupSettings::bool('autoreload_enabled')
                && TopupAutoReload::where('user_id', Auth::id())->where('active', true)->count() < TopupAutoReloadService::limit()) {
                $a = new TopupAutoReload([
                    'user_id' => Auth::id(), 'beneficiary_id' => $b->id, 'country_iso' => $p->country_iso, 'operator_name' => $p->operator_name,
                    'product_type' => $p->type === 'data' ? 'data' : 'airtime', 'send_amount' => $quote->send_amount, 'send_currency' => $quote->send_currency,
                    'product_name' => $p->type === 'data' ? $p->name : null, 'frequency' => in_array($this->freq, ['daily', 'weekly', 'monthly'], true) ? $this->freq : 'monthly',
                    'run_hour' => max(0, min(23, $this->hour)), 'run_weekday' => max(0, min(6, $this->weekday)), 'run_monthday' => max(1, min(28, $this->monthday)),
                    'timezone' => (string) (Auth::user()->timezone ?? config('app.timezone')), 'active' => true,
                ]);
                $a->next_run_at = $a->computeNext();
                $a->last_order_id = $order->id;
                $a->save();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function render()
    {
        $router = app(ProviderRouter::class);
        $cat = app(TopupCatalogue::class);

        $countries = Cache::remember('topup.country.list', 120, fn () => $cat->countries()->mapWithKeys(fn ($c) => [$c => CountryNames::name($c)])->sort()->all());
        $sellable = $this->country !== '' && $router->sellable($this->country);

        $operators = collect();
        $products = collect();
        $stale = false;
        if ($sellable) {
            try {
                $operators = $cat->operators($this->country);
                $products = $this->operator ? $cat->products($this->country, $this->type, $this->operator) : collect();
            } catch (Throwable) {
                $stale = true;
            }
        }
        if ($this->search !== '') {
            $s = strtolower($this->search);
            $products = $products->filter(fn ($p) => str_contains(strtolower($p->name.' '.$p->validity_text), $s))->values();
        }

        // Window counts (before the filter) so each tab says how many bundles it holds.
        $windowOf = fn ($p) => ($d = QuoteService::validityDays($p->validity_text)) === 0 ? 'monthly' : ($d <= 2 ? 'daily' : ($d <= 14 ? 'weekly' : 'monthly'));
        $windowCounts = ['all' => $products->count(), 'daily' => 0, 'weekly' => 0, 'monthly' => 0];
        if ($this->type === 'data') {
            foreach ($products as $p) {
                $windowCounts[$windowOf($p)]++;
            }
            if ($this->window !== 'all') {
                $products = $products->filter(fn ($p) => $windowOf($p) === $this->window)->values();
            }
        }

        // Flexible-amount first, then fixed amounts / bundles smallest to largest.
        $products = $products->sortBy(fn ($p) => [$p->is_fixed ? 1 : 0, (float) ($p->fixed_amount ?? 0)])->values();
        $total = $products->count();
        $cap = 12;
        if (! $this->showAll && $this->search === '' && $total > $cap) {
            $keep = $products->take($cap);
            if ($this->productId && ! $keep->contains('id', $this->productId)) {
                $keep = $keep->push($products->firstWhere('id', $this->productId))->filter();
            }
            $products = $keep->values();
        }

        $beneficiaries = TopupBeneficiary::where('user_id', Auth::id())->orderByDesc('last_used_at')->orderBy('name')->limit(30)->get();
        $autoCount = TopupAutoReload::where('user_id', Auth::id())->where('active', true)->count();
        $airtimeCount = $this->operator ? $cat->products($this->country, 'airtime', $this->operator)->count() : 0;
        $popular = array_values(array_intersect(['NG', 'GH', 'KE', 'ZA', 'TZ', 'UG', 'CM', 'SN', 'CI', 'RW', 'ZM', 'US', 'GB', 'IN'], array_keys($countries)));

        $selected = $this->productId ? TopupProduct::find($this->productId) : null;
        $quote = $this->quoteId ? TopupQuote::with('product')->where('user_id', Auth::id())->find($this->quoteId) : null;
        $svc = app(QuoteService::class);
        // Price every visible card through the same engine as the quote, so the card price is the price you pay. Cost never leaves this scope.
        $prices = $products->mapWithKeys(function ($p) use ($svc) {
            try {
                return [$p->id => $p->is_fixed ? $svc->price($p, (float) $p->fixed_amount)['price'] : null];
            } catch (Throwable) {
                return [$p->id => null];
            }
        });

        // Price tiles for a flexible-amount airtime product: what each preset amount costs, through the same engine as the quote.
        $tilePrices = [];
        if ($selected && ! $selected->is_fixed) {
            foreach (array_filter([50, 100, 200, 500, 1000, 2000, 5000, 10000], fn ($v) => $v >= (float) $selected->min_amount && $v <= (float) $selected->max_amount) as $v) {
                try {
                    $tilePrices[$v] = $svc->price($selected, (float) $v)['price'];
                } catch (Throwable) {
                    $tilePrices[$v] = null;
                }
            }
        }
        $rateLine = $quote ? $svc->rateLine($quote) : null;

        $dataCount = $this->operator ? $cat->products($this->country, 'data', $this->operator)->count() : 0;
        $hasData = $dataCount > 0;

        $offerQuote = $this->offerQuoteId ? TopupQuote::with('product')->where('user_id', Auth::id())->find($this->offerQuoteId) : null;
        $offerProduct = $this->offerId ? TopupProduct::find($this->offerId) : null;
        $offers = [];
        $offerCountries = [];
        $oc = '';
        if (class_exists(\App\Support\Topup\TopupBrandTint::class)) {
            try {
                $svcOffers = app(\App\Services\Topup\TopupOffers::class);
                $offerCountries = $svcOffers->countries();
                $oc = $this->offerCountry !== '' ? $this->offerCountry : (in_array($this->country, $offerCountries, true) ? $this->country : ($offerCountries[0] ?? 'NG'));
                $offers = $svcOffers->for($oc);
            } catch (Throwable) {
                $offers = [];
            }
        }

        return view('livewire.topup', compact('dataCount', 'beneficiaries', 'autoCount', 'airtimeCount', 'popular', 'total', 'countries', 'sellable', 'operators', 'products', 'selected', 'quote', 'prices', 'stale', 'hasData', 'windowCounts', 'tilePrices', 'rateLine', 'offers', 'offerQuote', 'offerProduct', 'offerCountries', 'oc'));
    }
}
