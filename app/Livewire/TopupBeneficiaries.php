<?php

namespace App\Livewire;

use App\Models\TopupAutoReload;
use App\Models\TopupBeneficiary;
use App\Models\TopupProduct;
use App\Services\Topup\NumberResolver;
use App\Services\Topup\ProviderRouter;
use App\Services\Topup\QuoteService;
use App\Services\Topup\TopupAutoReloadService;
use App\Services\Topup\TopupCatalogue;
use App\Support\ComingSoon;
use App\Support\CountryNames;
use App\Support\FeatureEntitlements;
use App\Support\FeatureLocks;
use App\Support\TopupSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/** My people: saved numbers (beneficiaries) and their auto-reload plans (daily / weekly / monthly, airtime or a data bundle). */
#[Layout('components.layouts.customer')]
class TopupBeneficiaries extends Component
{
    // add a person
    public string $country = '';

    public string $phone = '';

    public string $name = '';

    public ?string $operator = null;

    public ?string $phoneError = null;

    public string $e164 = '';

    public string $netSource = 'none';

    // rename
    public ?int $editId = null;

    public string $editName = '';

    // auto-reload form
    public ?int $planFor = null;

    public string $planType = 'airtime';

    public ?int $planProduct = null;

    public string $planAmount = '';

    public string $planFreq = 'monthly';

    public int $planWeekday = 1;

    public int $planMonthday = 1;

    public int $planHour = 9;

    public ?string $notice = null;

    public ?string $problem = null;

    public function booted(): void
    {
        ComingSoon::abortIfOff('naara_topup');
        abort_if(FeatureEntitlements::locked(FeatureLocks::F_TOPUP), 404);
    }

    public function mount(): void
    {
        $this->country = strtoupper((string) TopupSettings::get('default_country'));
    }

    public function updatedPhone(): void
    {
        $this->resolveNumber();
    }

    public function updatedCountry(): void
    {
        $this->resolveNumber();
    }

    private function resolveNumber(): void
    {
        $r = app(NumberResolver::class)->resolve($this->phone, $this->country ?: null, Auth::id());
        $this->phoneError = $r['valid'] ? null : $r['error'];
        $this->e164 = $r['valid'] ? $r['e164'] : '';
        $this->netSource = $r['source'];
        if ($r['valid'] && $r['country'] && $r['country'] !== $this->country) {
            $this->country = $r['country'];
        }
        $this->operator = $r['valid'] ? $r['operator'] : null;
    }

    public function pickOperator(string $op): void
    {
        $this->operator = $op;
    }

    public function add(): void
    {
        $this->problem = $this->notice = null;
        if ($this->e164 === '') {
            $this->problem = $this->phoneError ?: 'Enter a valid phone number first.';

            return;
        }
        if (trim($this->name) === '') {
            $this->problem = 'Give this person a name.';

            return;
        }
        if (TopupBeneficiary::where('user_id', Auth::id())->count() >= 100) {
            $this->problem = 'You have reached the limit of saved people.';

            return;
        }
        TopupBeneficiary::updateOrCreate(['user_id' => Auth::id(), 'phone_e164' => $this->e164], ['name' => mb_substr(trim($this->name), 0, 60), 'country_iso' => $this->country, 'operator_name' => $this->operator]);
        $this->reset('phone', 'name', 'operator', 'e164', 'netSource');
        $this->notice = 'Saved.';
    }

    public function startEdit(int $id): void
    {
        $b = TopupBeneficiary::where('user_id', Auth::id())->findOrFail($id);
        $this->editId = $b->id;
        $this->editName = $b->name;
    }

    public function saveEdit(): void
    {
        if ($this->editId && trim($this->editName) !== '') {
            TopupBeneficiary::where('user_id', Auth::id())->whereKey($this->editId)->update(['name' => mb_substr(trim($this->editName), 0, 60)]);
        }
        $this->editId = null;
    }

    public function remove(int $id): void
    {
        TopupBeneficiary::where('user_id', Auth::id())->whereKey($id)->delete();   // their plans go with them
        $this->notice = 'Removed, and any auto top-ups for that person were stopped.';
    }

