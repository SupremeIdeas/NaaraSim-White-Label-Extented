<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prompt 21-EXT §1 — the seeded, admin-editable license plan catalog that
 * supersedes Prompt 21 §4.1's "no fixed price list" instruction. `tier`
 * matches WhiteLabelInstance::TIERS exactly; Extended and Extended V2 both
 * resolve to the same entitlement (full) — only `support_level` differs
 * between them, per the owner's own framing ("all same plan only that the
 * extended V2 is pro with more Extended support").
 */
class WhiteLabelLicensePlan extends Model
{
    public const KEY_BASIC = 'basic';

    public const KEY_MEDIUM = 'medium';

    public const KEY_EXTENDED = 'extended';

    public const KEY_EXTENDED_V2 = 'extended_v2';

    public const SUPPORT_STANDARD = 'standard';

    public const SUPPORT_PRIORITY = 'priority';

    /** Prompt 21-EXT §6 — admin-controlled resell-status flags (Setting-backed,
     *  default open). The SAME two keys are read here (register()'s server-side
     *  gate and the carousel's visible-but-locked state) and written by the
     *  auto-close threshold check, so the UI and enforcement can never disagree. */
    public const SETTING_NORMAL_OPEN = 'whitelabel.resell.normal_open';

    public const SETTING_EXTENDED_OPEN = 'whitelabel.resell.extended_open';

    protected $fillable = [
        'key', 'name', 'tagline', 'description', 'price_usd', 'tier',
        'support_level', 'cover_image_url', 'features', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_usd' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WhiteLabelInstance::class, 'license_plan_id');
    }

    /** The balance still owed to reach the Extended tier from THIS plan's
     *  price — null for a plan that's already at/above Extended (nothing to
     *  top up), computed live against the currently-active Extended plan so
     *  a future price change is reflected automatically, never hardcoded. */
    public static function balanceToExtended(self $plan): ?float
    {
        if ($plan->tier === WhiteLabelInstance::TIER_EXTENDED) {
            return null;
        }

        $extended = self::where('tier', WhiteLabelInstance::TIER_EXTENDED)
            ->where('is_active', true)
            ->orderBy('price_usd')
            ->first();

        if ($extended === null) {
            return null;
        }

        return max(0.0, round((float) $extended->price_usd - (float) $plan->price_usd, 2));
    }

    /** Whether new self-service requests are currently accepted for the given
     *  tier (Prompt 21-EXT §6). Defaults open so a fresh install never starts
     *  accidentally closed. */
    public static function resellOpenForTier(string $tier): bool
    {
        $key = $tier === WhiteLabelInstance::TIER_EXTENDED ? self::SETTING_EXTENDED_OPEN : self::SETTING_NORMAL_OPEN;

        return (bool) Setting::getValue($key, true);
    }

    /** How many self-service sales of the given tier have actually landed
     *  (license issued) so far — the single query both the auto-close check
     *  (WhiteLabelLicenseService::checkResellAutoClose()) and the admin's
     *  live threshold display read, so they can never drift apart. */
    public static function soldCountForTier(string $tier): int
    {
        return WhiteLabelInstance::where('acquisition_method', WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE)
            ->where('tier', $tier)
            ->whereNotNull('license_issued_at')
            ->count();
    }
}
