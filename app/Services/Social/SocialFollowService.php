<?php

namespace App\Services\Social;

use App\Models\BrandPartnerHandle;
use App\Models\SocialFollowClaim;
use App\Models\SocialFollowHandle;
use App\Models\User;
use App\Services\Credits\CreditService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Follow-to-earn claims (BUILD-6 §C.3). Grants the surprise NaaraCredit ONCE per
 * user per handle, server-side only, recording the one-time claim in the same
 * transaction. Reuses CreditService::earn (no second ledger). The follow signal
 * — a self-confirmed tap or a verified API callback — must be validated by the
 * caller BEFORE this runs; opening the handle link alone never grants anything.
 */
class SocialFollowService
{
    public function __construct(private readonly CreditService $credits)
    {
    }

    /** True if the user has already claimed this handle (re-checked on every render). */
    public function hasClaimed(User $user, SocialFollowHandle|BrandPartnerHandle $handle): bool
    {
        return $this->claimQuery($user, $handle)->exists();
    }

    /**
     * Claim a follow. One-time and unrepeatable: the unique (user, handle) index
     * is the hard guarantee (a concurrent double-tap hits it and returns
     * already-claimed), and CreditService::earn is idempotent by reference so a
     * credit can never double-grant even under a retry.
     *
     * @return array{earned: float, already: bool, capped?: bool}
     */
    public function claim(User $user, SocialFollowHandle|BrandPartnerHandle $handle): array
    {
        if (! (bool) $handle->is_active) {
            return ['earned' => 0.0, 'already' => false];
        }

        // Daily 100-credit cap (BUILD-9 §4). If already reached, block the claim
        // entirely and DON'T record it — the follow can be claimed again tomorrow.
        if (! $this->hasClaimed($user, $handle) && \App\Support\DailyCreditCap::isReached($user)) {
            return ['earned' => 0.0, 'already' => false, 'capped' => true];
        }

        [$column, $source, $reference] = $this->refFor($user, $handle);

        return DB::transaction(function () use ($user, $handle, $column, $source, $reference) {
            try {
                SocialFollowClaim::create([
                    'user_id' => $user->id,
                    $column => $handle->id,
                    'claimed_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return ['earned' => 0.0, 'already' => true];
            }

            $reward = round((float) $handle->credit_reward, 2);
            if ($reward > 0) {
                $this->credits->earn($user, $reward, $source, $reference, 'Followed '.$handle->handle_label);
            }

            return ['earned' => $reward, 'already' => false];
        });
    }

    /**
     * Claimed platform-handle ids for this user, as an id => true map for O(1)
     * lookup while rendering. Server-side truth — never trust a client flag.
     *
     * @return array<int, bool>
     */
    public function claimedHandleIds(User $user): array
    {
        return SocialFollowClaim::where('user_id', $user->id)
            ->whereNotNull('handle_id')->pluck('handle_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /** @return array<int, bool> */
    public function claimedBrandHandleIds(User $user): array
    {
        return SocialFollowClaim::where('user_id', $user->id)
            ->whereNotNull('brand_partner_handle_id')->pluck('brand_partner_handle_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    private function claimQuery(User $user, SocialFollowHandle|BrandPartnerHandle $handle)
    {
        $column = $handle instanceof BrandPartnerHandle ? 'brand_partner_handle_id' : 'handle_id';

        return SocialFollowClaim::where('user_id', $user->id)->where($column, $handle->id);
    }

    /** @return array{0:string,1:string,2:string} [column, credit source, idempotent reference] */
    private function refFor(User $user, SocialFollowHandle|BrandPartnerHandle $handle): array
    {
        if ($handle instanceof BrandPartnerHandle) {
            return ['brand_partner_handle_id', 'brand_follow', "brand_follow:{$user->id}:{$handle->id}"];
        }

        return ['handle_id', 'social_follow', "social_follow:{$user->id}:{$handle->id}"];
    }
}
