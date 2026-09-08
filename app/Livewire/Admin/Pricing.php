<?php

namespace App\Livewire\Admin;

use App\Jobs\RecomputePlanPricingJob;
use App\Models\EsimPlan;
use App\Models\Setting;
use App\Services\Pricing\PricingEngine;
use App\Support\Auditor;
use App\Support\EsimCatalogue;
use App\Support\PricingDisplay;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin pricing panel (blueprint Section 13.3). Global markup + profit floor,
 * per-plan override / fixed price / active / featured, and a LIVE profit
 * summary that refreshes as the admin types (wire:model.live). Saving the
 * global markup dispatches a recompute of every plan; every change is
 * audit-logged. Cost is admin-only here — this surface is behind the admin gate.
 */
#[Layout('components.layouts.admin')]
class Pricing extends Component
{
    use WithPagination;

    // Global settings
    public $default_markup_pct;

    public $minimum_profit_usd;

    // Margin-safe discount floor (discount-floor blueprint §2) — the max % of
    // MARGIN (not price) a single order's combined coupon+credit discount may eat.
    public $discount_margin_cap_pct;

    public $merchant_discount_admin_pct;

    public $merchant_discount_merchant_pct;

    // Outbound-SMS wholesale cost per provider (Numbers V6 §6 — a user texting
    // from their Naara Line). Retail is layered on by PricingEngine; this is the
    // cost basis, never surfaced to users.
    public $sms_send_cost_twilio;

    public $sms_send_cost_telnyx;

    /** Outbound-MMS (attachment) wholesale cost per provider. */
    public $mms_send_cost_twilio;

    public $mms_send_cost_telnyx;

    // Public pricing page (Module 29)
    public string $public_mode = 'auto';

    public array $estimate_tiers = [];

    // Per-plan edit buffer
    public ?int $editingPlanId = null;

    public $override_markup_pct = '';

    public $manual_retail_usd = '';

    public bool $is_active = true;

    public bool $is_featured = false;

    public ?string $saved = null;

    /**
     * Authorization runs on EVERY request — not just the initial load. Livewire
     * dispatches method calls to a single `/livewire/update` endpoint that does
     * NOT re-apply the route's `role:` middleware (only its persistent-middleware
     * allow-list runs there), so a mount()-only or route-only check would leave
     * saveGlobal/savePlan callable by any authenticated user holding a snapshot.
     * booted() fires on both the initial render and every subsequent update.
     */
    public function booted(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->default_markup_pct = Setting::getValue('pricing.default_markup_pct', 30);
        $this->minimum_profit_usd = Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $this->discount_margin_cap_pct = Setting::getValue('pricing.discount_margin_cap_pct', 30);
        $this->merchant_discount_admin_pct = Setting::getValue('pricing.merchant_discount_admin_pct', 20);
        $this->merchant_discount_merchant_pct = Setting::getValue('pricing.merchant_discount_merchant_pct', 10);
        $this->sms_send_cost_twilio = Setting::getValue('pricing.sms_send_cost.twilio', 0.0079);
        $this->sms_send_cost_telnyx = Setting::getValue('pricing.sms_send_cost.telnyx', 0.004);
        $this->mms_send_cost_twilio = Setting::getValue('pricing.mms_send_cost.twilio', 0.02);
        $this->mms_send_cost_telnyx = Setting::getValue('pricing.mms_send_cost.telnyx', 0.01);
        $this->public_mode = (string) Setting::getValue('pricing.public_mode', 'auto');
        $this->estimate_tiers = PricingDisplay::estimateTiers();
    }

    public function savePublicPricing(): void
    {
        $this->validate([
            'public_mode' => 'required|in:auto,live,estimate',
            'estimate_tiers' => 'array|max:6',
            'estimate_tiers.*.name' => 'required|string|max:40',
            'estimate_tiers.*.from_usd' => 'required|numeric|min:0',
            'estimate_tiers.*.data' => 'required|string|max:20',
            'estimate_tiers.*.validity' => 'required|string|max:20',
            'estimate_tiers.*.blurb' => 'required|string|max:200',
        ]);

        Setting::setValue('pricing.public_mode', $this->public_mode, 'pricing');
        Setting::setValue('pricing.estimate_tiers', array_values($this->estimate_tiers), 'pricing');

        $this->audit('pricing.public_updated', null, ['public_mode' => $this->public_mode]);
        $this->saved = 'Public pricing page settings saved.';
    }

    public function addTier(): void
    {
        $this->estimate_tiers[] = ['name' => '', 'from_usd' => 0, 'data' => '', 'validity' => '', 'blurb' => ''];
    }

    public function removeTier(int $i): void
    {
        unset($this->estimate_tiers[$i]);
        $this->estimate_tiers = array_values($this->estimate_tiers);
    }

