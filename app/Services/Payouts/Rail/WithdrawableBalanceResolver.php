<?php

namespace App\Services\Payouts\Rail;

use App\Models\Merchant;
use App\Models\MerchantEarning;
use App\Models\Partner;
use App\Models\PartnerEarning;
use App\Models\ReferralEarning;
use App\Models\StaffEarning;
use App\Models\User;
use App\Models\UserWallet;
use App\Support\CreditSettings;
use App\Support\Money4;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * What each user could withdraw RIGHT NOW, in USD, by bucket (Funding Radar R2). Only
 * the five platform-funded buckets count — deposit-funded wallet money is never
 * withdrawable and never appears here. Holds are already spent from the bucket, so
 * nothing is subtracted; negative balances count as zero.
 *
 * `forUsers()` answers for N users in a fixed number of queries (no N+1) and is meant
 * for ENROLLED users only — never a scan of the whole user table.
 */
class WithdrawableBalanceResolver
{
    public const BUCKETS = ['credits', 'referral', 'merchant', 'partner', 'staff'];

    /** @return array{credits: string, referral: string, merchant: string, partner: string, staff: string, total: string} */
    public function forUser(User $user): array
    {
        return Cache::remember("payout:balances:{$user->id}", 60, fn () => $this->forUsers(collect([$user->id]))[$user->id]);
    }

    /** Drop a user's cached balances (call on hold / release / accrue). */
    public static function forget(int $userId): void
    {
        Cache::forget("payout:balances:{$userId}");
    }

    /**
     * @param  iterable<int>  $userIds
     * @return array<int, array{credits: string, referral: string, merchant: string, partner: string, staff: string, total: string}>
     */
    public function forUsers(iterable $userIds): array
    {
        $ids = collect($userIds)->map(fn ($i) => (int) $i)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $credits = UserWallet::whereIn('user_id', $ids)->pluck('withdrawable_credits', 'user_id')
            ->map(fn ($c) => Money4::units(CreditSettings::creditsToUsd((float) $c)));
        $referral = $this->latestBalances(ReferralEarning::class, 'user_id', $ids);
        $staff = $this->latestBalances(StaffEarning::class, 'user_id', $ids);

        $merchantByOwner = Merchant::whereIn('owner_user_id', $ids)->where('status', Merchant::ACTIVE)->pluck('id', 'owner_user_id');
        $merchantBal = $this->latestBalances(MerchantEarning::class, 'merchant_id', $merchantByOwner->values());
        $partnerByOwner = Partner::whereIn('owner_user_id', $ids)->where('status', Partner::ACTIVE)->pluck('id', 'owner_user_id');
        $partnerBal = $this->latestBalances(PartnerEarning::class, 'partner_id', $partnerByOwner->values());

        $out = [];
        foreach ($ids as $id) {
            $b = [
                'credits' => max(0, (int) ($credits[$id] ?? 0)),
                'referral' => max(0, (int) ($referral[$id] ?? 0)),
                'merchant' => max(0, (int) ($merchantBal[$merchantByOwner[$id] ?? 0] ?? 0)),
                'partner' => max(0, (int) ($partnerBal[$partnerByOwner[$id] ?? 0] ?? 0)),
                'staff' => max(0, (int) ($staff[$id] ?? 0)),
            ];
            $out[$id] = array_map(Money4::str(...), $b) + ['total' => Money4::str(array_sum($b))];
        }

        return $out;
    }

    /**
     * The newest ledger row's balance_after per owner, in one query.
     *
     * @param  class-string<Model>  $model
     * @return \Illuminate\Support\Collection<int, int> owner id => units
     */
    private function latestBalances(string $model, string $ownerColumn, $ownerIds)
    {
        $ownerIds = collect($ownerIds)->filter()->values();
        if ($ownerIds->isEmpty()) {
            return collect();
        }
        $table = (new $model)->getTable();

        return $model::query()
            ->whereIn('id', fn ($q) => $q->from($table)->selectRaw('MAX(id)')->whereIn($ownerColumn, $ownerIds)->groupBy($ownerColumn))
            ->pluck('balance_after', $ownerColumn)
            ->map(fn ($v) => Money4::units($v));
    }
}
