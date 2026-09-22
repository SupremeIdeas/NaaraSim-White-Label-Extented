<?php

namespace App\Services\Social;

use App\Models\BrandPartner;
use App\Models\BrandPartnerFollower;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * "Connect" to a brand on Naara (owner request, 2026-09-22) — a plain
 * in-platform follow/unfollow toggle with a cached follower count, distinct
 * from SocialFollowService (which claims a NaaraCredit reward for following
 * an external social handle). No reward here, just a social relationship.
 */
class BrandFollowService
{
    /** @return array{following: bool, count: int} */
    public function toggle(User $user, BrandPartner $brand): array
    {
        return DB::transaction(function () use ($user, $brand) {
            $locked = BrandPartner::whereKey($brand->id)->lockForUpdate()->firstOrFail();

            $existing = BrandPartnerFollower::where('brand_partner_id', $brand->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $locked->decrement('naara_followers_count');

                return ['following' => false, 'count' => max(0, (int) $locked->naara_followers_count)];
            }

            try {
                BrandPartnerFollower::create(['brand_partner_id' => $brand->id, 'user_id' => $user->id]);
            } catch (UniqueConstraintViolationException) {
                // Already connected (a concurrent double-tap) — report the current state, don't double-count.
                return ['following' => true, 'count' => (int) $locked->naara_followers_count];
            }

            $locked->increment('naara_followers_count');

            return ['following' => true, 'count' => (int) $locked->naara_followers_count];
        });
    }
}