    public function saveGlobal(): void
    {
        $this->validate([
            'default_markup_pct' => 'required|numeric|min:0|max:1000',
            'minimum_profit_usd' => 'required|numeric|min:0',
            'discount_margin_cap_pct' => 'required|numeric|min:0|max:100',
            'merchant_discount_admin_pct' => 'required|numeric|min:0|max:100',
            'merchant_discount_merchant_pct' => 'required|numeric|min:0|max:100',
            'sms_send_cost_twilio' => 'required|numeric|min:0|max:5',
            'sms_send_cost_telnyx' => 'required|numeric|min:0|max:5',
            'mms_send_cost_twilio' => 'required|numeric|min:0|max:5',
            'mms_send_cost_telnyx' => 'required|numeric|min:0|max:5',
        ]);

        Setting::setValue('pricing.default_markup_pct', (float) $this->default_markup_pct, 'pricing');
        Setting::setValue('pricing.minimum_profit_usd', (float) $this->minimum_profit_usd, 'pricing');
        Setting::setValue('pricing.discount_margin_cap_pct', (float) $this->discount_margin_cap_pct, 'pricing');
        Setting::setValue('pricing.merchant_discount_admin_pct', (float) $this->merchant_discount_admin_pct, 'pricing');
        Setting::setValue('pricing.merchant_discount_merchant_pct', (float) $this->merchant_discount_merchant_pct, 'pricing');
        Setting::setValue('pricing.sms_send_cost.twilio', round((float) $this->sms_send_cost_twilio, 4), 'pricing');
        Setting::setValue('pricing.sms_send_cost.telnyx', round((float) $this->sms_send_cost_telnyx, 4), 'pricing');
        Setting::setValue('pricing.mms_send_cost.twilio', round((float) $this->mms_send_cost_twilio, 4), 'pricing');
        Setting::setValue('pricing.mms_send_cost.telnyx', round((float) $this->mms_send_cost_telnyx, 4), 'pricing');

        // Recompute every plan's retail against the new global markup (rule 1.4).
        RecomputePlanPricingJob::dispatch();
        $this->audit('pricing.global_updated', null, [
            'default_markup_pct' => $this->default_markup_pct,
            'minimum_profit_usd' => $this->minimum_profit_usd,
        ]);

        $this->saved = 'Global pricing saved — all plans are being repriced.';
    }

    public function editPlan(int $planId): void
    {
        $plan = EsimPlan::findOrFail($planId);
        $this->editingPlanId = $plan->id;
        $this->override_markup_pct = $plan->override_markup_pct ?? '';
        $this->manual_retail_usd = $plan->manual_retail_usd ?? '';
        $this->is_active = (bool) $plan->is_active;
        $this->is_featured = (bool) $plan->is_featured;
        $this->saved = null;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingPlanId', 'override_markup_pct', 'manual_retail_usd', 'is_active', 'is_featured');
    }

    public function savePlan(PricingEngine $engine): void
    {
        $plan = EsimPlan::findOrFail($this->editingPlanId);
        $plan->override_markup_pct = $this->override_markup_pct === '' ? null : (float) $this->override_markup_pct;
        $plan->manual_retail_usd = $this->manual_retail_usd === '' ? null : (float) $this->manual_retail_usd;
        $plan->is_active = $this->is_active;
        $plan->is_featured = $this->is_featured;
        $plan->save();

        // Keep computed_retail_usd (and the generated final_retail_usd) in sync.
        $engine->recompute($plan);

        // Readiness-audit fix (2026-09-07): the storefront "from $X" teaser
        // grid caches final_retail_usd forever and was only flushed by
        // catalogue-sync/feature-toggle actions, never by a manual price
        // override here — so a plan's browse price could disagree with its
        // real price indefinitely after this exact save.
        EsimCatalogue::flush();

        $this->audit('pricing.plan_updated', $plan->id, [
            'override_markup_pct' => $plan->override_markup_pct,
            'manual_retail_usd' => $plan->manual_retail_usd,
            'is_active' => $plan->is_active,
        ]);

        $this->saved = "Plan “{$plan->name}” updated.";
        $this->cancelEdit();
    }

    private function audit(string $action, ?int $modelId, array $payload): void
    {
        Auditor::log($action, EsimPlan::class, $modelId, $payload);
    }

    public function render(PricingEngine $engine)
    {
        // Live profit preview for the plan being edited (no logging on keystroke).
        $summary = null;
        if ($this->editingPlanId) {
            $preview = EsimPlan::find($this->editingPlanId);
            if ($preview) {
                $preview->override_markup_pct = $this->override_markup_pct === '' ? null : (float) $this->override_markup_pct;
                $preview->manual_retail_usd = $this->manual_retail_usd === '' ? null : (float) $this->manual_retail_usd;
                $summary = $engine->getProfitSummary($preview, log: false);
            }
        }

        return view('livewire.admin.pricing', [
            'plans' => EsimPlan::orderBy('name')->paginate(10),
            'summary' => $summary,
        ]);
    }
}
