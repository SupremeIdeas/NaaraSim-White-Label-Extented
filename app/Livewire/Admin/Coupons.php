<?php

namespace App\Livewire\Admin;

use App\Models\Coupon;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Coupons (Module 31). Create discount codes for banner offers. The
 * percent is capped at 90 in the form, but the real protection is CouponEngine:
 * every application is clamped to provider cost + minimum profit, so no code —
 * whatever its percent — can ever sell below wholesale.
 */
#[Layout('components.layouts.admin')]
class Coupons extends Component
{
    public string $code = '';

    public $percent_off = 10;

    public string $applies_to = 'all';

    public $max_redemptions = null;

    public $per_user_limit = 1;

    public $expires_at = null;

    public ?string $saved = null;

    public function generateCode(): void
    {
        $this->code = strtoupper(Str::random(8));
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'code' => 'required|string|min:3|max:40|regex:/^[A-Za-z0-9\-_]+$/|unique:coupons,code',
            'percent_off' => 'required|numeric|min:1|max:90',
            'applies_to' => 'required|in:all,esim,number',
            'max_redemptions' => 'nullable|integer|min:1',
            'per_user_limit' => 'required|integer|min:1|max:100',
            'expires_at' => 'nullable|date|after:now',
        ], [
            'code.regex' => 'Letters, numbers, dashes and underscores only.',
        ]);

        $coupon = Coupon::create([
            'code' => strtoupper($this->code),
            'percent_off' => (float) $this->percent_off,
            'applies_to' => $this->applies_to,
            'max_redemptions' => $this->max_redemptions ?: null,
            'per_user_limit' => (int) $this->per_user_limit,
            'expires_at' => $this->expires_at ?: null,
            'is_active' => true,
        ]);

        Auditor::log('coupon.created', Coupon::class, $coupon->id, ['code' => $coupon->code, 'percent' => $coupon->percent_off]);
        $this->reset('code', 'max_redemptions', 'expires_at');
        $this->percent_off = 10;
        $this->per_user_limit = 1;
        $this->saved = "Coupon {$coupon->code} is live. Discounts are always clamped above cost + minimum profit.";
        $this->dispatch('nx-toast', type: 'success', message: 'Coupon created.');
    }

    public function toggle(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => ! $coupon->is_active]);
        Auditor::log('coupon.toggled', Coupon::class, $coupon->id, ['code' => $coupon->code, 'active' => $coupon->is_active]);
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $coupon = Coupon::findOrFail($id);

        // A used coupon is part of the money audit trail — pause it instead of
        // erasing its redemption history.
        if ($coupon->redemptions()->exists()) {
            $coupon->update(['is_active' => false]);
            $this->dispatch('nx-toast', type: 'info', message: 'This coupon has been used, so it was paused instead of deleted (audit trail).');

            return;
        }

        Auditor::log('coupon.deleted', Coupon::class, $coupon->id, ['code' => $coupon->code]);
        $coupon->delete();
    }

    /** Flip the friendly first-purchase / comeback dashboard nudges on or off. */
    public function toggleNudges(): void
    {
        $on = ! \App\Support\MarketingCoupons::enabled();
        \App\Models\Setting::setValue(\App\Support\MarketingCoupons::FLAG, $on, 'marketing');
        $this->saved = $on ? 'Marketing nudges are ON.' : 'Marketing nudges are OFF.';
    }

    public function render()
    {
        return view('livewire.admin.coupons', [
            'coupons' => Coupon::withCount('redemptions')
                ->withSum('redemptions as total_saved', 'amount_saved')
                ->latest()->get(),
            'nudgesOn' => \App\Support\MarketingCoupons::enabled(),
        ]);
    }
}
