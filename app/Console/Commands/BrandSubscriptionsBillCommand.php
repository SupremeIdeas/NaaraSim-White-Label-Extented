<?php

namespace App\Console\Commands;

use App\Exceptions\InsufficientBalanceException;
use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Notifications\BrandBillingReminderNotification;
use App\Services\Brands\BrandPriorityService;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Monthly billing sweep for self-service brand listings (BUILD-9 §5.2 + §6).
 * Mirrors the Naara Line renewal command exactly: chunked, idempotent per month,
 * money through WalletService. On success it advances the cycle and evaluates the
 * just-completed month's follower guarantee (adjusting priority_score). On a
 * short wallet it PAUSES (never cancels), reminds the owner, and auto-resumes on
 * the next successful charge. Runs daily; chunked so its runtime stays flat as
 * subscriber count grows.
 */
class BrandSubscriptionsBillCommand extends Command
{
    protected $signature = 'brand-subscriptions:bill';

    protected $description = 'Charge due brand listing subscriptions, pause short ones, and update priority scores.';

    public function handle(WalletService $wallet, BrandPriorityService $priority, BrandSubscriptionService $subs): int
    {
        $billed = 0;
        $paused = 0;

        BrandSubscription::whereIn('status', [BrandSubscription::ACTIVE, BrandSubscription::PAST_DUE])
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now())
            ->with(['brandPartner.owner', 'plan'])
            ->chunkById(100, function ($rows) use ($wallet, $priority, $subs, &$billed, &$paused) {
                foreach ($rows as $sub) {
                    $brand = $sub->brandPartner;
                    $owner = $brand?->owner;
                    $plan = $sub->plan;
                    if (! $brand || ! $owner || ! $plan) {
                        continue;
                    }

                    // Keyed on the PERIOD being charged (next_billing_at), not the
                    // calendar month the command happens to run in — a now()-keyed
                    // reference would let a late or repeated run in the same real
                    // month hit WalletService's idempotency guard (no new debit) while
                    // still advancing next_billing_at below, silently forgiving a
                    // month's charge. Keying on the actual due date guarantees each
                    // distinct billing period is charged exactly once, however late.
                    $ref = "brand-sub:{$sub->id}:".Carbon::parse($sub->next_billing_at)->format('Y-m');
                    try {
                        $wallet->debit($owner, (float) $plan->price_usd_per_month, 'USD', [
                            'reference' => $ref,
                            'description' => 'Brand listing — '.$plan->name.' (monthly)',
                        ]);
                    } catch (InsufficientBalanceException) {
                        $this->pause($sub, $brand, $owner, $plan);
                        $paused++;

                        continue;
                    } catch (\Throwable $e) {
                        Log::warning("brand-subscriptions:bill failed for sub {$sub->id}: ".$e->getMessage());

                        continue;
                    }

                    // §6: evaluate the JUST-COMPLETED month before advancing.
                    $periodStart = Carbon::parse($sub->last_charged_at ?? $sub->started_at);
                    $priority->evaluate($sub, $periodStart, now());

                    $sub->forceFill([
                        'status' => BrandSubscription::ACTIVE,
                        'last_charged_at' => now(),
                        'next_billing_at' => Carbon::parse($sub->next_billing_at)->addMonthNoOverflow(),
                        'grace_reminders_sent' => 0,
                    ])->save();

                    // Resume a paused listing automatically (§5.2.4) if setup is complete.
                    if ($brand->listing_status === BrandPartner::STATUS_PAUSED) {
                        $brand->forceFill([
                            'listing_status' => $subs->setupComplete($brand) ? BrandPartner::STATUS_ACTIVE : BrandPartner::STATUS_PENDING,
                        ])->save();
                    }
                    $billed++;
                }
            });

        $this->info("Billed {$billed} listing(s); paused {$paused}.");

        return self::SUCCESS;
    }

    private function pause(BrandSubscription $sub, BrandPartner $brand, $owner, $plan): void
    {
        if ($sub->status !== BrandSubscription::PAST_DUE) {
            $sub->forceFill(['status' => BrandSubscription::PAST_DUE])->save();
        }
        if ($brand->listing_status !== BrandPartner::STATUS_PAUSED) {
            $brand->forceFill(['listing_status' => BrandPartner::STATUS_PAUSED])->save();
        }
        // One reminder per daily run (the command runs daily, so this is once/day).
        $sub->increment('grace_reminders_sent');
        $owner->notify(new BrandBillingReminderNotification($brand->brand_name, (float) $plan->price_usd_per_month, $plan->name));
    }
}