    public function togglePlan(int $id): void
    {
        $a = TopupAutoReload::where('user_id', Auth::id())->findOrFail($id);
        if (! $a->active && TopupAutoReload::where('user_id', Auth::id())->where('active', true)->count() >= TopupAutoReloadService::limit()) {
            $this->problem = 'You already have the maximum number of active auto top-ups.';

            return;
        }
        $a->active = ! $a->active;
        if ($a->active) {
            $a->next_run_at = $a->computeNext();
            $a->failures = 0;
            $a->paused_reason = null;
        }
        $a->save();
    }

    public function removePlan(int $id): void
    {
        TopupAutoReload::where('user_id', Auth::id())->whereKey($id)->delete();
    }

    public function setPlanType(string $t): void
    {
        $this->planType = $t === 'data' ? 'data' : 'airtime';
        $this->planProduct = null;
    }

    public function openPlan(int $beneficiaryId): void
    {
        $this->planFor = $this->planFor === $beneficiaryId ? null : $beneficiaryId;
        $this->planProduct = null;
        $this->planAmount = '';
        $this->problem = null;
    }

    public function createPlan(): void
    {
        $this->problem = $this->notice = null;
        try {
            $b = TopupBeneficiary::where('user_id', Auth::id())->findOrFail($this->planFor);
            if (! TopupSettings::bool('autoreload_enabled')) {
                throw new \RuntimeException('Auto top-up is switched off right now.');
            }
            if (TopupAutoReload::where('user_id', Auth::id())->where('active', true)->count() >= TopupAutoReloadService::limit()) {
                throw new \RuntimeException('You already have the maximum number of active auto top-ups ('.TopupAutoReloadService::limit().').');
            }
            $p = TopupProduct::where('active', true)->findOrFail($this->planProduct);
            $amount = app(QuoteService::class)->assertAmount($p, (float) str_replace(',', '', $this->planAmount ?: (string) $p->fixed_amount));
            $a = new TopupAutoReload([
                'user_id' => Auth::id(), 'beneficiary_id' => $b->id, 'country_iso' => $p->country_iso, 'operator_name' => $p->operator_name, 'product_type' => $p->type === 'data' ? 'data' : 'airtime',
                'send_amount' => $amount, 'send_currency' => $p->currency, 'product_name' => $p->type === 'data' ? $p->name : null,
                'frequency' => in_array($this->planFreq, ['daily', 'weekly', 'monthly'], true) ? $this->planFreq : 'monthly', 'run_hour' => max(0, min(23, $this->planHour)),
                'run_weekday' => max(0, min(6, $this->planWeekday)), 'run_monthday' => max(1, min(28, $this->planMonthday)),
                'timezone' => (string) (Auth::user()->timezone ?? config('app.timezone')), 'active' => true,
            ]);
            $a->next_run_at = $a->computeNext();
            $a->save();
            $this->planFor = null;
            $this->notice = 'Auto top-up set. Next run: '.$a->next_run_at->setTimezone($a->timezone)->format('D j M, H:i').'.';
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->problem = 'Pick what to send first.';
        } catch (Throwable $e) {
            $this->problem = $e->getMessage();
        }
    }

    public function render()
    {
        $bens = TopupBeneficiary::where('user_id', Auth::id())->with('autoReloads')->orderBy('name')->get();
        $cat = app(TopupCatalogue::class);
        $countries = $cat->countries()->mapWithKeys(fn ($c) => [$c => CountryNames::name($c)])->sort()->all();
        $operators = $this->country && $this->e164 !== '' ? $cat->operators($this->country)->all() : [];
        $planOptions = collect();
        if ($this->planFor && ($pb = $bens->firstWhere('id', $this->planFor)) && $pb->operator_name) {
            $planOptions = $cat->products($pb->country_iso, $this->planType, $pb->operator_name)->sortBy(fn ($p) => [$p->is_fixed ? 1 : 0, (float) $p->fixed_amount])->values();
        }
        $svc = app(QuoteService::class);
        $prices = $planOptions->mapWithKeys(function ($p) use ($svc) {
            try {
                return [$p->id => $p->is_fixed ? $svc->price($p, (float) $p->fixed_amount)['price'] : null];
            } catch (Throwable) {
                return [$p->id => null];
            }
        });

        return view('livewire.topup-beneficiaries', compact('bens', 'countries', 'operators', 'planOptions', 'prices') + ['router' => app(ProviderRouter::class), 'limit' => TopupAutoReloadService::limit()]);
    }
}
