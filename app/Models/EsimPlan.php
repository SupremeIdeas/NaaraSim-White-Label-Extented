<?php

namespace App\Models;

use App\Services\Pricing\CurrencyService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsimPlan extends Model
{
    /** Coverage axis for the customer-facing Local/Regional/Global tabs (§3). */
    public const COVERAGE_LOCAL = 'local';

    public const COVERAGE_REGIONAL = 'regional';

    public const COVERAGE_GLOBAL = 'global';

    /** @var list<string> */
    public const COVERAGE_TYPES = [self::COVERAGE_LOCAL, self::COVERAGE_REGIONAL, self::COVERAGE_GLOBAL];

    /**
     * Prompt 10: every "unlimited" plan must disclose a fair-usage threshold.
     * No provider integration (eSIM Go, Airalo, Quibity, Zendit, 1GLOBAL,
     * Monty Mobile, Gigs) currently exposes a machine-readable per-plan FUP
     * value, so a plan without an admin-entered real threshold falls back to
     * this honest, non-numeric disclosure rather than a fabricated figure.
     */
    public const DEFAULT_FAIR_USAGE_NOTE = 'Unlimited data is subject to the provider\'s fair usage policy: '
        .'full speed may be reduced after a daily high-speed data threshold to keep the service fair for everyone. '
        .'Essentials like maps, messaging, and browsing keep working at reduced speed.';

    protected $fillable = [
        'provider',
        'provider_plan_id',
        'name',
        'type',
        'coverage_type', // local | regional | global (BUILD-8 §2.1)
        'region_slug',   // e.g. europe, caribbean, world; null for a single-country local plan
        'has_voice',
        'data_mb',
        'countries',
        'validity_days',
        'cost_price_usd',
        'airalo_min_price',
        'markup_pct',
        'override_markup_pct',
        'computed_retail_usd',
        'manual_retail_usd',
        'is_active',
        'is_featured',
        'ai_tooltip',            // generated customer description (§5)
        'ai_tooltip_override',   // manual admin description — always wins (§4.6)
        'ai_tooltip_generated_at',
        'fair_usage_note',       // admin-entered real FUP threshold for an unlimited plan (Prompt 10)
        'synced_at',
        // NOTE: final_retail_usd is a generated column and is intentionally NOT
        // fillable — the database computes COALESCE(manual, computed).
    ];

    /**
     * Money-safety rule 1.2: never expose cost. These columns must never
     * appear in a user-facing payload, so they are hidden from array/JSON.
     */
    protected $hidden = [
        'cost_price_usd',
        'airalo_min_price',
        'markup_pct',
        'override_markup_pct',
    ];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'data_mb' => 'integer',
            'validity_days' => 'integer',
            'cost_price_usd' => 'decimal:4',
            'airalo_min_price' => 'decimal:4',
            'markup_pct' => 'decimal:3',
            'override_markup_pct' => 'decimal:3',
            'computed_retail_usd' => 'decimal:4',
            'manual_retail_usd' => 'decimal:4',
            'final_retail_usd' => 'decimal:4',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'has_voice' => 'boolean',
            'synced_at' => 'datetime',
            'ai_tooltip_generated_at' => 'datetime',
        ];
    }

    /**
     * The tooltip copy actually shown to customers (§4.6): the manual admin
     * override always wins over the generated text; null when neither exists.
     *
     * @return Attribute<?string, never>
     */
    protected function displayTooltip(): Attribute
    {
        return Attribute::get(fn () => filled($this->ai_tooltip_override)
            ? $this->ai_tooltip_override
            : $this->ai_tooltip);
    }

    /**
     * The fair-usage disclosure actually shown to customers (Prompt 10): only
     * an unlimited plan (data_mb === null) carries one at all — a data-capped
     * plan has no fair-usage threshold to disclose. Prefers an admin-entered
     * real threshold; falls back to the honest generic notice otherwise.
     *
     * @return Attribute<?string, never>
     */
    protected function displayFairUsageNote(): Attribute
    {
        return Attribute::get(fn () => $this->data_mb === null
            ? (filled($this->fair_usage_note) ? $this->fair_usage_note : self::DEFAULT_FAIR_USAGE_NOTE)
            : null);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(EsimOrder::class, 'plan_id');
    }

    /**
     * The ONLY price ever shown to users (blueprint Section 13.4): the
     * generated final_retail_usd formatted as USD + NGN. Never exposes cost.
     * Computed on access (not appended) so serialization stays cheap.
     *
     * @return Attribute<array{usd: string, ngn: string, usd_amount: float, ngn_amount: float}, never>
     */
    protected function displayPrice(): Attribute
    {
        return Attribute::get(fn () => app(CurrencyService::class)
            ->displayPrice((float) $this->final_retail_usd));
    }
}

