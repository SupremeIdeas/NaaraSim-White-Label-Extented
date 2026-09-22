<?php

namespace App\Livewire\Admin;

use App\Models\BrandPartner;
use App\Models\BrandSubscriptionPlan;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Brand directory (BUILD-9 §10). Manage subscription plans (add/edit/
 * archive pricing + guarantees) and run the self-service brands: see each one's
 * status, plan and priority score, and override its listing status (e.g. suspend
 * for a policy violation) independent of the billing automation — an admin
 * override always takes precedence over what billing/priority would set.
 */
#[Layout('components.layouts.admin')]
class BrandDirectory extends Component
{
    /** Plan edit rows, keyed by plan id, plus a 'new' row. */
    public array $plans = [];

    public array $newPlan = ['name' => '', 'price_usd_per_month' => 19, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 50, 'video_previews_allowed' => 0, 'credit_reward_per_follow' => 5];

    public ?string $saved = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        foreach (BrandSubscriptionPlan::ordered()->get() as $p) {
            $this->plans[$p->id] = [
                'name' => $p->name, 'price_usd_per_month' => $p->price_usd_per_month,
                'handles_included' => $p->handles_included,
                'guaranteed_followers_per_handle_per_month' => $p->guaranteed_followers_per_handle_per_month,
                'video_previews_allowed' => $p->video_previews_allowed,
                'credit_reward_per_follow' => $p->credit_reward_per_follow,
                'is_active' => $p->is_active,
            ];
        }
    }

    public function savePlan(int $id): void
    {
        $d = $this->validate([
            "plans.{$id}.name" => 'required|string|max:60',
            "plans.{$id}.price_usd_per_month" => 'required|numeric|min:0|max:100000',
            "plans.{$id}.handles_included" => 'required|integer|min:1|max:50',
            "plans.{$id}.guaranteed_followers_per_handle_per_month" => 'required|integer|min:0|max:1000000',
            "plans.{$id}.video_previews_allowed" => 'required|integer|min:0|max:10',
            "plans.{$id}.credit_reward_per_follow" => 'nullable|numeric|min:0|max:1000',
        ])['plans'][$id];

        BrandSubscriptionPlan::whereKey($id)->update($d + ['is_active' => (bool) ($this->plans[$id]['is_active'] ?? true)]);
        Auditor::log('brand.plan_updated', 'BrandSubscriptionPlan', $id);
        $this->saved = 'Plan saved.';
    }

    public function togglePlan(int $id): void
    {
        $p = BrandSubscriptionPlan::findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
        $this->plans[$id]['is_active'] = $p->is_active;
    }

    public function addPlan(): void
    {
        $d = $this->validate([
            'newPlan.name' => 'required|string|max:60',
            'newPlan.price_usd_per_month' => 'required|numeric|min:0|max:100000',
            'newPlan.handles_included' => 'required|integer|min:1|max:50',
            'newPlan.guaranteed_followers_per_handle_per_month' => 'required|integer|min:0|max:1000000',
            'newPlan.video_previews_allowed' => 'required|integer|min:0|max:10',
            'newPlan.credit_reward_per_follow' => 'nullable|numeric|min:0|max:1000',
        ])['newPlan'];

        $plan = BrandSubscriptionPlan::create($d + ['is_active' => true, 'sort_order' => (int) BrandSubscriptionPlan::max('sort_order') + 1]);
        $this->plans[$plan->id] = $d + ['is_active' => true];
        $this->newPlan = ['name' => '', 'price_usd_per_month' => 19, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 50, 'video_previews_allowed' => 0, 'credit_reward_per_follow' => 5];
        $this->saved = 'Plan added.';
    }

    /** Admin override — precedence over billing/priority automation (§10.4). */
    public function suspendBrand(int $id): void
    {
        $b = BrandPartner::findOrFail($id);
        $b->update(['listing_status' => BrandPartner::STATUS_DISABLED]);
        Auditor::log('brand.admin_suspended', 'BrandPartner', $id);
    }

    public function restoreBrand(int $id): void
    {
        $b = BrandPartner::findOrFail($id);
        $b->update(['listing_status' => BrandPartner::STATUS_ACTIVE]);
        Auditor::log('brand.admin_restored', 'BrandPartner', $id);
    }

    public function render()
    {
        return view('livewire.admin.brand-directory', [
            'planModels' => BrandSubscriptionPlan::ordered()->get(),
            'brands' => BrandPartner::whereNotNull('owner_user_id')
                ->with(['owner', 'plan', 'subscription'])
                ->orderByDesc('id')->paginate(20),
        ]);
    }
}
