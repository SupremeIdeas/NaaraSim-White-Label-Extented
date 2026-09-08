<?php

namespace App\Services\GiftCards;

use App\Models\GiftCardProduct;
use App\Support\SyncStatus;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Syncs BOTH gift-card providers into one catalogue (Naara Gift). Mirrors the
 * eSIM CatalogueSyncService and inherits the same queue-restart-on-key-save fix
 * (shared Zendit key; Reloadly is key-gated too). After upserting, it recomputes
 * `is_primary` so the storefront buys from Reloadly wherever both carry a brand,
 * and falls back to Zendit only where Reloadly doesn't (admin's chosen routing).
 */
class GiftCardCatalogueSyncService
{
    /** @var array<string, class-string<GiftCardProviderInterface>> */
    private const PROVIDERS = [
        'reloadly' => ReloadlyGiftCardService::class,
        'zendit' => ZenditVoucherService::class,
        // NAARA-BUILD-18 — registered gift adapters (enabled=false until onboarded).
        'bitrefill' => BitrefillService::class,
        'tillo' => TilloService::class, // placeholder tier
    ];

    public function provider(string $key): GiftCardProviderInterface
    {
        // Container binding first (tests can swap a fake), else the real service.
        if (app()->bound('giftcard.'.$key)) {
            return app('giftcard.'.$key);
        }
        $class = self::PROVIDERS[$key] ?? throw new \InvalidArgumentException("Unknown gift-card provider [$key].");

        return app($class);
    }

    /**
     * Registered provider keys in PRIORITY order (routing/fallback rank, not
     * alphabetical) — Reloadly wins wherever it carries a brand, Zendit fills
     * gaps, then Bitrefill, then Tillo. Admin UI + recomputePrimary() both
     * drive off this single list so a newly-registered provider only needs
     * adding to PROVIDERS above.
     *
     * @return array<int, string>
     */
    public function providerKeys(): array
    {
        return array_keys(self::PROVIDERS);
    }

    /** Sync one provider; returns the number of products upserted. */
    public function sync(string $provider): int
    {
        $svc = $this->provider($provider);
        if (! $svc->available()) {
            SyncStatus::record('giftcards:'.$provider, false, 0, 'Provider keys not configured.');

            return 0;
        }

        try {
            $count = 0;
            foreach ($svc->getCatalogue() as $p) {
                if (($p['provider_product_id'] ?? '') === '') {
                    continue;
                }
                GiftCardProduct::updateOrCreate(
                    ['provider' => $p['provider'], 'provider_product_id' => $p['provider_product_id']],
                    collect($p)->only([
                        'brand_key', 'brand_name', 'country', 'currency', 'denomination_type',
                        'fixed_denominations', 'min_amount', 'max_amount', 'logo_url', 'brand_color',
                        'category', 'required_fields', 'redeem_instruction', 'cost_meta', 'provider_enabled',
                        'priceable',
                    ])->all(),
                );
                $count++;
            }

            $this->recomputePrimary();
            SyncStatus::record('giftcards:'.$provider, true, $count);

            return $count;
        } catch (Throwable $e) {
            SyncStatus::record('giftcards:'.$provider, false, null, $e->getMessage());

            return 0;
        }
    }

    /**
     * Each provider is primary per brand+country wherever no HIGHER-priority
     * provider (earlier in providerKeys()) already carries that brand+country
     * combo — generalizes the old Reloadly-then-Zendit-only routing to all N
     * registered providers, so Bitrefill/Tillo can actually become primary
     * for a brand neither Reloadly nor Zendit carries.
     */
    public function recomputePrimary(): void
    {
        GiftCardProduct::query()->update(['is_primary' => false]);

        $ranked = $this->providerKeys();
        foreach ($ranked as $i => $provider) {
            $higher = array_slice($ranked, 0, $i);

            GiftCardProduct::where('provider', $provider)
                ->when($higher !== [], fn ($q) => $q->whereNotExists(function ($sub) use ($higher) {
                    $sub->select(DB::raw(1))->from('gift_card_products as r')
                        ->whereColumn('r.brand_key', 'gift_card_products.brand_key')
                        ->whereIn('r.provider', $higher)
                        ->where(function ($w) {
                            $w->whereColumn('r.country', 'gift_card_products.country')
                                ->orWhere(function ($n) {
                                    $n->whereNull('r.country')->whereNull('gift_card_products.country');
                                });
                        });
                }))
                ->update(['is_primary' => true]);
        }
    }
}
