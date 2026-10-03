<?php

namespace App\Livewire;

use App\Services\Payouts\PayoutException;
use App\Services\Payouts\Rail\PayoutRailRegistry;
use App\Services\Payouts\Rail\RailAdvisor;
use App\Services\Payouts\Rail\RailCopy;
use App\Services\Payouts\Rail\RailGuideService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "How do I get paid?" (Rail Guide, Addendum B). Step 1 of the Withdraw flow and a
 * standalone page. All decisions come from RailAdvisor + the registry; every action is
 * re-validated server-side by RailGuideService, so a direct call cannot bypass the guide.
 */
#[Layout('components.layouts.customer')]
class PayoutGuide extends Component
{
    private const PER_PAGE = 40;

    public bool $embedded = false;

    public string $country = '';

    public string $search = '';

    public string $filter = 'all';

    public string $sort = 'az';

    public int $page = 1;

    /** @var array<int, bool> */
    public array $ack = [false, false, false];

    public bool $showAck = false;

    public ?string $notice = null;

    public ?string $error = null;

    private bool $tracked = false;

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        $user = Auth::user();
        $this->country = strtoupper((string) ($user->country_code ?: 'NG'));
        app(RailGuideService::class)->track($user, 'guide_viewed', $this->country);
    }

    public function updatedCountry(): void
    {
        $this->country = strtoupper(substr($this->country, 0, 2));
        $this->showAck = false;
        $this->ack = [false, false, false];
        $this->notice = $this->error = null;
        app(RailGuideService::class)->track(Auth::user(), 'country_changed', $this->country);
    }

    public function updated($name): void
    {
        in_array($name, ['search', 'filter', 'sort'], true) && $this->page = 1;
    }

    /** "Use this rail" — validated server-side; local rails hand over to the existing setup forms. */
    public function choose(string $rail, RailGuideService $guide): void
    {
        $this->error = $this->notice = null;
        try {
            $next = $guide->choose(Auth::user(), $rail, $this->country);
        } catch (PayoutException $e) {
            $this->error = $e->getMessage();

            return;
        }

        if ($next === 'acknowledge') {
            $this->showAck = true;

            return;
        }
        $this->notice = (string) __('payout_guide.rail_chosen');
        $this->dispatch('payout-rail-chosen', rail: $rail, country: $this->country);
    }

    public function confirmGlobal(RailGuideService $guide): void
    {
        $this->error = $this->notice = null;
        try {
            $guide->acknowledgeGlobal(Auth::user(), $this->country, $this->ack);
        } catch (PayoutException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->showAck = false;
        $this->notice = (string) __('payout_guide.rail_chosen');
        $this->dispatch('payout-rail-chosen', rail: 'global', country: $this->country);
    }

    public function notifyMe(RailGuideService $guide): void
    {
        $guide->requestNotify(Auth::user(), $this->country);
        $this->notice = (string) __('payout_guide.notify_sent');
    }

    public function render(RailAdvisor $advisor, PayoutRailRegistry $registry, RailCopy $copy, RailGuideService $guide)
    {
        $user = Auth::user();
        $advice = $advisor->advise($user, $this->country);

        // Recommendation is logged once per country per component lifetime, not on every re-render.
        $key = 'rec:'.$this->country;
        if ($advice['options'] && ! session()->has("guide.$key")) {
            session()->put("guide.$key", true);
            $first = $advice['options'][0];
            in_array($first['state'], ['recommended', 'global_fallback'], true) && $guide->track($user, 'rail_recommended', $this->country, $first['rail']);
        }

        $options = array_map(fn ($o) => $o + [
            'eta' => $copy->eta($o['rail'], $this->country),
            'fee' => in_array($o['state'], ['recommended', 'available', 'global_fallback'], true) ? $copy->fee($o['rail'], $this->country) : null,
        ], $advice['options']);

        return view('livewire.payout-guide', [
            'advice' => $advice, 'options' => $options, 'matrix' => $this->matrixPage($registry),
            'countryOptions' => $this->countryOptions(), 'globalDays' => RailCopy::globalDays(),
            'verifiedAt' => $registry->lastVerifiedAt(),
            'recommendedRail' => collect($options)->firstWhere('state', 'recommended')['rail'] ?? null,
            'railsAvailable' => collect($options)->whereIn('state', ['recommended', 'available'])->pluck('rail')->all(),
            'dir' => \App\Support\Locale::isRtl(app()->getLocale()) ? 'rtl' : 'ltr',
        ]);
    }

    /** @return array<string, string> code => localised name, A–Z */
    private function countryOptions(): array
    {
        $names = [];
        foreach (PayoutRailRegistry::countryCodes() as $c) {
            $names[$c] = PayoutRailRegistry::countryName($c);
        }
        asort($names, SORT_LOCALE_STRING);

        return $names;
    }

    /** @return array{rows: list<array<string, mixed>>, pages: int, total: int} */
    private function matrixPage(PayoutRailRegistry $registry): array
    {
        $rows = collect($registry->matrix())->map(fn ($r) => $r + ['name' => PayoutRailRegistry::countryName($r['country'])]);

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['name']), $needle) || str_contains(mb_strtolower($r['country']), $needle));
        }
        $rows = $rows->filter(function ($r) {
            $c = $r['rails'];

            return match ($this->filter) {
                'paystack' => $c['paystack'] === 'available',
                'flutterwave' => $c['flutterwave'] === 'available',
                'stripe' => $c['stripe_connect'] === 'available',
                'global' => $c['global'] === 'available' && ! in_array('available', [$c['paystack'], $c['flutterwave'], $c['stripe_connect']], true),
                'unavailable' => ! in_array('available', array_values($c), true),
                default => true,
            };
        });

        $rows = $this->sort === 'available'
            ? $rows->sortBy([[fn ($r) => -count(array_filter($r['rails'], fn ($s) => $s === 'available')), 'asc'], ['name', 'asc']])
            : $rows->sortBy('name', SORT_LOCALE_STRING);

        // The user's own country is pinned on top, highlighted.
        $mine = $rows->firstWhere('country', $this->country);
        $rows = $rows->reject(fn ($r) => $r['country'] === $this->country)->values();
        $total = $rows->count() + ($mine ? 1 : 0);
        $slice = $rows->slice(($this->page - 1) * self::PER_PAGE, self::PER_PAGE)->values();
        if ($mine && $this->page === 1) {
            $slice->prepend($mine + ['pinned' => true]);
        }

        return ['rows' => $slice->all(), 'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'total' => $total];
    }
}
