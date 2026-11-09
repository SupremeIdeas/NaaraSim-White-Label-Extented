<?php

namespace App\Services\Topup;

use App\Models\TopupAutoReload;
use App\Models\TopupOrder;
use App\Services\Wallet\WalletService;
use App\Support\Notifications\Notify;
use App\Support\TopupSettings;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Repeating top-ups (daily / weekly / monthly) for a saved beneficiary, airtime or a data bundle. Every run is an ORDINARY top-up through
 * TopupOrderService (same fraud limits, same atomic debit, same refund rules), with an idempotency key per scheduled slot so a re-run of
 * the scheduler can never charge twice. Money problems pause the plan and tell the customer; they are never retried blindly.
 */
class TopupAutoReloadService
{
    public const MAX_FAILURES = 3;

    public function __construct(private ProviderRouter $router, private QuoteService $quotes, private TopupOrderService $orders, private WalletService $wallet) {}

    /** Plans a user may have at once (admin setting). */
    public static function limit(): int
    {
        return max(1, TopupSettings::int('max_autoreloads_per_user'));
    }

    /** @return int how many plans were attempted */
    public function runDue(): int
    {
        $n = 0;
        if (! TopupSettings::bool('autoreload_enabled') || ! \App\Support\ComingSoon::isLive('naara_topup') || \App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_TOPUP)) {
            return 0;
        }
        TopupAutoReload::where('active', true)->where('next_run_at', '<=', now())->orderBy('next_run_at')->limit(200)->get()->each(function (TopupAutoReload $a) use (&$n) {
            $n++;
            $this->runOne($a->id);
        });
        $this->warnLowBalance();

        return $n;
    }

    public function runOne(int $id): ?TopupOrder
    {
        $a = DB::transaction(function () use ($id) {
            $a = TopupAutoReload::lockForUpdate()->find($id);
            if (! $a || ! $a->active || ! $a->next_run_at || $a->next_run_at->isFuture()) {
                return null;
            }
            // Claim the slot FIRST: the next run is scheduled before the money moves, so a crash can skip a day but never double-charge.
            $slot = $a->next_run_at->timestamp;
            $a->forceFill(['next_run_at' => $a->computeNext(max(now(), $a->next_run_at)), 'last_run_at' => now()])->save();
            $a->slot = $slot;

            return $a;
        });
        if (! $a) {
            return null;
        }
        $user = $a->user_id ? \App\Models\User::find($a->user_id) : null;
        $b = $a->beneficiary;
        if (! $user || ! $b || ! $user->is_active) {
            return null;
        }

        try {
            $product = $this->router->matchProduct($a->country_iso, $a->product_type, $a->operator_name, (float) $a->send_amount, $a->send_currency, $a->product_name);
            if (! $product) {
                throw new TopupException('That top-up is not on offer right now.');
            }
            $quote = $this->quotes->create($user, $product, $b->phone_e164, (float) $a->send_amount, useCredits: false);
            $order = $this->orders->place($user, $quote, 'auto-'.$a->id.'-'.$a->slot);
            if ($order->status === TopupOrder::PAYMENT_FAILED) {
                throw new TopupException('Your wallet did not have enough for it.');
            }
            $a->forceFill(['last_order_id' => $order->id, 'last_status' => $order->status, 'runs' => $a->runs + 1, 'failures' => 0])->save();
            $b->forceFill(['last_used_at' => now()])->save();

            return $order;
        } catch (TopupException $e) {
            $this->failed($a, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->failed($a, 'Something went wrong on our side.');
        }

        return null;
    }

    private function failed(TopupAutoReload $a, string $why): void
    {
        $fails = $a->failures + 1;
        $pause = $fails >= self::MAX_FAILURES;
        $a->forceFill(['failures' => $fails, 'last_status' => 'skipped', 'active' => ! $pause, 'paused_reason' => $pause ? substr($why, 0, 150) : null])->save();
        try {
            Notify::event($a->user, $pause ? 'topup.autoreload_paused' : 'topup.autoreload_failed', ['to' => $a->beneficiary?->name ?: $a->beneficiary?->phone_e164, 'why' => $why], 'topup.auto.'.$a->id.'.'.now()->format('YmdH'));
        } catch (Throwable) {
        }
    }

    /** A day before a run, tell the customer if the wallet will not cover it (once a day per plan). */
    private function warnLowBalance(): void
    {
        TopupAutoReload::where('active', true)->whereBetween('next_run_at', [now(), now()->addDay()])->where(fn ($q) => $q->whereNull('low_balance_notified_at')->orWhere('low_balance_notified_at', '<', now()->subDay()))->limit(200)->get()->each(function (TopupAutoReload $a) {
            try {
                $product = $this->router->matchProduct($a->country_iso, $a->product_type, $a->operator_name, (float) $a->send_amount, $a->send_currency, $a->product_name);
                if (! $product) {
                    return;
                }
                $price = $this->quotes->price($product, (float) $a->send_amount)['price'];
                if ($this->wallet->spendableUsd($a->user) < $price) {
                    Notify::event($a->user, 'topup.autoreload_low_balance', ['to' => $a->beneficiary?->name ?: $a->beneficiary?->phone_e164, 'amount' => '$'.number_format($price, 2)], 'topup.low.'.$a->id.'.'.now()->format('Ymd'));
                    $a->forceFill(['low_balance_notified_at' => now()])->save();
                }
            } catch (Throwable) {
            }
        });
    }
}
