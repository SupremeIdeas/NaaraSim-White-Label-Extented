<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * The one maintained list of in-app destinations an admin can point a
 * marketing CTA at — content/billing/hot-menu blueprint Phase C. Built from
 * real named routes only (resolved through route(), never a hand-typed path),
 * so every announcement and broadcast-email link picker reads from the same
 * list instead of each screen growing its own that can quietly drift.
 */
class DeepLinkLibrary
{
    /** @return list<array{key:string,label:string,url:string}> */
    public static function all(): array
    {
        $out = [];
        foreach (self::routes() as [$name, $label]) {
            if (Route::has($name)) {
                $out[] = ['key' => $name, 'label' => $label, 'url' => route($name)];
            }
        }

        return $out;
    }

    /**
     * @return list<array{0:string,1:string}> [route name, admin-facing label]
     *
     * Tier 5 #11 Phase A2 widened this from the original 13 entries to cover
     * every real named destination the blueprint asked for. `merchant.
     * white-label` (the White Label license-purchase page) is deliberately
     * included here rather than gated by a separate isMaster() check — the
     * License Surgery boundary already removed that route entirely from both
     * white-label forks, so the existing Route::has() guard in all() excludes
     * it there automatically, with nothing to drift out of sync.
     */
    private static function routes(): array
    {
        return [
            ['home', 'Home'],
            ['catalogue', 'eSIM catalogue'],
            ['numbers', 'Numbers & lines'],
            ['numbers.lines', 'My lines'],
            ['numbers.dialer', 'Dialer'],
            ['numbers.messages', 'Messages'],
            ['gift-cards', 'Naara Gift'],
            ['wallet', 'Wallet / shared wallet plans'],
            ['dashboard', 'Dashboard'],
            ['data-estimator', 'Data estimator'],
            ['profile', 'Profile'],
            ['account', 'Account'],
            ['journey', 'My Journey'],
            ['referrals', 'Referrals'],
            ['rewards', 'Rewards'],
            ['merchant.apply', 'Become a Merchant'],
            ['brand.get-listed', 'List my brand'],
            ['merchant.white-label', 'White Label purchase (master only)'],
            ['support', 'Support chat'],
            ['refund-policy', 'Refund policy'],
        ];
    }
}
